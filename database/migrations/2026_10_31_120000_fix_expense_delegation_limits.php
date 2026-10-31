<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_matrices')) return;

        $budgetHolder = Role::firstOrCreate(['slug' => 'budget-holder'], ['name' => 'Budget Holder']);
        $financeOfficer = Role::firstOrCreate(['slug' => 'finance-officer'], ['name' => 'Finance Officer']);
        $financeManager = Role::firstOrCreate(['slug' => 'finance-manager'], ['name' => 'Finance Manager']);
        $board = Role::firstOrCreate(['slug' => 'board-director'], ['name' => 'Board of Executives']);

        $abil = User::query()->where('name', 'like', '%Abil Ahmad Akbar%')->with('employee')->first();
        $denny = User::query()->where('name', 'like', '%Denny Bhatara%')->with('employee')->first();
        $winda = User::query()->where('name', 'like', '%Winda Apriyani%')->with('employee')->first();
        $syaifani = User::query()->where('name', 'like', '%Syaifani Auliana Havid%')->with('employee')->first();

        DB::table('approval_matrices')->where('module', 'expense')->delete();
        $now = now();
        $rows = [
            ['level' => 1, 'min_amount' => 0, 'max_amount' => 178000000, 'role_id' => $budgetHolder->id, 'user' => $abil, 'title' => 'Project Budget Holder (Abil Ahmad Akbar)', 'description' => 'Project expenses up to USD 10,000 / IDR 178,000,000.'],
            ['level' => 1, 'min_amount' => 178000000.01, 'max_amount' => null, 'role_id' => $budgetHolder->id, 'user' => $denny, 'title' => 'Program Manager & Operational (Denny Bhatara)', 'description' => 'Project expenses above USD 10,000; Denny remains the budget holder for higher project values.'],
            ['level' => 2, 'min_amount' => 0, 'max_amount' => null, 'role_id' => $financeOfficer->id, 'user' => $winda, 'title' => 'Finance Officer', 'description' => 'Finance review is required after the Budget Holder stage.'],
            ['level' => 3, 'min_amount' => 0, 'max_amount' => null, 'role_id' => $financeManager->id, 'user' => $syaifani, 'title' => 'Finance Manager', 'description' => 'Finance Manager verification is required before completion.'],
            ['level' => 4, 'min_amount' => 267000000.01, 'max_amount' => null, 'role_id' => $board->id, 'user' => null, 'title' => 'Board of Executives', 'description' => 'Expenses above USD 15,000 / IDR 267,000,000 require Board approval.'],
        ];

        foreach ($rows as $row) {
            DB::table('approval_matrices')->insert([
                'module' => 'expense',
                'level' => $row['level'],
                'min_amount' => $row['min_amount'],
                'max_amount' => $row['max_amount'],
                'role_id' => $row['role_id'],
                'user_id' => $row['user']?->id,
                'employee_id' => $row['user']?->employee_id,
                'approver_title' => $row['title'],
                'description' => $row['description'],
                'is_conditional_project_manager' => $row['level'] === 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_matrices')->where('module', 'expense')->delete();
    }
};
