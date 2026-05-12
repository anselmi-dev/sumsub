<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int|string  $user_id
 * @property string      $applicant_id
 * @property string      $level_name
 * @property string|null $review_status
 * @property string|null $review_answer
 * @property array|null  $raw_data
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SumsubApplicant extends Model
{
    protected $table = 'sumsub_applicants';

    protected $fillable = [
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

    public function isPending(): bool
    {
        return $this->review_status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->review_status === 'completed';
    }

    public function isApproved(): bool
    {
        return $this->review_answer === 'GREEN';
    }

    public function isRejected(): bool
    {
        return $this->review_answer === 'RED';
    }
}
