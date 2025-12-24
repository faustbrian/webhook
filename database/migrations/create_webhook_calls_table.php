<?php

declare(strict_types=1);

use Cline\Webhook\Enums\PrimaryKeyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $primaryKeyType = PrimaryKeyType::tryFrom(config('webhook.primary_key_type', 'ulid')) ?? PrimaryKeyType::ULID;

        Schema::create('webhook_calls', function (Blueprint $table) use ($primaryKeyType): void {
            match ($primaryKeyType) {
                PrimaryKeyType::ULID => $table->ulid('id')->primary(),
                PrimaryKeyType::UUID => $table->uuid('id')->primary(),
                PrimaryKeyType::ID => $table->id(),
            };
            $table->string('config_name')->index();
            $table->string('webhook_id')->index()->comment('Standard Webhooks ID for idempotency');
            $table->integer('timestamp')->comment('Unix timestamp from webhook-timestamp header');
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('exception')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Unique constraint for idempotency
            $table->unique(['config_name', 'webhook_id']);

            // Index for pruning
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_calls');
    }
};
