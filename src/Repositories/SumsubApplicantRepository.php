<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Repositories;

use AnselmiDev\Sumsub\Contracts\KycRepositoryInterface;
use AnselmiDev\Sumsub\Models\SumsubApplicant;
use RuntimeException;

class SumsubApplicantRepository implements KycRepositoryInterface
{
    public function __construct(
        private readonly ?string $tenantId = null,
    ) {}

    public function findByUserId(int|string $userId): ?SumsubApplicant
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->latest()
            ->first();
    }

    public function findByApplicantId(string $applicantId): ?SumsubApplicant
    {
        return $this->newQuery()
            ->where('applicant_id', $applicantId)
            ->first();
    }

    public function create(array $data): SumsubApplicant
    {
        if ($this->tenantId !== null && ! isset($data['tenant_id'])) {
            $data['tenant_id'] = $this->tenantId;
        }

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

    /**
     * Returns a query builder scoped to the current tenant when in SaaS mode.
     *
     * @return \Illuminate\Database\Eloquent\Builder<SumsubApplicant>
     */
    private function newQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = SumsubApplicant::query();

        if (config('sumsub.saas_mode') && $this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        }

        return $query;
    }
}
