<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Models;

use AnselmiDev\Sumsub\DataTypes\SumsubStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                              $id
 * @property string|null                      $tenant_id
 * @property int|string                       $user_id
 * @property string                           $applicant_id
 * @property string                           $level_name
 * @property string|null                      $review_status
 * @property string|null                      $review_answer
 * @property array|null                       $raw_data
 * @property-read \AnselmiDev\Sumsub\DataTypes\SumsubStatus $status
 * @property \Carbon\Carbon                   $created_at
 * @property \Carbon\Carbon                   $updated_at
 */
class SumsubApplicant extends Model
{
    CONST REVIEW_STATUS_PENDING = 'pending';

    CONST REVIEW_STATUS_COMPLETED = 'completed';

    CONST REVIEW_ANSWER_GREEN = 'GREEN';

    CONST REVIEW_ANSWER_RED = 'RED';

    CONST REVIEW_ANSWER_RETRY = 'RETRY';

    protected $table = 'sumsub_applicants';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'applicant_id',
        'level_name',
        'review_status',
        'review_answer',
        'raw_data',
    ];

    protected $casts = [
        'user_id'  => 'integer',
        'raw_data' => 'array',
    ];

    /**
     * The host-app User this applicant belongs to.
     * Resolved dynamically against the auth.providers.users.model config.
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model');

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Derived KYC status that maps review_status + review_answer
     * to a friendly SumsubStatus DataType.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn () => SumsubStatus::fromApplicant($this),
        );
    }

    public function isPending(): bool
    {
        return $this->review_status === self::REVIEW_STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->review_status === self::REVIEW_STATUS_COMPLETED;
    }

    public function isApproved(): bool
    {
        return $this->review_answer === self::REVIEW_ANSWER_GREEN;
    }

    public function isRejected(): bool
    {
        return $this->review_answer === self::REVIEW_ANSWER_RED;
    }
}
