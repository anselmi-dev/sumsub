<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use AnselmiDev\Sumsub\Contracts\KycRepositoryInterface;
use AnselmiDev\Sumsub\Contracts\SumsubClientInterface;
use AnselmiDev\Sumsub\Events\ApplicantCreated;
use AnselmiDev\Sumsub\Models\SumsubApplicant;

class SumsubService
{
    public function __construct(
        private readonly SumsubClientInterface $client,
        private readonly KycRepositoryInterface $repository,
    ) {}

    /**
     * Create a Sumsub applicant for the given user, or return the existing one.
     */
    public function createApplicant(Authenticatable $user, ?string $levelName = null): SumsubApplicant
    {
        $existing = $this->repository->findByUserId($user->getAuthIdentifier());

        if ($existing !== null) {
            return $existing;
        }

        $levelName ??= config('sumsub.default_level_name');

        $response = $this->client->createApplicant(
            externalUserId: (string) $user->getAuthIdentifier(),
            levelName: $levelName,
        );

        $applicant = $this->repository->create([
            'user_id'       => $user->getAuthIdentifier(),
            'applicant_id'  => $response['id'],
            'level_name'    => $levelName,
            'review_status' => $response['review']['reviewStatus'] ?? null,
            'raw_data'      => $response,
        ]);

        event(new ApplicantCreated($applicant, $user));

        return $applicant;
    }

    /**
     * Generate a short-lived SDK token for the Sumsub Web/Mobile SDK.
     *
     * @return array{token: string, userId: string}
     */
    public function generateSdkToken(Authenticatable $user, ?string $levelName = null): array
    {
        $applicant = $this->createApplicant($user, $levelName);
        $levelName ??= $applicant->level_name;

        $response = $this->client->generateSdkToken(
            applicantId: $applicant->applicant_id,
            levelName: $levelName,
        );

        return [
            'token'  => $response['token'],
            'userId' => $response['userId'] ?? $applicant->applicant_id,
        ];
    }

    /**
     * Retrieve the latest applicant data from Sumsub and sync it locally.
     */
    public function refreshApplicant(SumsubApplicant $applicant): SumsubApplicant
    {
        $response = $this->client->getApplicant($applicant->applicant_id);

        return $this->repository->updateStatus($applicant->applicant_id, [
            'review_status' => $response['review']['reviewStatus'] ?? $applicant->review_status,
            'review_answer' => $response['review']['reviewAnswer'] ?? $applicant->review_answer,
            'raw_data'      => $response,
        ]);
    }
}
