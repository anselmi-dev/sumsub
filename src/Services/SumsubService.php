<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use AnselmiDev\Sumsub\Contracts\KycRepositoryInterface;
use AnselmiDev\Sumsub\Contracts\SumsubClientInterface;
use AnselmiDev\Sumsub\Events\ApplicantCreated;
use AnselmiDev\Sumsub\Http\Client\SumsubClient;
use AnselmiDev\Sumsub\Models\SumsubApplicant;
use AnselmiDev\Sumsub\Repositories\SumsubApplicantRepository;

class SumsubService
{
    public function __construct(
        private readonly SumsubClientInterface $client,
        private readonly KycRepositoryInterface $repository,
        private readonly ?string $tenantId = null,
    ) {}

    /**
     * Return a new instance scoped to a specific tenant with its own Sumsub credentials.
     *
     * Use this in SaaS mode when each tenant has its own Sumsub project.
     * If all tenants share one Sumsub project, pass the global app_token / secret_key
     * and only use $tenantId for data isolation.
     *
     * @example
     *   Sumsub::forTenant(
     *       tenantId:  $tenant->id,
     *       appToken:  $tenant->sumsub_app_token,   // per-tenant Sumsub credentials
     *       secretKey: $tenant->sumsub_secret_key,
     *   )->createApplicant($user);
     *
     * @example (shared Sumsub project, data isolation only)
     *   Sumsub::forTenant(tenantId: $tenant->id)->createApplicant($user);
     */
    public function forTenant(
        string $tenantId,
        ?string $appToken = null,
        ?string $secretKey = null,
    ): static {
        $client = ($appToken !== null && $secretKey !== null)
            ? new SumsubClient(
                appToken: $appToken,
                secretKey: $secretKey,
                baseUrl: config('sumsub.base_url'),
            )
            : $this->client;

        $repository = new SumsubApplicantRepository(tenantId: $tenantId);

        return new static($client, $repository, $tenantId);
    }

    /**
     * Create a Sumsub applicant for the given user, or return the existing one.
     *
     * Handles the case where the local DB record is missing but Sumsub already
     * has an applicant for this externalUserId (409 Conflict). In that scenario
     * the existing applicant is fetched from Sumsub and re-saved locally.
     */
    public function createApplicant(Authenticatable $user, ?string $levelName = null): SumsubApplicant
    {
        $existing = $this->repository->findByUserId($user->getAuthIdentifier());

        if ($existing !== null) {
            return $existing;
        }

        $levelName       ??= config('sumsub.default_level_name');
        $externalUserId    = $this->buildExternalUserId($user);

        try {
            $response = $this->client->createApplicant(
                externalUserId: $externalUserId,
                levelName: $levelName,
            );
        } catch (\RuntimeException $e) {
            // 409 → Sumsub already has this externalUserId but our DB doesn't.
            // Recover by fetching the existing applicant and re-syncing locally.
            if ($e->getCode() !== 409) {
                throw $e;
            }

            $response = $this->client->getApplicantByExternalUserId($externalUserId);
        }

        $applicant = $this->repository->create([
            'tenant_id'     => $this->tenantId,
            'user_id'       => $user->getAuthIdentifier(),
            'applicant_id'  => $response['id'],
            'level_name'    => $response['levelName'] ?? $levelName,
            'review_status' => $response['review']['reviewStatus'] ?? null,
            'review_answer' => $response['review']['reviewAnswer'] ?? null,
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

    /**
     * Build the externalUserId sent to Sumsub.
     *
     * In SaaS mode the user ID is namespaced with the tenant ID to prevent
     * collisions when multiple tenants share the same Sumsub project.
     * In single-tenant mode the raw user ID is used.
     */
    private function buildExternalUserId(Authenticatable $user): string
    {
        $userId = (string) $user->getAuthIdentifier();

        if (config('sumsub.saas_mode') && $this->tenantId !== null) {
            return "{$this->tenantId}:{$userId}";
        }

        return $userId;
    }
}
