<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('supplier_contract_notifications', function (Blueprint $table) { $table->id(); $table->string('scn_number', 40)->unique(); $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete(); $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete(); $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete(); $table->date('notification_date'); $table->string('subject', 200); $table->text('notes')->nullable(); $table->enum('status', ['draft', 'issued', 'acknowledged', 'cancelled'])->default('draft'); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('issued_at')->nullable(); $table->timestamps(); }); }
    public function down(): void { Schema::dropIfExists('supplier_contract_notifications'); }
};
