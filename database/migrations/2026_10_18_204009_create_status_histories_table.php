<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * command: php artisan migrate --path=backend/database/migrations/2026_10_18_204009_create_status_histories_table.php
     */
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     * command: php artisan migrate:rollaback --path=backend/database/migrations/2026_10_18_204009_create_status_histories_table.php
     */
    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};
