<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\JobPost;
use App\Services\ApiResponse;
use App\Http\Resources\JobPostResource;
use App\Http\Requests\JobPost\StoreJobPostRequest;
use App\Http\Requests\JobPost\UpdateJobPostRequest;
use Illuminate\Support\Facades\Gate;

class JobPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) min($request->query('per_page', 10), 50);

        $jobPosts = JobPost::query()
            ->with('recruiter')
            ->where('status', 'open')
            ->where('deadline', '>=', now()->toDateString())
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->query('location'), function ($query, $location) {
                $query->where('location', $location);
            })
            ->when($request->query('employment_type'), function ($query, $type) {
                $query->where('employment_type', $type);
            })
            ->when($request->query('max_experience'), function ($query, $maxExp) {
                $query->where('experience_years', '<=', $maxExp);
            })
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success([
            'items' => JobPostResource::collection($jobPosts->items()),
            'pagination' => [
                'current_page' => $jobPosts->currentPage(),
                'last_page' => $jobPosts->lastPage(),
                'per_page' => $jobPosts->perPage(),
                'total' => $jobPosts->total(),
            ],
        ], 'Job posts retrieved successfully');
    }

    public function show(JobPost $jobPost): JsonResponse
    {
        $jobPost->load('recruiter');

        return ApiResponse::success(new JobPostResource($jobPost), 'Job post details retrieved');
    }

    public function store(StoreJobPostRequest $request): JsonResponse
    {
        $jobPost = $request->user()->jobPosts()->create($request->validated());
        $jobPost->load('recruiter');

        return ApiResponse::success(new JobPostResource($jobPost), 'Job post created successfully', 201);
    }

    public function update(UpdateJobPostRequest $request, JobPost $jobPost): JsonResponse
    {
        Gate::authorize('update', $jobPost);

        $jobPost->update($request->validated());
        $jobPost->load('recruiter');

        return ApiResponse::success(new JobPostResource($jobPost), 'Job post updated successfully');
    }

    public function close(Request $request, JobPost $jobPost): JsonResponse
    {
        Gate::authorize('update', $jobPost);

        $jobPost->update(['status' => 'closed']);
        $jobPost->load('recruiter');

        return ApiResponse::success(new JobPostResource($jobPost), 'Job post closed successfully');
    }   


    public function updateStatus(Request $request, JobPost $jobPost): JsonResponse
    {
        Gate::authorize('update', $jobPost);

        $validated = $request->validate([
            'status' => 'required|in:open,closed',
        ]);

        $jobPost->update([
            'status' => $validated['status'],
        ]);

        return ApiResponse::success(
            new JobPostResource($jobPost),
            'Job post status updated successfully'
        );
    }

    public function destroy(JobPost $jobPost): JsonResponse
    {
        Gate::authorize('delete', $jobPost);

        if ($jobPost->jobApplications()->exists()) {
            return ApiResponse::error('এই পোস্টে আবেদন আছে, ডিলিট না করে বন্ধ করুন', 409);
        }

        $jobPost->delete();

        return response()->json(null, 204);
    }

    public function myPosts(Request $request): JsonResponse
    {
        $perPage = (int) min($request->query('per_page', 10), 50);

        $jobPosts = $request->user()
            ->jobPosts()
            ->withCount('jobApplications')
            ->latest()
            ->paginate($perPage);

        return ApiResponse::success([
            'items' => JobPostResource::collection($jobPosts->items()),
            'pagination' => [
                'current_page' => $jobPosts->currentPage(),
                'last_page' => $jobPosts->lastPage(),
                'per_page' => $jobPosts->perPage(),
                'total' => $jobPosts->total(),
            ],
        ], 'My job posts retrieved successfully');
    }
}
