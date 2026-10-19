<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus as ApplicationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobApplication\StoreJobApplicationRequest;
use App\Http\Requests\JobApplication\UpdateApplicationStatusRequest;
use App\Http\Resources\JobApplicationResource;
use App\Models\JobApplication;
use App\Models\JobPost;
use App\Services\ApiResponse;
use App\Services\ApplicationStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use App\Services\ResumeTextExtractor;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\KeywordScorer;
use App\Jobs\AnalyzeApplicationJob;

class JobApplicationController extends Controller
{
    public function __construct(
        protected ApplicationStatus $statusService,
        protected ResumeTextExtractor $textExtractor
    ) {}

    public function store(StoreJobApplicationRequest $request, JobPost $jobPost,KeywordScorer $keywordScorer): JsonResponse
    {
        if ($jobPost->status === 'closed' || $jobPost->deadline < now()->toDateString()) {
            return ApiResponse::error('This job post is no longer accepting applications', 422);
        }

        $user = $request->user();

        if (JobApplication::where('job_post_id', $jobPost->id)->where('candidate_id', $user->id)->exists()) {
            return ApiResponse::error('You have already applied to this job', 409);
        }

        $file = $request->file('resume');

        $extractedText = $this->textExtractor->extractText($file);

        if (mb_strlen($extractedText) < 100) {
            return ApiResponse::error(
                'The text could not be read from this PDF. Please upload a text-based PDF instead of a scanned image.',
                422
            );
        }

        $requiredSkills = is_array($jobPost->required_skills) ? $jobPost->required_skills  : json_decode($jobPost->required_skills, true) ?? [];
        $keywordResult = $keywordScorer->calculate($requiredSkills, $extractedText);
        
        $path = $file->store('resumes');
        $originalName = $file->getClientOriginalName();

        try {
            $application = DB::transaction(function () use ($request, $jobPost, $user, $extractedText, $path, $originalName, $keywordResult) {
                $app = JobApplication::create([
                    'job_post_id' => $jobPost->id,
                    'candidate_id' => $user->id,
                    'resume_path' => $path,
                    'resume_original_name' => $originalName,
                    'resume_text' => $extractedText,
                    'keyword_score' => $keywordResult['score'],
                    // 'matched_skills_keyword' => $keywordResult['matched_skills'],
                    'cover_letter' => $request->validated('cover_letter'),
                    'status' => ApplicationStatusEnum::APPLIED,
                ]);

                $app->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => ApplicationStatusEnum::APPLIED->value,
                    'changed_by' => $user->id,
                ]);

                return $app;
            });

            AnalyzeApplicationJob::dispatch($application)->afterResponse();

            return ApiResponse::success(
                new JobApplicationResource($application->load('jobPost')),
                'Application submitted successfully',
                201
            );
        } catch (UniqueConstraintViolationException $e) {
            // Delete the uploaded file if the DB insert fails
            Storage::delete($path);
            return ApiResponse::error('You have already applied to this job', 409);
        }
    }

    public function myApplications(Request $request): JsonResponse
    {
        $perPage = (int) min($request->query('per_page', 10), 50);

        $applications = $request->user()
            ->jobApplications()
            ->with('jobPost')
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success([
            'items' => JobApplicationResource::collection($applications->items()),
            'pagination' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ], 'Applications retrieved successfully');
    }

    public function indexForRecruiter(Request $request, JobPost $jobPost): JsonResponse
    {
        Gate::authorize('viewApplications', $jobPost);

        $perPage = (int) min($request->query('per_page', 10), 50);

        $applications = JobApplication::query()
            ->where('job_post_id', $jobPost->id)
            ->with('candidate')
            ->when($request->query('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderByRaw('ai_score IS NULL ASC')
            ->orderBy('ai_score', 'desc')
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success([
            'items' => JobApplicationResource::collection($applications->items()),
            'pagination' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ], 'Job post applications retrieved successfully');
    }

    public function show(JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('view', $jobApplication);

        $jobApplication->load([
            'jobPost',
            'candidate',
            'statusHistories.changedBy',
        ]);

        return ApiResponse::success(
            new JobApplicationResource($jobApplication),
            'Application details retrieved successfully'
        );
    }

    public function updateStatus(UpdateApplicationStatusRequest $request, JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('updateStatus', $jobApplication);

        try {
            $newStatus = ApplicationStatusEnum::from($request->validated('status'));

            $updatedApplication = $this->statusService->changeStatus(
                $jobApplication,
                $newStatus,
                $request->user(),
                $request->validated('note')
            );

            return ApiResponse::success(
                new JobApplicationResource($updatedApplication),
                'Application status updated successfully'
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function withdraw(Request $request, JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('withdraw', $jobApplication);

        try {
            $updatedApplication = $this->statusService->changeStatus(
                $jobApplication,
                ApplicationStatusEnum::WITHDRAWN,
                $request->user(),
                'Withdrawn by candidate'
            );

            return ApiResponse::success(
                new JobApplicationResource($updatedApplication),
                'Application withdrawn successfully'
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    public function updateNote(Request $request, JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('addNote', $jobApplication);

        $request->validate([
            'recruiter_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $jobApplication->update([
            'recruiter_note' => $request->input('recruiter_note'),
        ]);

        return ApiResponse::success(
            new JobApplicationResource($jobApplication),
            'Recruiter note updated successfully'
        );
    }

    public function downloadResume(JobApplication $jobApplication): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $jobApplication);

        if (! Storage::exists($jobApplication->resume_path)) {
            return ApiResponse::error('Resume file not found on server.', 404);
        }

        return Storage::download($jobApplication->resume_path, $jobApplication->resume_original_name);
    }

    public function reanalyze(
        JobApplication $jobApplication, 
        KeywordScorer $keywordScorer
    ): JsonResponse {
        Gate::authorize('update', $jobApplication->jobPost);

        $jobPost = $jobApplication->jobPost;

        $requiredSkills = is_array($jobPost->required_skills)  ? $jobPost->required_skills  : json_decode($jobPost->required_skills, true) ?? [];
        $keywordResult = $keywordScorer->calculate($requiredSkills, $jobApplication->resume_text);

        $jobApplication->update([
            'keyword_score' => $keywordResult['score'], 
            // 'matched_skills_keyword' => $keywordResult['matched_skills'],
        ]);

        AnalyzeApplicationJob::dispatch($jobApplication)->afterResponse();

        return ApiResponse::success(
            null,
            'Analysis process started in the background.',
            202
        );
    }
}