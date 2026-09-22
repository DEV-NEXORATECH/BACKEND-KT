<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_chart_types', function (Blueprint $table) {
            $table->id();
            $table->string('value')->unique();
            $table->string('label');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        $now = now();
        DB::table('report_chart_types')->insert([
            ['value' => 'bar', 'label' => 'Bar Chart', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['value' => 'donut', 'label' => 'Donut Chart', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['value' => 'line', 'label' => 'Line Chart', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['value' => 'table', 'label' => 'Table', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('report_chart_types');
    }
};
