<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * command: php artisan migrate --path=backend/database/migrations/2026_10_18_204008_create_job_applications_table.php
     */
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_post_id')->constrained('job_posts')->restrictOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->cascadeOnDelete();
            $table->longText('resume_text');
            $table->string('resume_path');
            $table->string('resume_original_name');
            $table->text('cover_letter')->nullable();
            $table->enum('status', ['applied', 'shortlisted', 'interview', 'hired', 'rejected', 'withdrawn'])->default('applied')->index();
            $table->unsignedTinyInteger('keyword_score')->nullable();
            $table->unsignedTinyInteger('ai_score')->nullable();
            $table->json('ai_matched_skills')->nullable();
            $table->json('ai_missing_skills')->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestamp('ai_analyzed_at')->nullable();
            $table->text('recruiter_note')->nullable();
            $table->timestamps();

            $table->unique(['job_post_id', 'candidate_id']);
        });
    }

    /**
     * Reverse the migrations.
     * command: php artisan migrate:rollback --path=backend/database/migrations/2026_10_18_204008_create_job_applications_table.php
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
