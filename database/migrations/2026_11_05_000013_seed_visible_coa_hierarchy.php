<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        $now = now();
        $parentId = DB::table('chart_of_accounts')->where('code', '9000')->value('id');
        $parentData = [
            'code' => '9000',
            'name' => 'Header Account - Program Costs',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'level' => 1,
            'is_header' => true,
            'parent_id' => null,
            'is_active' => true,
            'updated_at' => $now,
        ];
        if ($parentId) {
            DB::table('chart_of_accounts')->where('id', $parentId)->update($parentData);
        } else {
            $parentData['created_at'] = $now;
            $parentId = DB::table('chart_of_accounts')->insertGetId($parentData);
        }

        foreach ([
            ['code' => '9001', 'name' => 'Child Account 1 - Field Activities'],
            ['code' => '9002', 'name' => 'Child Account 2 - Workshop Activities'],
        ] as $child) {
            $data = [
                ...$child,
                'account_type' => 'expense',
                'normal_balance' => 'debit',
                'level' => 2,
                'is_header' => false,
                'parent_id' => $parentId,
                'is_active' => true,
                'updated_at' => $now,
            ];
            $childId = DB::table('chart_of_accounts')->where('code', $child['code'])->value('id');
            if ($childId) {
                DB::table('chart_of_accounts')->where('id', $childId)->update($data);
            } else {
                $data['created_at'] = $now;
                DB::table('chart_of_accounts')->insert($data);
            }
        }
    }

    public function down(): void
    {
        DB::table('chart_of_accounts')->whereIn('code', ['9001', '9002', '9000'])->delete();
    }
};
