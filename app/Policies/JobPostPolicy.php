<?php

namespace App\Policies;

use App\Models\JobPost;
use App\Models\User;

class JobPostPolicy
{
    public function update(User $user, JobPost $jobPost): bool
    {
        return $user->id === $jobPost->recruiter_id;
    }

    public function delete(User $user, JobPost $jobPost): bool
    {
        return $user->id === $jobPost->recruiter_id;
    }

    public function viewApplications(User $user, JobPost $jobPost): bool
    {
        return $user->id === $jobPost->recruiter_id;
    }
}
