<?php

use Illuminate\Support\Facades\Route;
use AnselmiDev\Sumsub\Http\Webhooks\SumsubWebhookController;

Route::post(
    config('sumsub.webhook_route', 'webhooks/sumsub'),
    SumsubWebhookController::class
)->name(config('sumsub.webhook_route_name', 'sumsub.webhook'));
