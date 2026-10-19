<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    public function view(User $user, JobApplication $application): bool
    {
        return $user->id === $application->candidate_id 
            || $user->id === $application->jobPost->recruiter_id;
    }

    public function updateStatus(User $user, JobApplication $application): bool
    {
        return $user->id === $application->jobPost->recruiter_id;
    }

    public function withdraw(User $user, JobApplication $application): bool
    {
        return $user->id === $application->candidate_id;
    }

    public function addNote(User $user, JobApplication $application): bool
    {
        return $user->id === $application->jobPost->recruiter_id;
    }
}
