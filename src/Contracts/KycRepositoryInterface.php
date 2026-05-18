<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Contracts;

use AnselmiDev\Sumsub\Models\SumsubApplicant;

interface KycRepositoryInterface
{
    /**
     * Find an applicant record by host-app user ID.
     */
    public function findByUserId(int|string $userId): ?SumsubApplicant;

    /**
     * Find an applicant record by Sumsub applicant ID.
     */
    public function findByApplicantId(string $applicantId): ?SumsubApplicant;

    /**
     * Create a new applicant record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SumsubApplicant;

    /**
     * Update the review status and raw webhook payload for an applicant.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateStatus(string $applicantId, array $data): SumsubApplicant;
}
