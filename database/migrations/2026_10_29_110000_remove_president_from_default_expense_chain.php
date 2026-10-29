<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_matrices')) return;

        $presidentRoleId = Role::query()->where('slug', 'president')->value('id');
        $query = DB::table('approval_matrices')
            ->where('module', 'expense')
            ->where(function ($q) use ($presidentRoleId) {
                $q->where('approver_title', 'President');
                if ($presidentRoleId) $q->orWhere('role_id', $presidentRoleId);
            });

        // Keep a President row only when explicitly configured as level 1
        // Budget Holder; remove the former mandatory final approval stage.
        $query->where('level', '>', 1)->delete();
    }

    public function down(): void
    {
        // The default chain is recreated by ExpenseApprovalChainSeeder.
    }
};
