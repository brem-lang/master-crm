<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class LogViewerController extends Controller
{
    /**
     * Only the tail of the log file is read so memory/CPU stay bounded even
     * when the file has grown large during an incident.
     */
    protected const MAX_BYTES = 5 * 1024 * 1024;

    protected const HEADER_PATTERN = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\S+)\.(\w+): (.*)$/';

    protected const ERROR_LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    protected const WARNING_LEVELS = ['WARNING'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view-system-logs'), 403);

        $search = trim((string) $request->query('search', ''));
        $level = $request->query('level');

        $entries = $this->parseEntries();

        $stats = [
            'total' => count($entries),
            'errors' => count(array_filter($entries, fn ($entry) => in_array($entry['level'], self::ERROR_LEVELS, true))),
            'warnings' => count(array_filter($entries, fn ($entry) => in_array($entry['level'], self::WARNING_LEVELS, true))),
            'info' => count(array_filter($entries, fn ($entry) => ! in_array($entry['level'], [...self::ERROR_LEVELS, ...self::WARNING_LEVELS], true))),
        ];

        $filtered = array_values(array_filter($entries, function ($entry) use ($search, $level) {
            if ($level && $entry['level'] !== $level) {
                return false;
            }

            if ($search !== '' && ! str_contains(strtolower($entry['message'].$entry['body']), strtolower($search))) {
                return false;
            }

            return true;
        }));

        return Inertia::render('logs/index', [
            'stats' => $stats,
            'entries' => $this->paginate($filtered, $request),
            'filters' => [
                'search' => $search,
                'level' => $level,
            ],
        ]);
    }

    /**
     * @return list<array{id: int, timestamp: string, environment: string, level: string, message: string, body: string}>
     */
    protected function parseEntries(): array
    {
        $path = config('logging.channels.single.path', storage_path('logs/laravel.log'));

        if (! is_readable($path)) {
            return [];
        }

        $size = filesize($path);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $bytesToRead = min($size, self::MAX_BYTES);
        fseek($handle, -$bytesToRead, SEEK_END);
        $contents = fread($handle, $bytesToRead);
        fclose($handle);

        $lines = explode("\n", (string) $contents);
        $entries = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match(self::HEADER_PATTERN, $line, $matches) === 1) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'timestamp' => $matches[1],
                    'environment' => $matches[2],
                    'level' => strtoupper($matches[3]),
                    'message' => $matches[4],
                    'body' => $line,
                ];

                continue;
            }

            if ($current !== null && trim($line) !== '') {
                $current['body'] .= "\n".$line;
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        $entries = array_reverse($entries);

        return array_map(fn ($entry, $index) => [...$entry, 'id' => $index], $entries, array_keys($entries));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function paginate(array $items, Request $request): LengthAwarePaginator
    {
        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator(
            array_slice($items, ($page - 1) * $perPage, $perPage),
            count($items),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
