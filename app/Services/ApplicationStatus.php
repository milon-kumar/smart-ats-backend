<?php

namespace App\Services;

use App\Enums\ApplicationStatus as ApplicationStatusEnum;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplicationStatus
{
   public function changeStatus( JobApplication $application, ApplicationStatusEnum $newStatus, User $changedBy, ?string $note = null ): JobApplication {
        if (! $application->status->canTransitionTo($newStatus)) {
            throw new InvalidArgumentException(
                "Cannot transition application status from '{$application->status->value}' to '{$newStatus->value}'."
            );
        }

        return DB::transaction(function () use ($application, $newStatus, $changedBy, $note) {
            $fromStatus = $application->status->value;

            $application->update([
                'status' => $newStatus,
            ]);

            $application->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => $newStatus->value,
                'changed_by' => $changedBy->id,
                'note' => $note,
            ]);

            return $application;
        });
    }
}
