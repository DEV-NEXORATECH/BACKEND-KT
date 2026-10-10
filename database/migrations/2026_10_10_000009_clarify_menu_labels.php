<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $labels = [
            'master-budget-templates' => 'Budget Categories & Templates',
            'master-expense-types' => 'Document Types',
            'donor-grant-grants' => 'Grant Agreements',
            'donor-grant-budget' => 'Grant / Project Budget',
            'procurement-contracts' => 'Procurement Contracts',
            'admin-documents' => 'Document Repository',
            'admin-contracts' => 'Internal / Legal Contracts',
        ];

        foreach ($labels as $slug => $title) {
            DB::table('menus')->where('slug', $slug)->update(['title' => $title, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $labels = [
            'master-budget-templates' => 'Budget Templates / Lines',
            'master-expense-types' => 'Document / Expense Types',
            'donor-grant-grants' => 'Grants',
            'donor-grant-budget' => 'Budget',
            'procurement-contracts' => 'Contracts',
            'admin-documents' => 'Documents',
            'admin-contracts' => 'Contracts',
        ];

        foreach ($labels as $slug => $title) {
            DB::table('menus')->where('slug', $slug)->update(['title' => $title, 'updated_at' => now()]);
        }
    }
};
