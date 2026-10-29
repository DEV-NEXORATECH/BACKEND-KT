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

        $budgetRole = Role::firstOrCreate(['slug' => 'budget-holder'], ['name' => 'Budget Holder']);
        $financeOfficerRole = Role::firstOrCreate(['slug' => 'finance-officer'], ['name' => 'Finance Officer']);
        $financeManagerRole = Role::firstOrCreate(['slug' => 'finance-manager'], ['name' => 'Finance Manager']);
        $boardRole = Role::firstOrCreate(['slug' => 'board-director'], ['name' => 'Board of Executives']);
        $abil = User::query()->where('name', 'like', '%Abil Ahmad Akbar%')->value('id');
        $denny = User::query()->where('name', 'like', '%Denny Bhatara%')->value('id');

        DB::table('approval_matrices')->where('module', 'expense')->delete();
        $now = now();
        DB::table('approval_matrices')->insert([
            ['module' => 'expense', 'level' => 1, 'min_amount' => 0, 'max_amount' => 178000000, 'role_id' => $abil ? null : $budgetRole->id, 'user_id' => $abil, 'approver_title' => 'Campaigns Lead (Abil)', 'description' => 'Project expenses up to USD 10,000 / IDR 178,000,000.', 'is_conditional_project_manager' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['module' => 'expense', 'level' => 1, 'min_amount' => 178000001, 'max_amount' => 267000000, 'role_id' => $denny ? null : $budgetRole->id, 'user_id' => $denny, 'approver_title' => 'Program Manager & Operational (Denny)', 'description' => 'Project expenses above USD 10,000 up to USD 15,000.', 'is_conditional_project_manager' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['module' => 'expense', 'level' => 2, 'min_amount' => 0, 'max_amount' => null, 'role_id' => $financeOfficerRole->id, 'user_id' => null, 'approver_title' => 'Finance Officer', 'description' => 'Finance document and budget review.', 'is_conditional_project_manager' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['module' => 'expense', 'level' => 3, 'min_amount' => 0, 'max_amount' => null, 'role_id' => $financeManagerRole->id, 'user_id' => null, 'approver_title' => 'Finance Manager', 'description' => 'Finance Manager verification.', 'is_conditional_project_manager' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['module' => 'expense', 'level' => 4, 'min_amount' => 267000001, 'max_amount' => null, 'role_id' => $boardRole->id, 'user_id' => null, 'approver_title' => 'Board of Executives', 'description' => 'Expenses above USD 15,000 / IDR 267,000,000.', 'is_conditional_project_manager' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        // Delegation limits are configuration data and are not restored automatically.
    }
};
