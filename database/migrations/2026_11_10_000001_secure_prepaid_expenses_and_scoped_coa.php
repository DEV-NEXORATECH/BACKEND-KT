<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prepaid_expenses', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('created_by')->constrained('projects')->nullOnDelete();
            $table->index(['created_by', 'project_id']);
        });

        $permissions = [
            ['name' => 'View prepaid expenses', 'slug' => 'accounting.prepaid.view'],
            ['name' => 'Create prepaid expenses', 'slug' => 'accounting.prepaid.create'],
            ['name' => 'Update prepaid expenses', 'slug' => 'accounting.prepaid.update'],
            ['name' => 'Delete prepaid expenses', 'slug' => 'accounting.prepaid.delete'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['slug' => $permission['slug']], [
                'name' => $permission['name'],
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        $viewId = DB::table('permissions')->where('slug', 'accounting.prepaid.view')->value('id');
        $createId = DB::table('permissions')->where('slug', 'accounting.prepaid.create')->value('id');
        $updateId = DB::table('permissions')->where('slug', 'accounting.prepaid.update')->value('id');
        $deleteId = DB::table('permissions')->where('slug', 'accounting.prepaid.delete')->value('id');

        DB::table('roles')->whereIn('slug', ['super-admin', 'admin-system-owner', 'finance-manager', 'finance'])->get(['id'])->each(function ($role) use ($viewId, $createId, $updateId, $deleteId) {
            foreach ([$viewId, $createId, $updateId, $deleteId] as $permissionId) {
                DB::table('role_permission')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $permissionId]);
            }
        });
    }

    public function down(): void
    {
        DB::table('role_permission')->whereIn('permission_id', DB::table('permissions')->whereIn('slug', [
            'accounting.prepaid.view', 'accounting.prepaid.create', 'accounting.prepaid.update', 'accounting.prepaid.delete',
        ])->pluck('id'))->delete();
        DB::table('permissions')->whereIn('slug', [
            'accounting.prepaid.view', 'accounting.prepaid.create', 'accounting.prepaid.update', 'accounting.prepaid.delete',
        ])->delete();
        Schema::table('prepaid_expenses', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['created_by', 'project_id']);
            $table->dropColumn('project_id');
        });
    }
};
