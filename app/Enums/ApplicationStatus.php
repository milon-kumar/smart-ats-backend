<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case APPLIED = 'applied';
    case SHORTLISTED = 'shortlisted';
    case INTERVIEW = 'interview';
    case HIRED = 'hired';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::APPLIED => [self::SHORTLISTED, self::REJECTED, self::WITHDRAWN],
            self::SHORTLISTED => [self::INTERVIEW, self::REJECTED],
            self::INTERVIEW => [self::HIRED, self::REJECTED],
            self::HIRED, self::REJECTED, self::WITHDRAWN => [],
        };
    }

    public function canTransitionTo(ApplicationStatus $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }
}
