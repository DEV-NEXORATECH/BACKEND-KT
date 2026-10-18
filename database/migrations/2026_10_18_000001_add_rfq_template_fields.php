<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->date('required_delivery_date')->nullable()->after('submission_deadline');
            $table->string('delivery_method', 30)->nullable()->after('required_delivery_date');
            $table->string('payment_terms', 120)->nullable()->after('delivery_method');
            $table->foreignId('supplier_vendor_id')->nullable()->after('payment_terms')->constrained('vendors')->nullOnDelete();
            $table->string('supplier_contact_name', 120)->nullable()->after('supplier_vendor_id');
            $table->string('supplier_email', 160)->nullable()->after('supplier_contact_name');
            $table->string('supplier_phone', 50)->nullable()->after('supplier_email');
            $table->string('supplier_fax', 50)->nullable()->after('supplier_phone');
            $table->string('supplier_mobile', 50)->nullable()->after('supplier_fax');
            $table->text('supplier_address')->nullable()->after('supplier_mobile');
            $table->string('procurement_contact_name', 120)->nullable()->after('supplier_vendor_id');
            $table->string('procurement_contact_email', 160)->nullable()->after('procurement_contact_name');
            $table->text('procurement_address')->nullable()->after('procurement_contact_email');
            $table->decimal('other_tax', 18, 2)->default(0)->after('procurement_address');
            $table->decimal('other_charges', 18, 2)->default(0)->after('other_tax');
            $table->string('checked_by', 120)->nullable()->after('other_charges');
            $table->string('budget_holder_name', 120)->nullable()->after('checked_by');
            $table->string('budget_holder_title', 120)->nullable()->after('budget_holder_name');
            $table->string('supplier_acceptance_name', 120)->nullable()->after('budget_holder_title');
            $table->string('supplier_acceptance_title', 120)->nullable()->after('supplier_acceptance_name');
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_vendor_id');
            $table->dropColumn([
                'required_delivery_date', 'delivery_method', 'payment_terms',
                'supplier_contact_name', 'supplier_email', 'supplier_phone', 'supplier_fax', 'supplier_mobile', 'supplier_address',
                'procurement_contact_name', 'procurement_contact_email', 'procurement_address',
                'other_tax', 'other_charges', 'checked_by', 'budget_holder_name',
                'budget_holder_title', 'supplier_acceptance_name', 'supplier_acceptance_title',
            ]);
        });
    }
};
