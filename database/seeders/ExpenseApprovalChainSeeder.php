<?php

namespace Database\Seeders;

use App\Models\Master\ApprovalMatrix;
use App\Models\Master\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Default approval chain for every expense document type.
 *
 * The matrix is deliberately project-scoped so the same request can never
 * accidentally inherit a different project's approvers. The UI only reads
 * this matrix; requesters cannot edit or choose these people.
 */
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

        // Rebuild the expense chain from one authoritative three-step flow.
        // This also removes legacy threshold/board rows that caused the PWA,
        // frontend and backend to show different approval chains.
        ApprovalMatrix::query()->where('module', 'expense')->delete();

        $abil = User::query()->where('name', 'like', '%Abil Ahmad Akbar%')->first();
        $denny = User::query()->where('name', 'like', '%Denny Bhatara%')->first();
        $winda = User::query()->where('name', 'like', '%Winda Apriyani%')->first();
        $syaifani = User::query()->where('name', 'like', '%Syaifani Auliana Havid%')->first();

        $create = function (array $stage, ?int $projectId = null) use ($roleIds): void {
            ApprovalMatrix::create([
                'module' => 'expense',
                'project_id' => $projectId,
                'level' => $stage['level'],
                'min_amount' => $stage['min_amount'] ?? 0,
                'max_amount' => $stage['max_amount'] ?? null,
                'role_id' => $stage['user'] ? null : $roleIds[$stage['role']],
                'user_id' => $stage['user']?->id,
                'employee_id' => $stage['user']?->employee_id,
                'approver_title' => $stage['title'],
                'description' => $stage['description'],
                'is_conditional_project_manager' => false,
                'is_active' => true,
            ]);
        };

        $tenK = 178000000;
        $fifteenK = 267000000;
        $financeStages = [
            ['level' => 2, 'user' => $winda, 'role' => 'finance-officer', 'title' => 'Finance Officer', 'description' => 'Finance review after Budget Holder approval.'],
            ['level' => 3, 'user' => $syaifani, 'role' => 'finance-manager', 'title' => 'Finance Manager', 'description' => 'Finance Manager verification after Finance Officer review.'],
        ];

        // Every project gets the same default chain until project-level
        // configuration is introduced. The project_id makes the relation
        // explicit and lets the backend select the correct chain.
        foreach (Project::query()->pluck('id') as $projectId) {
            $stages = [
                ['level' => 1, 'user' => $abil, 'role' => 'budget-holder', 'min_amount' => 0, 'max_amount' => $tenK, 'title' => 'Project Budget Holder (Abil Ahmad Akbar)', 'description' => 'Project expenses up to USD 10,000.'],
                ['level' => 1, 'user' => $denny, 'role' => 'budget-holder', 'min_amount' => $tenK + 0.01, 'max_amount' => $fifteenK, 'title' => 'Program Manager & Operational (Denny Bhatara)', 'description' => 'Project expenses above USD 10,000 up to USD 15,000.'],
                ...$financeStages,
                ['level' => 4, 'user' => null, 'role' => 'board-director', 'min_amount' => $fifteenK + 0.01, 'title' => 'Board of Executives', 'description' => 'Project expenses above USD 15,000.'],
            ];
            foreach ($stages as $stage) {
                $create($stage, (int) $projectId);
            }
        }

        // Requests without a project (for example a future entity/internal
        // expense) still follow the same controlled chain. Project-specific
        // rows take precedence whenever a project is present.
        $internalStages = [
            ['level' => 1, 'user' => $denny, 'role' => 'budget-holder', 'min_amount' => 0, 'max_amount' => $fifteenK, 'title' => 'Program Manager & Operational (Denny Bhatara)', 'description' => 'Entity/internal KT expenses up to USD 15,000.'],
            ...$financeStages,
            ['level' => 4, 'user' => null, 'role' => 'board-director', 'min_amount' => $fifteenK + 0.01, 'title' => 'Board of Executives', 'description' => 'Entity/internal KT expenses above USD 15,000.'],
        ];
        foreach ($internalStages as $stage) {
            $create($stage);
        }
    }
}
