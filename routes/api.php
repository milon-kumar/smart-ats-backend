<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::get('/job-posts', [JobPostController::class, 'index']);
Route::get('/job-posts/{jobPost}', [JobPostController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/applications/{jobApplication}', [JobApplicationController::class, 'show']);
    Route::get('/applications/{jobApplication}/resume', [JobApplicationController::class, 'downloadResume']);
    
    Route::middleware('role:candidate')->group(function () {
        Route::post('/job-posts/{jobPost}/applications', [JobApplicationController::class, 'store']);
        Route::get('/me/applications', [JobApplicationController::class, 'myApplications']);
        Route::patch('/applications/{jobApplication}/withdraw', [JobApplicationController::class, 'withdraw']);
    });

    Route::middleware('role:recruiter')->group(function () {
        Route::get('/recruiter/dashboard', [DashboardController::class, 'index']);
        Route::post('/recruiter/job-posts', [JobPostController::class, 'store']);
        Route::put('/recruiter/job-posts/{jobPost}', [JobPostController::class, 'update']);
        Route::patch('/recruiter/job-posts/{jobPost}/close', [JobPostController::class, 'close']);
        Route::patch('/recruiter/job-posts/{jobPost}/status', [JobPostController::class, 'updateStatus']);
        Route::delete('/recruiter/job-posts/{jobPost}', [JobPostController::class, 'destroy']);
        Route::get('/recruiter/job-posts', [JobPostController::class, 'myPosts']);

        Route::get('/recruiter/job-posts/{jobPost}/applications', [JobApplicationController::class, 'indexForRecruiter']);
        Route::patch('/recruiter/applications/{jobApplication}/status', [JobApplicationController::class, 'updateStatus']);
        Route::patch('/recruiter/applications/{jobApplication}/note', [JobApplicationController::class, 'updateNote']);
        Route::get('/recruiter/applications/{jobApplication}',[JobApplicationController::class,'show']);

        Route::post('/recruiter/applications/{jobApplication}/analyze', [JobApplicationController::class, 'reanalyze'])
        ->middleware('throttle:5,1');
    });

});