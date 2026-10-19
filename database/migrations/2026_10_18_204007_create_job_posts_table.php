<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * command: php artisan migrate --path=backend/database/migrations/2026_10_18_204007_create_job_posts_table.php
     */
    public function up(): void
    {
        Schema::create('job_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruiter_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->json('required_skills');
            $table->unsignedTinyInteger('experience_years')->default(0);
            $table->string('location');
            $table->enum('employment_type', ['full_time', 'part_time', 'remote', 'internship'])->default('full_time');
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->date('deadline');
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     * command: php artisan migrate:rollback --path=backend/database/migrations/2026_10_18_204007_create_job_posts_table.php
     */
    public function down(): void
    {
        Schema::dropIfExists('job_posts');
    }
};
