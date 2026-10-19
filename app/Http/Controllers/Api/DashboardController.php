<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobPost;
use App\Models\StatusHistory;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $recruiterJobPostIds = JobPost::where('recruiter_id', $user->id)->pluck('id');

        if ($recruiterJobPostIds->isEmpty()) {
            return ApiResponse::success([
                'has_posts' => false,
                'stats' => [
                    'open_posts' => 0,
                    'total_applications' => 0,
                    'pending_review' => 0,
                    'new_this_week' => 0,
                    'new_last_week' => 0,
                    'avg_ai_score' => 0,
                    'analyzed_count' => 0,
                    'posts_closing_soon' => 0,
                ],
                'pipeline' => [],
                'top_candidates' => [],
                'skill_gaps' => [],
                'job_posts' => [],
                'recent_activity' => [],
            ], 'Dashboard stats retrieved successfully');
        }

        $openPostsCount = JobPost::where('recruiter_id', $user->id)
            ->where('status', 'active')
            ->count();

        $postsClosingSoon = JobPost::where('recruiter_id', $user->id)
            ->where('status', 'active')
            ->whereBetween('deadline', [Carbon::now(), Carbon::now()->addDays(3)])
            ->count();

        $appQuery = JobApplication::whereIn('job_post_id', $recruiterJobPostIds);

        $totalApplications = (clone $appQuery)->count();
        $pendingReview = (clone $appQuery)->where('status', 'applied')->count();

        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfLastWeek = Carbon::now()->subWeek()->startOfWeek();
        $endOfLastWeek = Carbon::now()->subWeek()->endOfWeek();

        $newThisWeek = (clone $appQuery)->where('created_at', '>=', $startOfWeek)->count();
        $newLastWeek = (clone $appQuery)->whereBetween('created_at', [$startOfLastWeek, $endOfLastWeek])->count();

        $avgAiScoreQuery = (clone $appQuery)->whereNotNull('ai_score');
        $analyzedCount = (clone $avgAiScoreQuery)->count();
        $avgAiScore = $analyzedCount > 0 ? (int) round((clone $avgAiScoreQuery)->avg('ai_score')) : 0;

        $pipelineData = JobApplication::whereIn('job_post_id', $recruiterJobPostIds)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $pipeline = [
            'applied' => $pipelineData['applied'] ?? 0,
            'shortlisted' => $pipelineData['shortlisted'] ?? 0,
            'interview' => $pipelineData['interview'] ?? 0,
            'hired' => $pipelineData['hired'] ?? 0,
            'rejected' => $pipelineData['rejected'] ?? 0,
            'withdrawn' => $pipelineData['withdrawn'] ?? 0,
        ];

        $topCandidates = JobApplication::whereIn('job_post_id', $recruiterJobPostIds)
            ->where('status', 'applied')
            ->whereNotNull('ai_score')
            ->with(['candidate:id,name,email', 'jobPost:id,title'])
            ->orderByDesc('ai_score')
            ->take(5)
            ->get()
            ->map(function ($app) {
                return [
                    'id' => $app->id,
                    'candidate_name' => $app->candidate?->name ?? 'Candidate',
                    'job_title' => $app->jobPost?->title ?? 'N/A',
                    'ai_score' => $app->ai_score,
                ];
            });

        $missingSkillsCollection = JobApplication::whereIn('job_post_id', $recruiterJobPostIds)
            ->whereNotNull('ai_missing_skills')
            ->pluck('ai_missing_skills');

        $skillGaps = $missingSkillsCollection
            ->flatten()
            ->map(fn ($skill) => strtolower(trim($skill)))
            ->reject(fn ($skill) => empty($skill))
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->map(fn ($count, $skill) => ['skill' => ucfirst($skill), 'count' => $count])
            ->values();

        $jobPostsSummary = JobPost::where('recruiter_id', $user->id)
            ->withCount([
                'jobApplications',
                'jobApplications as shortlisted_count' => function ($q) {
                    $q->where('status', 'shortlisted');
                },
            ])
            ->withAvg('jobApplications as avg_ai_score', 'ai_score')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(function ($post) {
                $daysRemaining = Carbon::now()->diffInDays(Carbon::parse($post->deadline), false);

                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'status' => $post->status,
                    'applications_count' => $post->job_applications_count,
                    'shortlisted_count' => $post->shortlisted_count,
                    'avg_ai_score' => $post->avg_ai_score ? (int) round($post->avg_ai_score) : null,
                    'deadline' => $post->deadline,
                    'days_remaining' => (int) $daysRemaining,
                ];
            });

        $recentActivity = StatusHistory::whereHas('jobApplication', function ($q) use ($recruiterJobPostIds) {
            $q->whereIn('job_post_id', $recruiterJobPostIds);
        })
            ->with([
                'changedBy:id,name',
                'jobApplication.candidate:id,name',
                'jobApplication.jobPost:id,title',
            ])
            ->orderByDesc('created_at')
            ->take(8)
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'candidate_name' => $history->jobApplications?->candidate?->name ?? 'Candidate',
                    'job_title' => $history->jobApplications?->jobPost?->title ?? 'Job Post',
                    'from_status' => $history->from_status,
                    'to_status' => $history->to_status,
                    'changed_by' => $history->changedBy?->name ?? 'System',
                    'created_at' => $history->created_at->diffForHumans(),
                ];
            });

        return ApiResponse::success([
            'has_posts' => true,
            'stats' => [
                'open_posts' => $openPostsCount,
                'total_applications' => $totalApplications,
                'pending_review' => $pendingReview,
                'new_this_week' => $newThisWeek,
                'new_last_week' => $newLastWeek,
                'avg_ai_score' => $avgAiScore,
                'analyzed_count' => $analyzedCount,
                'posts_closing_soon' => $postsClosingSoon,
            ],
            'pipeline' => $pipeline,
            'top_candidates' => $topCandidates,
            'skill_gaps' => $skillGaps,
            'job_posts' => $jobPostsSummary,
            'recent_activity' => $recentActivity,
        ], 'Dashboard data retrieved successfully');
    }
}