<?php

namespace Database\Seeders;

use App\Models\Master\ApprovalMatrix;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Default approval chain for every expense document type. */
class ExpenseApprovalChainSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'budget-holder' => 'Budget Holder',
            'finance-officer' => 'Finance Officer',
            'finance-manager' => 'Finance Manager',
            'board-director' => 'Board of Executives',
        ];
        $roleIds = [];
        foreach ($roles as $slug => $name) {
            $roleIds[$slug] = Role::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        // Remove the former amount-tiered expense defaults. Project-specific
        // matrices can still be added later and will take precedence.
        ApprovalMatrix::query()->where('module', 'expense')->delete();

        $abil = User::query()->where('name', 'like', '%Abil Ahmad Akbar%')->first();
        $denny = User::query()->where('name', 'like', '%Denny Bhatara%')->first();
        $chain = [
            ['level' => 1, 'max' => 178000000, 'user' => $abil, 'role' => 'budget-holder', 'title' => 'Campaigns Lead (Abil)', 'description' => 'Project expenses up to USD 10,000 / IDR 178,000,000.'],
            ['level' => 1, 'min' => 178000001, 'max' => 267000000, 'user' => $denny, 'role' => 'budget-holder', 'title' => 'Program Manager & Operational (Denny)', 'description' => 'Project expenses above USD 10,000 up to USD 15,000.'],
            ['level' => 2, 'min' => 0, 'max' => null, 'user' => null, 'role' => 'finance-officer', 'title' => 'Finance Officer', 'description' => 'Finance document and budget review.'],
            ['level' => 3, 'min' => 0, 'max' => null, 'user' => null, 'role' => 'finance-manager', 'title' => 'Finance Manager', 'description' => 'Finance Manager verification.'],
            ['level' => 4, 'min' => 267000001, 'user' => null, 'role' => 'board-director', 'title' => 'Board of Executives', 'description' => 'Expenses above USD 15,000 / IDR 267,000,000.'],
        ];
        foreach ($chain as $stage) {
            ApprovalMatrix::create([
                'module' => 'expense',
                'level' => $stage['level'],
                'min_amount' => $stage['min'] ?? 0,
                'max_amount' => $stage['max'] ?? null,
                'role_id' => $stage['user'] ? null : $roleIds[$stage['role']],
                'user_id' => $stage['user']?->id,
                'employee_id' => $stage['user']?->employee_id,
                'approver_title' => $stage['title'],
                'description' => $stage['description'],
                'is_conditional_project_manager' => false,
                'is_active' => true,
            ]);
        }
    }
}
