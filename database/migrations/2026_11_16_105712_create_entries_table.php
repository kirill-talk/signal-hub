<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id_hash', 64);
            $table->string('title');
            $table->text('url');
            $table->string('author')->nullable();
            $table->longText('summary')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_bookmarked')->default(false);
            $table->boolean('is_noise')->default(false);
            $table->boolean('is_highlighted')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'external_id_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
