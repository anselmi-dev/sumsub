<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Http\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use AnselmiDev\Sumsub\Jobs\ProcessSumsubWebhook;

class SumsubWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! $this->isValidSignature($request)) {
            Log::warning('[Sumsub] Invalid webhook signature', [
                'ip' => $request->ip(),
            ]);

            return response('Unauthorized', 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $connection = config('sumsub.queue_connection');
        $queue = config('sumsub.queue_name');

        $job = new ProcessSumsubWebhook($payload);

        if ($connection !== null) {
            $job->onConnection($connection)->onQueue($queue);
        }

        dispatch($job);

        return response('', 200);
    }

    /**
     * Verify the HMAC-SHA256 digest sent in X-App-Token against the raw request body.
     *
     * Sumsub signs webhooks with: HMAC-SHA256(secret, rawBody).
     */
    private function isValidSignature(Request $request): bool
    {
        $secret = config('sumsub.webhook_secret');

        if (empty($secret)) {
            // If no secret is configured, skip verification (dev mode only).
            Log::debug('[Sumsub] Webhook secret not configured, skipping signature check.');

            return true;
        }

        $receivedDigest = $request->header('X-App-Token', '');
        $rawBody = $request->getContent();
        $expectedDigest = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expectedDigest, (string) $receivedDigest);
    }
}
