<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->string('platform_post_id')->nullable();
            $table->string('platform_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['publication_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_results');
    }
};
