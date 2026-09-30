<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('taxes')->whereIn('code', ['PPh 21 – Staff', 'PPh 21 – Non Staff'])->update(['tax_type' => 'PPH21']);
        DB::table('taxes')->where('code', 'PPh 21 – Staff')->update(['applicable_rule' => 'TER']);
        DB::table('taxes')->where('code', 'PPh 21 – Non Staff')->update(['applicable_rule' => 'Pasal 17 progressive']);
        DB::table('taxes')->where('code', 'PPh 23')->update(['tax_type' => 'PPH23']);
        DB::table('taxes')->where('code', 'PPh 4(2)')->update(['tax_type' => 'PPH_FINAL']);
    }

    public function down(): void {}
};
