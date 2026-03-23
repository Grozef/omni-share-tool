<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->enum('status', ['draft', 'pending', 'processing', 'published', 'partial', 'failed'])
                  ->default('draft');
            $table->json('platforms');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
