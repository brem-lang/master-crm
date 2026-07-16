<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Re-encrypts any `api_key` values left over from before the `encrypted` cast
     * was added to the Company model, so every row is readable through it.
     */
    public function up(): void
    {
        DB::table('companies')->select(['id', 'api_key'])->orderBy('id')->each(function ($company) {
            try {
                Crypt::decryptString($company->api_key);

                return;
            } catch (DecryptException) {
                // Not encrypted yet — fall through and encrypt it below.
            }

            DB::table('companies')
                ->where('id', $company->id)
                ->update(['api_key' => Crypt::encryptString($company->api_key)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: we can't distinguish originally-plaintext keys after the fact.
    }
};
