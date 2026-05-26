<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->index();
            $table->string('provider_invoice_id')->nullable()->unique();
            $table->string('invoice_number')->nullable()->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedInteger('amount_due')->default(0);
            $table->unsignedInteger('amount_paid')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('hosted_url')->nullable();
            $table->string('pdf_url')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

