<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\DataTypes;

use AnselmiDev\Sumsub\Models\SumsubApplicant;
use InvalidArgumentException;

/**
 * Represents the simplified KYC status of a SumsubApplicant.
 *
 * Maps the raw Sumsub (review_status + review_answer) fields into
 * four human-friendly states based on Sumsub action-verification-statuses docs:
 *
 *  null / init                              → PENDING  (not started or just created)
 *  pending / queued / prechecked / onHold   → PROGRESS (in review queue)
 *  completed + GREEN                        → COMPLETED
 *  completed + RED                          → CANCELLED
 *  completed + RETRY                        → PENDING  (user must restart)
 *
 * @property-read string $label
 * @property-read string $color
 */
class SumsubStatus
{
    const PENDING   = 'pending';
    const PROGRESS  = 'progress';
    const COMPLETED = 'completed';
    const CANCELLED = 'cancelled';

    private const ALLOWED = [self::PENDING, self::PROGRESS, self::COMPLETED, self::CANCELLED];

    public function __construct(
        public readonly SumsubApplicant $model,
        public readonly string $value,
    ) {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new InvalidArgumentException(
                "Invalid SumsubStatus value: [{$value}]. Allowed: " . implode(', ', self::ALLOWED)
            );
        }
    }

    /**
     * Build a SumsubStatus from a SumsubApplicant's raw Sumsub fields.
     */
    public static function fromApplicant(SumsubApplicant $applicant): self
    {
        $reviewStatus = $applicant->review_status;
        $reviewAnswer = $applicant->review_answer;

        $value = match (true) {
            // Not started, or just created by the SDK (docs: "init")
            $reviewStatus === null,
            $reviewStatus === 'init'
                => self::PENDING,

            // Approved
            $reviewStatus === 'completed' && $reviewAnswer === SumsubApplicant::REVIEW_ANSWER_GREEN
                => self::COMPLETED,

            // Rejected
            $reviewStatus === 'completed' && $reviewAnswer === SumsubApplicant::REVIEW_ANSWER_RED
                => self::CANCELLED,

            // Needs to retry — treat as PENDING so the user can restart the flow
            $reviewStatus === 'completed' && $reviewAnswer === SumsubApplicant::REVIEW_ANSWER_RETRY
                => self::PENDING,

            // pending / queued / prechecked / onHold → in review
            default => self::PROGRESS,
        };

        return new self($applicant, $value);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Boolean helpers
    // ──────────────────────────────────────────────────────────────────────────

    public function isPending(): bool   { return $this->value === self::PENDING; }
    public function isProgress(): bool  { return $this->value === self::PROGRESS; }
    public function isCompleted(): bool { return $this->value === self::COMPLETED; }
    public function isCancelled(): bool { return $this->value === self::CANCELLED; }

    // ──────────────────────────────────────────────────────────────────────────
    // Presentation helpers
    // ──────────────────────────────────────────────────────────────────────────

    public function label(): string
    {
        return match ($this->value) {
            self::PENDING   => 'Pendiente',
            self::PROGRESS  => 'En progreso',
            self::COMPLETED => 'Completado',
            self::CANCELLED => 'Cancelado',
        };
    }

    /** @return 'gray'|'yellow'|'green'|'red' */
    public function color(): string
    {
        return match ($this->value) {
            self::PENDING   => 'gray',
            self::PROGRESS  => 'yellow',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Magic
    // ──────────────────────────────────────────────────────────────────────────

    public function __get(string $name): mixed
    {
        if (method_exists($this, $name)) {
            return $this->{$name}();
        }

        return null;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
