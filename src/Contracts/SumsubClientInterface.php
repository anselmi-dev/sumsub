<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Contracts;

interface SumsubClientInterface
{
    /**
     * Create a new applicant in Sumsub.
     *
     * @param  string  $externalUserId  Unique identifier for the user in the host app
     * @param  string  $levelName  KYC level name configured in Sumsub
     * @return array<string, mixed>  Sumsub applicant payload
     */
    public function createApplicant(string $externalUserId, string $levelName): array;

    /**
     * Retrieve an existing applicant by their Sumsub applicant ID.
     *
     * @return array<string, mixed>
     */
    public function getApplicant(string $applicantId): array;

    /**
     * Generate a short-lived SDK access token for the Sumsub Web/Mobile SDK.
     *
     * @return array<string, mixed>  Contains 'token' and 'userId' keys
     */
    public function generateSdkToken(string $applicantId, string $levelName): array;

    /**
     * Fetch the review result for an applicant.
     *
     * @return array<string, mixed>
     */
    public function getApplicantReview(string $applicantId): array;

    /**
     * Reset the applicant verification so they can re-submit documents.
     */
    public function resetApplicant(string $applicantId): bool;
}
