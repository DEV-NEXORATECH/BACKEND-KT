<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('source');
            $table->json('dimensions');
            $table->json('metrics');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('report_definitions')->insert([
            ['slug' => 'expense', 'label' => 'Expense', 'source' => 'expense', 'dimensions' => json_encode([['value' => 'status', 'label' => 'Status'], ['value' => 'type', 'label' => 'Expense Type'], ['value' => 'requester', 'label' => 'Requester'], ['value' => 'date', 'label' => 'Date']]), 'metrics' => json_encode([['value' => 'amount', 'label' => 'Amount']]), 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'project', 'label' => 'Project / Journal', 'source' => 'journal', 'dimensions' => json_encode([['value' => 'project', 'label' => 'Project'], ['value' => 'budget_line', 'label' => 'Budget Line'], ['value' => 'date', 'label' => 'Date']]), 'metrics' => json_encode([['value' => 'debit', 'label' => 'Debit'], ['value' => 'credit', 'label' => 'Credit']]), 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'budget', 'label' => 'Budget Actual', 'source' => 'journal', 'dimensions' => json_encode([['value' => 'project', 'label' => 'Project'], ['value' => 'budget_line', 'label' => 'Budget Line'], ['value' => 'date', 'label' => 'Date']]), 'metrics' => json_encode([['value' => 'debit', 'label' => 'Debit'], ['value' => 'credit', 'label' => 'Credit']]), 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'procurement', 'label' => 'Procurement / AP', 'source' => 'procurement', 'dimensions' => json_encode([['value' => 'status', 'label' => 'Status'], ['value' => 'vendor', 'label' => 'Vendor'], ['value' => 'date', 'label' => 'Date']]), 'metrics' => json_encode([['value' => 'amount', 'label' => 'Total Amount'], ['value' => 'outstanding', 'label' => 'Outstanding']]), 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('report_definitions');
    }
};
