<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->string('theme')->default('minimal');
            $table->string('custom_domain')->nullable();
            $table->boolean('show_instagram')->default(true);
            $table->boolean('show_tiktok')->default(true);
            $table->boolean('show_youtube')->default(true);
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
