<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use AnselmiDev\Sumsub\Contracts\KycRepositoryInterface;
use AnselmiDev\Sumsub\Events\ApplicantReviewed;
use AnselmiDev\Sumsub\Events\ApplicantStatusChanged;

class ProcessSumsubWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  array<string, mixed>  $payload  Validated webhook payload from Sumsub
     */
    public function __construct(
        public readonly array $payload,
    ) {}

    /**
     * Handle the webhook payload and update the applicant status.
     */
    public function handle(KycRepositoryInterface $repository): void
    {
        $applicantId = $this->payload['applicantId'] ?? null;

        if ($applicantId === null) {
            Log::warning('[Sumsub] Webhook missing applicantId', ['payload' => $this->payload]);

            return;
        }

        $applicant = $repository->findByApplicantId($applicantId);

        if ($applicant === null) {
            Log::warning("[Sumsub] No local applicant found for applicantId: {$applicantId}");

            return;
        }

        $reviewStatus = $this->payload['reviewStatus'] ?? null;
        $reviewAnswer = $this->payload['reviewResult']['reviewAnswer'] ?? null;

        $updatedApplicant = $repository->updateStatus($applicantId, [
            'review_status' => $reviewStatus,
            'review_answer' => $reviewAnswer,
            'raw_data'      => $this->payload,
        ]);

        // Event to notify that the applicant status has changed
        event(new ApplicantStatusChanged($updatedApplicant, $this->payload));

        // Check if the review answer is not null and if the review answer is not the same as the previous review answer
        if ($reviewAnswer !== null) {
            event(new ApplicantReviewed($updatedApplicant, $reviewAnswer, $this->payload));
        }
    }
}
