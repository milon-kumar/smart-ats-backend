<?php
namespace App\Jobs;

use App\Models\JobApplication;
use App\Services\ResumeAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeApplicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public JobApplication $application) {}

    public function handle(ResumeAnalyzer $analyzer): void
    {
        $this->application->load('jobPost');

        $result = $analyzer->analyze($this->application);

        if ($result) {
            $this->application->update([
                'ai_score' => $result['score'],
                'ai_matched_skills' => $result['matched_skills'],
                'ai_missing_skills' => $result['missing_skills'],
                'ai_summary' => $result['summary'],
                'ai_analyzed_at' => now(),
            ]);
        }
    }
}