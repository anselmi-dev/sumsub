<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Livewire;

use AnselmiDev\Sumsub\DataTypes\SumsubStatus;
use AnselmiDev\Sumsub\Facades\Sumsub;
use AnselmiDev\Sumsub\Models\SumsubApplicant;
use AnselmiDev\Sumsub\Services\SumsubService;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Throwable;

/**
 * Livewire KYC verification widget.
 *
 * Widget states
 * ─────────────
 *  idle      → no applicant record yet (or RETRY after a rejection)
 *  loading   → requesting the SDK token from Sumsub
 *  sdk_ready → Sumsub WebSDK is mounted and running
 *  progress  → documents submitted, waiting for review result
 *  completed → KYC approved (review_answer = GREEN)
 *  cancelled → KYC rejected (review_answer = RED)
 *  error     → API / network error while fetching the token
 *
 * Usage (single-tenant)
 * ─────────────────────
 *   <livewire:sumsub.kyc-verification />
 *
 * Usage (SaaS / multi-tenant)
 * ───────────────────────────
 *   <livewire:sumsub.kyc-verification :tenant-id="$tenantId" />
 *
 * Publishable view
 * ────────────────
 *   php artisan vendor:publish --tag=sumsub-views
 */
class KycVerification extends Component
{
    // ── Widget UI states ──────────────────────────────────────────────────────
    const STATE_IDLE      = 'idle';
    const STATE_LOADING   = 'loading';
    const STATE_SDK_READY = 'sdk_ready';
    const STATE_PROGRESS  = 'progress';
    const STATE_COMPLETED = 'completed';
    const STATE_CANCELLED = 'cancelled';
    const STATE_ERROR     = 'error';

    /** Current widget display state. */
    public string $state = self::STATE_IDLE;

    /** Raw review answer received from the last SDK event or webhook. */
    public string $reviewAnswer = '';

    /** SDK token for the Sumsub WebSDK. Cleared once the review is complete. */
    public ?string $sdkToken = null;

    /** Error message shown in STATE_ERROR. */
    public ?string $errorMessage = null;

    /**
     * Tenant identifier for SaaS mode.
     * Pass as a Livewire prop: <livewire:sumsub.kyc-verification :tenant-id="$id" />
     * Ignored when sumsub.saas_mode = false.
     */
    public ?string $tenantId = null;

    // ──────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $applicant = $this->findApplicant();

        if ($applicant === null) {
            return;
        }

        $this->reviewAnswer = $applicant->review_answer ?? '';
        $this->state        = $this->stateFromStatus($applicant->status);
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function startVerification(): void
    {
        $this->state        = self::STATE_LOADING;
        $this->errorMessage = null;

        try {
            $result = $this->service()->generateSdkToken(auth()->user());

            $this->sdkToken = $result['token'];
            $this->state    = self::STATE_SDK_READY;

            $this->dispatch('sumsub:launch', token: $this->sdkToken);
        } catch (Throwable $e) {
            $this->state        = self::STATE_ERROR;
            $this->errorMessage = __('No se pudo iniciar la verificación. Inténtalo de nuevo.');
            report($e);
        }
    }

    /**
     * Called from Alpine when the SDK token is about to expire.
     * #[Renderless] prevents a full Livewire re-render for this background call.
     */
    #[Renderless]
    #[On('sumsub:refresh-token')]
    public function refreshSdkToken(): void
    {
        try {
            $result = $this->service()->generateSdkToken(auth()->user());

            $this->sdkToken = $result['token'];

            $this->dispatch('sumsub:token-refreshed', token: $this->sdkToken);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Called from Alpine when the Sumsub SDK fires idCheck.applicantReviewComplete.
     *
     * reviewAnswer is one of: GREEN | RED | RETRY
     */
    #[On('sumsub:review-complete')]
    public function onReviewComplete(string $reviewAnswer): void
    {
        $this->reviewAnswer = $reviewAnswer;
        $this->sdkToken     = null;

        $this->state = match ($reviewAnswer) {
            SumsubApplicant::REVIEW_ANSWER_GREEN  => self::STATE_COMPLETED,
            SumsubApplicant::REVIEW_ANSWER_RED    => self::STATE_CANCELLED,
            SumsubApplicant::REVIEW_ANSWER_RETRY  => self::STATE_IDLE,   // user must restart
            default                               => self::STATE_PROGRESS,
        };
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('sumsub::livewire.kyc-verification');
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Resolve the SumsubService, scoped by tenant when in SaaS mode.
     */
    protected function service(): SumsubService
    {
        if (config('sumsub.saas_mode') && $this->tenantId !== null) {
            return Sumsub::forTenant($this->tenantId);
        }

        return app(SumsubService::class);
    }

    /**
     * Look up the most recent SumsubApplicant for the authenticated user,
     * scoped by tenant when in SaaS mode.
     */
    protected function findApplicant(): ?SumsubApplicant
    {
        $query = SumsubApplicant::query()->where('user_id', auth()->id());

        if (config('sumsub.saas_mode') && $this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        }

        return $query->latest()->first();
    }

    /**
     * Map a model-level SumsubStatus to a widget display state.
     *
     *  PENDING   → idle      (null/init/RETRY — the user has not yet submitted)
     *  PROGRESS  → progress  (pending/queued/prechecked/onHold)
     *  COMPLETED → completed (GREEN)
     *  CANCELLED → cancelled (RED)
     */
    protected function stateFromStatus(SumsubStatus $status): string
    {
        return match ($status->value) {
            SumsubStatus::PROGRESS  => self::STATE_PROGRESS,
            SumsubStatus::COMPLETED => self::STATE_COMPLETED,
            SumsubStatus::CANCELLED => self::STATE_CANCELLED,
            default                 => self::STATE_IDLE,
        };
    }
}
