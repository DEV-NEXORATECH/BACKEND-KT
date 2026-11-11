<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiary_partners', function (Blueprint $table): void {
            $table->string('partner_key', 20)->nullable()->after('id');
        });

        DB::table('beneficiary_partners')
            ->orderBy('id')
            ->each(function (object $partner): void {
                DB::table('beneficiary_partners')
                    ->where('id', $partner->id)
                    ->update(['partner_key' => 'BP-' . str_pad((string) $partner->id, 6, '0', STR_PAD_LEFT)]);
            });

        Schema::table('beneficiary_partners', function (Blueprint $table): void {
            $table->unique('partner_key');
            // Manual codes only need to be unique among active records. A
            // soft-deleted historical row must not permanently block reuse.
            $table->dropUnique('beneficiary_partners_code_unique');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::table('beneficiary_partners', function (Blueprint $table): void {
            $table->dropIndex('beneficiary_partners_code_index');
            $table->unique('code');
            $table->dropUnique('beneficiary_partners_partner_key_unique');
            $table->dropColumn('partner_key');
        });
    }
};
