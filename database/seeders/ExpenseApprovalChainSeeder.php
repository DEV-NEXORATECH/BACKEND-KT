<?php

namespace Database\Seeders;

use App\Models\Master\ApprovalMatrix;
use App\Models\Role;
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
        ];
        $roleIds = [];
        foreach ($roles as $slug => $name) {
            $roleIds[$slug] = Role::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        // Remove the former amount-tiered expense defaults. Project-specific
        // matrices can still be added later and will take precedence.
        ApprovalMatrix::query()->where('module', 'expense')->delete();

        $chain = [
            ['role' => 'budget-holder', 'title' => 'Budget Holder', 'description' => 'Project budget holder approval.'],
            ['role' => 'finance-officer', 'title' => 'Finance Officer', 'description' => 'Finance document and budget review.'],
            ['role' => 'finance-manager', 'title' => 'Finance Manager', 'description' => 'Finance Manager verification.'],
        ];
        foreach ($chain as $index => $stage) {
            ApprovalMatrix::create([
                'module' => 'expense',
                'level' => $index + 1,
                'min_amount' => 0,
                'max_amount' => null,
                'role_id' => $roleIds[$stage['role']],
                'approver_title' => $stage['title'],
                'description' => $stage['description'],
                'is_conditional_project_manager' => $stage['role'] === 'budget-holder',
                'is_active' => true,
            ]);
        }
    }
}
