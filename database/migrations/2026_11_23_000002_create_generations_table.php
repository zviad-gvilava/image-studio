<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money columns hold whole nanodollars (USD x 10^9), the precision Pixelforge bills in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->ulid('id')->primary()->comment('Also sent to Pixelforge as the Idempotency-Key and metadata.generation_id');
            $table->foreignId('user_id')->nullable()->comment('Nulled, not deleted, with the user so cost records survive')->constrained()->nullOnDelete();
            $table->string('provider_id')->nullable()->unique();
            $table->string('status', 20)->index();

            $table->text('prompt');
            $table->string('size', 20);
            $table->string('quality', 20);
            $table->json('reference_images');

            $table->bigInteger('estimated_min_price')->comment('Nanodollars, including markup');
            $table->bigInteger('estimated_max_price')->comment('Nanodollars, including markup; held against the balance while in progress');

            $table->json('rates')->nullable()->comment('USD per 1M tokens, fixed by Pixelforge when it accepted the generation');
            $table->unsignedInteger('input_text_tokens')->nullable();
            $table->unsignedInteger('input_image_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->bigInteger('provider_cost')->nullable()->comment('Nanodollars Pixelforge billed us');
            $table->bigInteger('price')->nullable()->comment('Nanodollars the user was charged');

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable()->comment('Why it failed, or why submission is being retried');
            $table->unsignedSmallInteger('submission_attempts')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index()->comment('Pixelforge time; it bills on this UTC day');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
