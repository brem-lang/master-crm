<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DashboardController extends Controller
{
    private const RANGES = ['today', 'yesterday', 'week', 'last_week', 'month', 'last_month', 'all', 'custom'];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        if (! $companyScoped && ! $allCompanies) {
            return Inertia::render('dashboard', ['analytics' => null]);
        }

        return Inertia::render('dashboard', [
            'analytics' => $this->buildAnalytics($request, $companyScoped ? $user->company_id : null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAnalytics(Request $request, ?int $companyId): array
    {
        [$start, $end, $rangeMeta] = $this->resolveRange($request);

        $affiliate = $request->query('affiliate');
        $advertiser = $request->query('advertiser');

        $base = fn (): Builder => Lead::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($start, fn ($query) => $query->where('lead_created_at', '>=', $start))
            ->when($end, fn ($query) => $query->where('lead_created_at', '<=', $end))
            ->when($affiliate, fn ($query) => $query->where('affiliate_name', $affiliate));

        // One bulk fetch drives everything that has to inspect `meta->lead_distributions`
        // (a JSON array — not portable to aggregate in SQL across MySQL/SQLite). Every
        // other metric below is a single aggregate query; nothing here loops per row
        // against the database.
        $bulk = $base()->get(['id', 'meta', 'is_ftd', 'lead_created_at']);

        $advertiserIndex = $this->tallyByAdvertiser($bulk);

        $advertiserLeadIds = $advertiser
            ? $bulk->filter(fn (Lead $lead) => in_array($advertiser, $this->advertiserNames($lead), true))->pluck('id')
            : null;

        $filteredBulk = $advertiserLeadIds ? $bulk->whereIn('id', $advertiserLeadIds) : $bulk;

        $scoped = fn (): Builder => $base()->when($advertiserLeadIds, fn ($query) => $query->whereIn('id', $advertiserLeadIds));

        $stats = $scoped()
            ->selectRaw('
                count(*) as total,
                sum(case when status = ? then 1 else 0 end) as rejected,
                sum(case when is_ftd = 1 then 1 else 0 end) as ftd
            ', ['rejected'])
            ->first();

        $total = (int) $stats->total;
        $ftd = (int) $stats->ftd;
        $rejected = (int) $stats->rejected;

        $pendingFtd = $scoped()
            ->where('is_ftd', true)
            ->where(fn ($query) => $query->whereNull('meta->ftd_released')->orWhere('meta->ftd_released', false))
            ->count();

        [$sent, $failed] = $this->sentFailedCounts($filteredBulk);

        $topCountries = $scoped()
            ->select('country_code')
            ->selectRaw('count(*) as leads, sum(is_ftd) as ftd')
            ->groupBy('country_code')
            ->orderByDesc('leads')
            ->limit(5)
            ->get()
            ->map(fn ($row) => $this->withConversionRate($row->country_code, (int) $row->leads, (int) $row->ftd));

        $topAffiliates = $scoped()
            ->whereNotNull('affiliate_name')
            ->where('affiliate_name', '!=', '')
            ->select('affiliate_name')
            ->selectRaw('count(*) as leads, sum(is_ftd) as ftd')
            ->groupBy('affiliate_name')
            ->orderByDesc('leads')
            ->limit(5)
            ->get()
            ->map(fn ($row) => $this->withConversionRate($row->affiliate_name, (int) $row->leads, (int) $row->ftd));

        $topAdvertisers = collect($advertiserIndex)
            ->when($advertiser, fn ($collection) => $collection->only([$advertiser]))
            ->map(fn ($tally, $name) => $this->withConversionRate($name, $tally['sent'], $tally['ftd'], 'sent'))
            ->sortByDesc('sent')
            ->take(5)
            ->values();

        return [
            'stats' => [
                'total' => $total,
                'rejected' => $rejected,
                'rejectionRate' => $total > 0 ? round($rejected / $total * 100, 2) : 0.0,
                'ftd' => $ftd,
                'conversionRate' => $total > 0 ? round($ftd / $total * 100, 2) : 0.0,
                'pendingFtd' => $pendingFtd,
                'sent' => $sent,
                'failed' => $failed,
            ],
            'series' => $this->buildSeries($filteredBulk, $start, $end),
            'topCountries' => $topCountries,
            'topAffiliates' => $topAffiliates,
            'topAdvertisers' => $topAdvertisers,
            'filters' => [
                'range' => $rangeMeta['range'],
                'from' => $rangeMeta['from'],
                'to' => $rangeMeta['to'],
                'affiliate' => $affiliate,
                'advertiser' => $advertiser,
            ],
            'affiliateOptions' => $base()
                ->whereNotNull('affiliate_name')
                ->where('affiliate_name', '!=', '')
                ->distinct()
                ->orderBy('affiliate_name')
                ->pluck('affiliate_name'),
            'advertiserOptions' => collect($advertiserIndex)->keys()->sort()->values(),
        ];
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null, 2: array{range: string, from: string|null, to: string|null}}
     */
    private function resolveRange(Request $request): array
    {
        $range = $request->query('range', 'today');

        if (! in_array($range, self::RANGES, true)) {
            $range = 'today';
        }

        $now = now();

        [$start, $end] = match ($range) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'all' => [null, null],
            'custom' => $this->resolveCustomRange($request, $now),
        };

        return [$start, $end, [
            'range' => $range,
            'from' => $start?->toDateString(),
            'to' => $end?->toDateString(),
        ]];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveCustomRange(Request $request, CarbonInterface $now): array
    {
        try {
            $start = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : $now->copy()->startOfDay();
            $end = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : $now->copy()->endOfDay();
        } catch (Throwable) {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        }

        return $start->gt($end) ? [$end->copy()->startOfDay(), $start->copy()->endOfDay()] : [$start, $end];
    }

    /**
     * Tally sent/FTD counts per advertiser name from `meta->lead_distributions`.
     *
     * @param  Collection<int, Lead>  $leads
     * @return array<string, array{sent: int, ftd: int}>
     */
    private function tallyByAdvertiser(Collection $leads): array
    {
        $index = [];

        foreach ($leads as $lead) {
            $seenForLead = [];

            foreach ($this->distributions($lead) as $distribution) {
                $name = $distribution['advertisers']['name'] ?? null;

                if (! $name || in_array($name, $seenForLead, true)) {
                    continue;
                }

                $seenForLead[] = $name;
                $index[$name] ??= ['sent' => 0, 'ftd' => 0];

                if (($distribution['status'] ?? null) === 'sent') {
                    $index[$name]['sent']++;

                    if ($lead->is_ftd) {
                        $index[$name]['ftd']++;
                    }
                }
            }
        }

        return $index;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return array{0: int, 1: int}
     */
    private function sentFailedCounts(Collection $leads): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($leads as $lead) {
            $distributions = $this->distributions($lead);

            if ($distributions === []) {
                continue;
            }

            collect($distributions)->contains(fn ($d) => ($d['status'] ?? null) === 'sent') ? $sent++ : $failed++;
        }

        return [$sent, $failed];
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return list<array{label: string, leads: int, ftd: int}>
     */
    private function buildSeries(Collection $leads, ?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $hourly = $start && $end && $start->diffInHours($end) <= 24;
        $buckets = [];

        foreach ($leads as $lead) {
            $timestamp = $lead->lead_created_at;

            if (! $timestamp) {
                continue;
            }

            $key = $hourly ? $timestamp->format('Y-m-d H:00') : $timestamp->format('Y-m-d');

            $buckets[$key] ??= [
                'label' => $hourly ? $timestamp->format('H:i') : $timestamp->format('M j'),
                'leads' => 0,
                'ftd' => 0,
            ];

            $buckets[$key]['leads']++;

            if ($lead->is_ftd) {
                $buckets[$key]['ftd']++;
            }
        }

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function distributions(Lead $lead): array
    {
        $distributions = $lead->meta['lead_distributions'] ?? [];

        return is_array($distributions) ? $distributions : [];
    }

    /**
     * @return list<string>
     */
    private function advertiserNames(Lead $lead): array
    {
        return collect($this->distributions($lead))
            ->map(fn ($d) => $d['advertisers']['name'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, leads?: int, sent?: int, ftd: int, cr: float}
     */
    private function withConversionRate(?string $name, int $count, int $ftd, string $countKey = 'leads'): array
    {
        return [
            'name' => $name ?? 'Unknown',
            $countKey => $count,
            'ftd' => $ftd,
            'cr' => $count > 0 ? round($ftd / $count * 100, 1) : 0.0,
        ];
    }
}
