<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Repositories;

use AnselmiDev\Sumsub\Contracts\KycRepositoryInterface;
use AnselmiDev\Sumsub\Models\SumsubApplicant;
use RuntimeException;

class SumsubApplicantRepository implements KycRepositoryInterface
{
    public function findByUserId(int|string $userId): ?SumsubApplicant
    {
        return SumsubApplicant::query()
            ->where('user_id', $userId)
            ->latest()
            ->first();
    }

    public function findByApplicantId(string $applicantId): ?SumsubApplicant
    {
        return SumsubApplicant::query()
            ->where('applicant_id', $applicantId)
            ->first();
    }

    public function create(array $data): SumsubApplicant
    {
        return SumsubApplicant::create($data);
    }

    public function updateStatus(string $applicantId, array $data): SumsubApplicant
    {
        $applicant = $this->findByApplicantId($applicantId);

        if ($applicant === null) {
            throw new RuntimeException("SumsubApplicant not found for applicant_id: {$applicantId}");
        }

        $applicant->update($data);

        return $applicant->fresh() ?? $applicant;
    }
}
