<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use AnselmiDev\Sumsub\Models\SumsubApplicant;

/**
 * Fired when a review result (GREEN/RED/RETRY) is received via webhook.
 * Host-app listeners should subscribe to this event to update their own
 * KYC status (e.g. KycDocumentStatus, User.kyc_verified_at, etc.).
 */
class ApplicantReviewed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SumsubApplicant $applicant,
        /** 'GREEN' | 'RED' | 'RETRY' */
        public readonly string $reviewAnswer,
        /** @var array<string, mixed> */
        public readonly array $webhookPayload,
    ) {}

    public function isApproved(): bool
    {
        return $this->reviewAnswer === 'GREEN';
    }

    public function isRejected(): bool
    {
        return $this->reviewAnswer === 'RED';
    }

    public function needsRetry(): bool
    {
        return $this->reviewAnswer === 'RETRY';
    }
}
