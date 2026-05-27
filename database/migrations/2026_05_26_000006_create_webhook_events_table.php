<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('external_event_id');
            $table->string('event_type_raw');
            $table->string('event_type_canonical')->nullable()->index();
            $table->json('payload_json');
            $table->json('headers_json')->nullable();
            $table->timestamp('signature_verified_at')->nullable();
            $table->string('processing_status', 32)->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_event_id']);
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};

