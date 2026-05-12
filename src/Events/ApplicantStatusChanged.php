<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use AnselmiDev\Sumsub\Models\SumsubApplicant;

class ApplicantStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SumsubApplicant $applicant,
        /** @var array<string, mixed> */
        public readonly array $webhookPayload,
    ) {}
}
