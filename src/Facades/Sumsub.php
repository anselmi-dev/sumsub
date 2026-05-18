<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Facades;

use Illuminate\Support\Facades\Facade;
use AnselmiDev\Sumsub\Services\SumsubService;

/**
 * @method static \AnselmiDev\Sumsub\Models\SumsubApplicant createApplicant(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $levelName = null)
 * @method static array{token: string, userId: string} generateSdkToken(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $levelName = null)
 * @method static \AnselmiDev\Sumsub\Models\SumsubApplicant refreshApplicant(\AnselmiDev\Sumsub\Models\SumsubApplicant $applicant)
 *
 * @see SumsubService
 */
class Sumsub extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SumsubService::class;
    }
}
