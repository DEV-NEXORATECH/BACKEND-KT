<?php

namespace App\Services\Master;

use App\Models\Master\Vendor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendorCodeGeneratorService
{
    public const PREFIX = 'VND';
    public const PAD_LENGTH = 6;

    /**
     * Generate the next unique, monotonic vendor code in a concurrency-safe manner.
     * Uses row-level database locking (lockForUpdate) inside a transaction.
     * Format: VND-000001, VND-000002, ...
     */
    public function generate(): string
    {
        $this->ensureSequenceTableExists();

        return DB::transaction(function () {
            // Lock the sequence row to serialize concurrent allocations
            $seq = DB::table('vendor_code_sequences')
                ->where('prefix', self::PREFIX)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                $maxExisting = $this->getCurrentMaxNumber();
                DB::table('vendor_code_sequences')->updateOrInsert(
                    ['prefix' => self::PREFIX],
                    [
                        'last_number' => $maxExisting,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $seq = DB::table('vendor_code_sequences')
                    ->where('prefix', self::PREFIX)
                    ->lockForUpdate()
                    ->first();
            }

            $currentNumber = $seq ? (int) $seq->last_number : $this->getCurrentMaxNumber();

            // Find the next available number that doesn't collide with existing vendors
            do {
                $currentNumber++;
                $candidateCode = sprintf('%s-%0' . self::PAD_LENGTH . 'd', self::PREFIX, $currentNumber);
                $exists = Vendor::withoutGlobalScopes()
                    ->where('code', $candidateCode)
                    ->exists();
            } while ($exists);

            DB::table('vendor_code_sequences')
                ->where('prefix', self::PREFIX)
                ->update([
                    'last_number' => $currentNumber,
                    'updated_at' => now(),
                ]);

            return $candidateCode;
        }, 5);
    }

    /**
     * Preview the next vendor code without incrementing the sequence counter.
     */
    public function previewNext(): string
    {
        $this->ensureSequenceTableExists();

        $seq = DB::table('vendor_code_sequences')
            ->where('prefix', self::PREFIX)
            ->first();

        $lastNumber = $seq ? (int) $seq->last_number : $this->getCurrentMaxNumber();
        $nextNumber = $lastNumber;

        do {
            $nextNumber++;
            $candidateCode = sprintf('%s-%0' . self::PAD_LENGTH . 'd', self::PREFIX, $nextNumber);
            $exists = Vendor::withoutGlobalScopes()
                ->where('code', $candidateCode)
                ->exists();
        } while ($exists);

        return $candidateCode;
    }

    /**
     * Scan existing vendors globally (without global scopes) for the maximum VND integer suffix.
     */
    public function getCurrentMaxNumber(): int
    {
        if (! Schema::hasTable('vendors')) {
            return 0;
        }

        $codes = Vendor::withoutGlobalScopes()
            ->whereNotNull('code')
            ->where('code', 'LIKE', self::PREFIX . '-%')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^' . preg_quote(self::PREFIX, '/') . '-(\d+)$/', $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        return $max;
    }

    /**
     * Ensure the vendor_code_sequences table exists in the database.
     */
    private function ensureSequenceTableExists(): void
    {
        if (! Schema::hasTable('vendor_code_sequences')) {
            Schema::create('vendor_code_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('prefix', 10)->unique();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
            });

            $maxExisting = $this->getCurrentMaxNumber();
            DB::table('vendor_code_sequences')->updateOrInsert(
                ['prefix' => self::PREFIX],
                [
                    'last_number' => $maxExisting,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
