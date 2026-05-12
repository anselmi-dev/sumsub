<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use AnselmiDev\Sumsub\Http\Webhooks\SumsubWebhookController;

Route::post(
    config('sumsub.webhook_route', 'webhooks/sumsub'),
    SumsubWebhookController::class
)->name('sumsub.webhook');
