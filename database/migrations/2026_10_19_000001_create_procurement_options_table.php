<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_options', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->string('option_type', 30);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['option_type', 'code']);
            $table->index(['option_type', 'is_active']);
        });

        DB::table('procurement_options')->insert([
            ['code' => 'AIR', 'name' => 'Air', 'option_type' => 'delivery_method', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'SEA', 'name' => 'Sea', 'option_type' => 'delivery_method', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'HAND_CARRY', 'name' => 'Hand carry', 'option_type' => 'delivery_method', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ADVANCE', 'name' => 'Advance payment', 'option_type' => 'payment_term', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'COD', 'name' => 'Cash on delivery', 'option_type' => 'payment_term', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'NET_7', 'name' => '7 days after invoice', 'option_type' => 'payment_term', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'NET_14', 'name' => '14 days after invoice', 'option_type' => 'payment_term', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'NET_30', 'name' => '30 days after invoice', 'option_type' => 'payment_term', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ADVANCE_SPLIT', 'name' => '50% advance, 50% after completion', 'option_type' => 'payment_term', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_options');
    }
};
