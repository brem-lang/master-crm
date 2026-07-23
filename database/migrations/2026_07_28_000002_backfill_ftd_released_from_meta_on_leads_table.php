<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Leads synced before `ftd_released` was promoted to a dedicated column
     * carry the real value inside `meta` instead — this backfills the column
     * for those rows so the Conversions page's counts reflect reality.
     */
    public function up(): void
    {
        DB::table('leads')
            ->where('ftd_released', false)
            ->whereNotNull('meta')
            ->orderBy('id')
            ->select(['id', 'meta'])
            ->each(function ($lead) {
                $meta = json_decode($lead->meta, true);

                if (($meta['ftd_released'] ?? false) === true) {
                    DB::table('leads')->where('id', $lead->id)->update(['ftd_released' => true]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: we can't distinguish backfilled rows from ones synced with the real value after the fact.
    }
};
