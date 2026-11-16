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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('adapter_type')->default('rss'); // rss, github, json
            $table->unsignedInteger('fetch_interval_minutes')->default(60);
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('etag')->nullable();
            $table->string('last_modified')->nullable();
            $table->string('status')->default('active'); // active, failing
            $table->unsignedInteger('error_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
