<?php

namespace App\Http\Controllers;

use App\Models\Advertiser;
use App\Models\AuditLog;
use App\Models\Company;
use App\Services\ChildCrmDirectoryClient;
use App\Services\CompanyDirectorySyncer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdvertiserController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($companyScoped || $allCompanies, 403);

        $companyId = $companyScoped ? $user->company_id : ($request->integer('company_id') ?: null);

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $scoped = fn () => Advertiser::query()->when($companyId, fn ($query) => $query->where('company_id', $companyId));

        $stats = $scoped()->selectRaw('
            count(*) as total,
            sum(case when is_active = 1 then 1 else 0 end) as active,
            sum(case when is_active = 0 then 1 else 0 end) as inactive
        ')->first();

        $props = [
            'stats' => [
                'total' => (int) $stats->total,
                'active' => (int) $stats->active,
                'inactive' => (int) $stats->inactive,
            ],
            'advertisers' => $scoped()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->when($status === 'active', fn ($query) => $query->where('is_active', true))
                ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
                ->when(! $companyId, fn ($query) => $query->with('company:id,name'))
                ->latest()
                ->paginate($this->perPage($request))
                ->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'company_id' => $companyId,
            ],
        ];

        if (! $companyScoped) {
            $props['companies'] = Company::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        }

        return Inertia::render('advertisers/index', $props);
    }

    public function sendTestLead(Request $request, Advertiser $advertiser, ChildCrmDirectoryClient $client): JsonResponse
    {
        $user = $request->user();

        $companyScoped = $user->company_id && $user->can('view-company-customers');
        $allCompanies = $user->can('view-all-customers');

        abort_unless($user->can('send-test-leads') && ($companyScoped || $allCompanies), 403);
        abort_if($companyScoped && $advertiser->company_id !== $user->company_id, 403);

        $validated = $request->validate([
            'firstname' => ['nullable', 'string', 'max:255'],
            'lastname' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2'],
            'country' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['required', 'ip'],
            'offer_name' => ['nullable', 'string', 'max:255'],
            'custom1' => ['nullable', 'string', 'max:255'],
            'custom2' => ['nullable', 'string', 'max:255'],
            'custom3' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:35'],
            'password' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:10'],
        ]);

        $result = $client->sendTestLead($advertiser->company, [
            ...$validated,
            'advertiser_id' => $advertiser->external_id,
        ]);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * Pushes an edit to the child CRM's `update-advertiser` endpoint, then
     * mirrors its response locally so this row stays byte-for-byte
     * consistent with what the child CRM now has. Empty strings for the
     * nullable fields are normalized to `null` first, so clearing a field in
     * the form actually clears it (rather than failing `url`/`integer`
     * validation on an empty value). The child API's own field-level
     * validation errors (422) are re-thrown as a normal Laravel validation
     * exception; any other non-2xx reply is surfaced as a toast.
     */
    public function update(Request $request, Advertiser $advertiser, ChildCrmDirectoryClient $client): RedirectResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $advertiser->company_id;

        abort_unless($user->can('update-advertisers') && ($ownsCompany || $user->can('view-all-customers')), 403);

        $request->merge([
            'name' => $request->filled('name') ? $request->input('name') : null,
            'url' => $request->filled('url') ? $request->input('url') : null,
            'daily_cap' => $request->filled('daily_cap') ? $request->input('daily_cap') : null,
            'hourly_cap' => $request->filled('hourly_cap') ? $request->input('hourly_cap') : null,
        ]);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'url', 'max:255'],
            'daily_cap' => ['nullable', 'integer', 'min:0'],
            'hourly_cap' => ['nullable', 'integer', 'min:0'],
        ]);

        // A checkbox that's unchecked simply omits itself from the request —
        // `boolean()` correctly reads that as false, same as CompanyController.
        $validated['is_active'] = $request->boolean('is_active');

        // The `integer` rule above only checks that these look like
        // integers — it doesn't cast them, so a present value is still the
        // raw string the form submitted. Cast explicitly before it's
        // JSON-encoded, or the child CRM's type-aware validation rejects a
        // quoted number.
        if ($validated['daily_cap'] !== null) {
            $validated['daily_cap'] = (int) $validated['daily_cap'];
        }

        if ($validated['hourly_cap'] !== null) {
            $validated['hourly_cap'] = (int) $validated['hourly_cap'];
        }

        $payload = ['id' => $advertiser->external_id, ...$validated];

        $result = $client->updateAdvertiser($advertiser->company, $payload);

        if ($result['status'] === 422 && is_array($result['body']['errors'] ?? null)) {
            throw ValidationException::withMessages(
                collect($result['body']['errors'])
                    ->mapWithKeys(fn ($message, $field) => [$field => [is_string($message) ? $message : __('Invalid value.')]])
                    ->all(),
            );
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $result['body']['message'] ?? __('Failed to update the advertiser with the child CRM.'),
            ]);

            return back();
        }

        // Prefer mirroring the child CRM's own canonical response so this row
        // stays byte-for-byte consistent with it; if it omits `data` for some
        // reason, fall back to just the fields this edit actually touched
        // rather than nulling out columns (advertiser_type, api_key, etc.)
        // that were never part of the request.
        $responseData = $result['body']['data'] ?? null;

        $advertiser->update(
            is_array($responseData) ? CompanyDirectorySyncer::advertiserAttributes($responseData) : $validated,
        );

        AuditLog::record('advertiser.updated', $advertiser, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Advertiser updated.')]);

        return back();
    }

    /**
     * Applies the same edit to every selected advertiser, one
     * {@see update()}-style child-CRM call per advertiser. Only the fields
     * actually present in the request are sent — this is a genuinely
     * partial update, unlike the single-advertiser edit dialog which always
     * submits the full set — so a bulk "deactivate these 5 advertisers"
     * doesn't also have to specify a name/url/caps for all of them.
     * Advertisers the user can't touch are skipped.
     */
    public function bulkUpdate(Request $request, ChildCrmDirectoryClient $client): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('update-advertisers'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:advertisers,id'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'url' => ['sometimes', 'nullable', 'string', 'url', 'max:255'],
            'daily_cap' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'hourly_cap' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $changes = collect($validated)->except('ids')->all();

        if ($changes === []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Select at least one field to change.')]);

            return back();
        }

        // Same string-vs-number/boolean issue as `update()` — cast before
        // these are JSON-encoded.
        if (array_key_exists('daily_cap', $changes) && $changes['daily_cap'] !== null) {
            $changes['daily_cap'] = (int) $changes['daily_cap'];
        }

        if (array_key_exists('hourly_cap', $changes) && $changes['hourly_cap'] !== null) {
            $changes['hourly_cap'] = (int) $changes['hourly_cap'];
        }

        if (array_key_exists('is_active', $changes)) {
            $changes['is_active'] = $request->boolean('is_active');
        }

        $advertisers = Advertiser::whereIn('id', $validated['ids'])->get();

        $updated = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($advertisers as $advertiser) {
            $ownsCompany = $user->company_id && $user->company_id === $advertiser->company_id;

            if (! ($ownsCompany || $user->can('view-all-customers'))) {
                $skipped++;

                continue;
            }

            $payload = ['id' => $advertiser->external_id, ...$changes];

            $result = $client->updateAdvertiser($advertiser->company, $payload);

            if ($result['status'] < 200 || $result['status'] >= 300) {
                $failed++;

                continue;
            }

            $updated++;

            $responseData = $result['body']['data'] ?? null;

            $advertiser->update(
                is_array($responseData) ? CompanyDirectorySyncer::advertiserAttributes($responseData) : $changes,
            );

            AuditLog::record('advertiser.updated', $advertiser, $changes);
        }

        $message = trans_choice('{0} No advertisers updated.|{1} :count advertiser updated.|[2,*] :count advertisers updated.', $updated, ['count' => $updated]);

        if ($failed > 0) {
            $message .= ' '.trans_choice('{1} :count failed.|[2,*] :count failed.', $failed, ['count' => $failed]);
        }

        if ($skipped > 0) {
            $message .= ' '.trans_choice('{1} :count skipped.|[2,*] :count skipped.', $skipped, ['count' => $skipped]);
        }

        Inertia::flash('toast', ['type' => $updated > 0 ? 'success' : 'error', 'message' => $message]);

        return back();
    }

    public function destroy(Request $request, Advertiser $advertiser): RedirectResponse
    {
        $user = $request->user();

        $ownsCompany = $user->company_id && $user->company_id === $advertiser->company_id;

        abort_unless($user->can('delete-advertisers') && ($ownsCompany || $user->can('view-all-customers')), 403);

        AuditLog::record('advertiser.deleted', $advertiser);

        $advertiser->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Advertiser deleted.')]);

        return back();
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('delete-advertisers'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:advertisers,id'],
        ]);

        $deleted = Advertiser::whereIn('id', $validated['ids'])
            ->get()
            ->filter(fn (Advertiser $advertiser) => $user->company_id === $advertiser->company_id || $user->can('view-all-customers'))
            ->each(function (Advertiser $advertiser) {
                AuditLog::record('advertiser.deleted', $advertiser);
                $advertiser->delete();
            })
            ->count();

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No advertisers deleted.|{1} :count advertiser deleted.|[2,*] :count advertisers deleted.', $deleted, ['count' => $deleted])]);

        return back();
    }
}
