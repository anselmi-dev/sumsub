<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Console;

use AnselmiDev\Sumsub\Jobs\ProcessSumsubWebhook;
use AnselmiDev\Sumsub\Models\SumsubApplicant;
use Illuminate\Console\Command;

class SimulateWebhookCommand extends Command
{
    protected $signature = 'sumsub:simulate-webhook
                            {applicantId? : Sumsub applicant ID (leave empty to pick from DB)}
                            {--answer=GREEN : Review answer: GREEN, RED or RETRY}
                            {--status=completed : Review status sent in the payload}
                            {--sync : Dispatch the job synchronously instead of queuing it}';

    protected $description = '[DEV] Simulate a Sumsub reviewComplete webhook for local testing';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This command cannot be run in production.');

            return self::FAILURE;
        }

        $applicantId = $this->argument('applicantId') ?? $this->pickApplicantId();

        if ($applicantId === null) {
            $this->error('No applicant ID provided and no records found in sumsub_applicants.');

            return self::FAILURE;
        }

        $answer = strtoupper((string) $this->option('answer'));
        $status = (string) $this->option('status');

        if (! in_array($answer, ['GREEN', 'RED', 'RETRY'], true)) {
            $this->error("Invalid --answer value: {$answer}. Allowed: GREEN, RED, RETRY");

            return self::FAILURE;
        }

        $payload = [
            'type'          => 'applicantReviewed',
            'applicantId'   => $applicantId,
            'reviewStatus'  => $status,
            'reviewResult'  => [
                'reviewAnswer'       => $answer,
                'rejectLabels'       => [],
                'reviewRejectType'   => null,
            ],
        ];

        $this->info("Simulating Sumsub webhook:");
        $this->table(
            ['Field', 'Value'],
            [
                ['applicantId',  $applicantId],
                ['reviewStatus', $status],
                ['reviewAnswer', $answer],
                ['sync',         $this->option('sync') ? 'yes' : 'no (queued)'],
            ]
        );

        $job = new ProcessSumsubWebhook($payload);

        if ($this->option('sync')) {
            dispatch_sync($job);
            $this->info('✓ Job processed synchronously.');
        } else {
            dispatch($job);
            $this->info('✓ Job dispatched to queue.');
        }

        return self::SUCCESS;
    }

    private function pickApplicantId(): ?string
    {
        $applicants = SumsubApplicant::query()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'applicant_id', 'user_id', 'review_status', 'review_answer']);

        if ($applicants->isEmpty()) {
            return null;
        }

        $choices = $applicants->map(fn ($a) => sprintf(
            '%s  (user_id=%s  status=%s  answer=%s)',
            $a->applicant_id,
            $a->user_id,
            $a->review_status ?? 'null',
            $a->review_answer ?? 'null',
        ))->all();

        $selected = $this->choice('Select an applicant from the database:', $choices, 0);

        // Extract the applicant_id (first word before the spaces)
        return explode(' ', trim($selected))[0];
    }
}
