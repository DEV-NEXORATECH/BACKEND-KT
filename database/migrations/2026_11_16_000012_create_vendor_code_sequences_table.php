<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_code_sequences')) {
            Schema::create('vendor_code_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('prefix', 10)->unique();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
            });
        }

        // Initialize sequence with the maximum existing VND code in vendors table
        if (Schema::hasTable('vendors')) {
            $existingCodes = DB::table('vendors')
                ->whereNotNull('code')
                ->where('code', 'LIKE', 'VND-%')
                ->pluck('code');

            $maxNumber = 0;
            foreach ($existingCodes as $code) {
                if (preg_match('/^VND-(\d+)$/', $code, $matches)) {
                    $num = (int) $matches[1];
                    if ($num > $maxNumber) {
                        $maxNumber = $num;
                    }
                }
            }

            DB::table('vendor_code_sequences')->updateOrInsert(
                ['prefix' => 'VND'],
                [
                    'last_number' => $maxNumber,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            // Safe idempotent backfill: If any existing vendor is missing a code, assign one
            $vendorsWithoutCode = DB::table('vendors')
                ->where(function ($query) {
                    $query->whereNull('code')->orWhere('code', '');
                })
                ->orderBy('id')
                ->get();

            $currentNumber = $maxNumber;
            foreach ($vendorsWithoutCode as $vendor) {
                do {
                    $currentNumber++;
                    $candidate = sprintf('VND-%06d', $currentNumber);
                } while (DB::table('vendors')->where('code', $candidate)->exists());

                DB::table('vendors')->where('id', $vendor->id)->update([
                    'code' => $candidate,
                    'updated_at' => now(),
                ]);
            }

            if ($currentNumber > $maxNumber) {
                DB::table('vendor_code_sequences')->where('prefix', 'VND')->update([
                    'last_number' => $currentNumber,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_code_sequences');
    }
};
