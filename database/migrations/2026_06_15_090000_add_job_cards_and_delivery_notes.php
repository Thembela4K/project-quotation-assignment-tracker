<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('job_card_number')->unique();
            $table->string('title');
            $table->string('status')->default('Draft');
            $table->text('scope')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('invoice_ready_at')->nullable();
            $table->boolean('delivery_required')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index(['sales_quotation_id', 'status']);
            $table->index(['due_date', 'status']);
        });

        Schema::create('delivery_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delivery_note_number')->unique();
            $table->string('status')->default('Draft');
            $table->date('delivery_date')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index(['job_card_id', 'status']);
            $table->index(['delivery_date', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('job_card_id')->nullable()->after('sales_quotation_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('job_card_id');
        });

        Schema::dropIfExists('delivery_notes');
        Schema::dropIfExists('job_cards');
    }
};
