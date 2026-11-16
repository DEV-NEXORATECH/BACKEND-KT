<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('supplier_company_name', 180)->nullable();
            $table->string('delivery_contact_name', 120)->nullable();
            $table->string('delivery_contact_email', 160)->nullable();
            $table->string('checked_by_title', 120)->nullable();
        });
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->string('budget_code', 100)->nullable();
            $table->string('budget_line', 255)->nullable();
            $table->unsignedInteger('pr_line_item_no')->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('currency', 10)->default('IDR');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['budget_code', 'budget_line', 'pr_line_item_no', 'unit', 'currency']);
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['supplier_company_name', 'delivery_contact_name', 'delivery_contact_email', 'checked_by_title']);
        });
    }
};
