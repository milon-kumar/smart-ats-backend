<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isRecruiter = $request->user() && $request->user()->role === 'recruiter';

        return [
            'id' => $this->id,
            'status' => $this->status,
            'resume_text' => $this->resume_text,
            'resume_original_name' => $this->resume_original_name,
            'cover_letter' => $this->cover_letter,
            'created_at' => $this->created_at,
            'job_post' => $this->whenLoaded('jobPost', function () {
                return [
                    'id' => $this->jobPost->id,
                    'title' => $this->jobPost->title,
                ];
            }),
            'candidate' => $this->when($isRecruiter, function () {
                return $this->whenLoaded('candidate', function () {
                    return [
                        'id' => $this->candidate->id,
                        'name' => $this->candidate->name,
                        'email' => $this->candidate->email,
                    ];
                });
            }),
            'ai_score' => $this->when($isRecruiter, $this->ai_score),
            'keyword_score' => $this->when($isRecruiter, $this->keyword_score),
            'ai_matched_skills' => $this->when($isRecruiter, $this->ai_matched_skills),
            'ai_missing_skills' => $this->when($isRecruiter, $this->ai_missing_skills),
            'ai_summary' => $this->when($isRecruiter, $this->ai_summary),
            'ai_analyzed_at' => $this->when($isRecruiter, $this->ai_analyzed_at),
            'recruiter_note' => $this->when($isRecruiter, $this->recruiter_note),
            'status_histories' => StatusHistoryResource::collection($this->whenLoaded('statusHistories')),
        ];
    }
}
