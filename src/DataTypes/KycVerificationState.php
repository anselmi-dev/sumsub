<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\DataTypes;

use AnselmiDev\Sumsub\Models\SumsubApplicant;

/**
 * Estados del flujo de verificación KYC (UI / widget).
 *
 * Los estados derivados del dominio (SumsubStatus, review_answer) se resuelven
 * con fromApplicant(), fromSumsubStatus() y fromReviewAnswer().
 *
 * Los estados transitorios (Loading, SdkReady, Error) los asigna la capa de
 * presentación (p. ej. un componente Livewire) durante la interacción.
 */
enum KycVerificationState: string
{
    case Idle = 'idle';
    case Loading = 'loading';
    case SdkReady = 'sdk_ready';
    case Progress = 'progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Error = 'error';

    /**
     * Estado inicial del widget a partir del applicant persistido.
     */
    public static function fromApplicant(SumsubApplicant $applicant): self
    {
        return self::fromSumsubStatus($applicant->status);
    }

    /**
     * Mapea SumsubStatus (dominio) al estado del widget.
     *
     *  PENDING   → Idle
     *  PROGRESS  → Progress
     *  COMPLETED → Completed
     *  CANCELLED → Cancelled
     */
    public static function fromSumsubStatus(SumsubStatus $status): self
    {
        return match ($status->value) {
            SumsubStatus::PROGRESS  => self::Progress,
            SumsubStatus::COMPLETED => self::Completed,
            SumsubStatus::CANCELLED => self::Cancelled,
            default                 => self::Idle,
        };
    }

    /**
     * Mapea la reviewAnswer del SDK (GREEN | RED | RETRY) al estado del widget.
     */
    public static function fromReviewAnswer(string $reviewAnswer): self
    {
        return match ($reviewAnswer) {
            SumsubApplicant::REVIEW_ANSWER_GREEN => self::Completed,
            SumsubApplicant::REVIEW_ANSWER_RED   => self::Cancelled,
            SumsubApplicant::REVIEW_ANSWER_RETRY => self::Idle,
            default                              => self::Progress,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
