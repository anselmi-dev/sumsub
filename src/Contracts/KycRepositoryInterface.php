<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Contracts;

use AnselmiDev\Sumsub\Models\SumsubApplicant;

interface KycRepositoryInterface
{
    /**
     * Find an applicant record by host-app user ID.
     * In SaaS mode the search is automatically scoped to the current tenant.
     */
    public function findByUserId(int|string $userId): ?SumsubApplicant;

    /**
     * Find an applicant record by Sumsub applicant ID.
     * In SaaS mode the search is automatically scoped to the current tenant.
     */
    public function findByApplicantId(string $applicantId): ?SumsubApplicant;

    /**
     * Create a new applicant record.
     * In SaaS mode `tenant_id` is injected automatically if not present in $data.
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
