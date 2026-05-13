<?php

declare(strict_types=1);

namespace AnselmiDev\Sumsub\Http\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use AnselmiDev\Sumsub\Contracts\SumsubClientInterface;
use RuntimeException;

class SumsubClient implements SumsubClientInterface
{
    private Client $http;

    public function __construct(
        private readonly string $appToken,
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {
        $this->http = new Client([
            'base_uri' => rtrim($this->baseUrl, '/'),
            'timeout'  => 30,
        ]);
    }

    public function createApplicant(string $externalUserId, string $levelName): array
    {
        $path = '/resources/applicants?levelName='.urlencode($levelName);
        $body = json_encode(['externalUserId' => $externalUserId], JSON_THROW_ON_ERROR);

        return $this->request('POST', $path, $body);
    }

    public function getApplicant(string $applicantId): array
    {
        return $this->request('GET', "/resources/applicants/{$applicantId}/one");
    }

    public function getApplicantByExternalUserId(string $externalUserId): array
    {
        $id = urlencode($externalUserId);

        return $this->request('GET', "/resources/applicants/-;externalUserId={$id}/one");
    }

    public function generateSdkToken(string $applicantId, string $levelName): array
    {
        $path = '/resources/accessTokens?userId='.urlencode($applicantId).'&levelName='.urlencode($levelName);

        return $this->request('POST', $path);
    }

    public function getApplicantReview(string $applicantId): array
    {
        return $this->request('GET', "/resources/applicants/{$applicantId}/requiredIdDocsStatus");
    }

    public function resetApplicant(string $applicantId): bool
    {
        $this->request('POST', "/resources/applicants/{$applicantId}/reset");

        return true;
    }

    /**
     * Execute a signed HTTP request against the Sumsub API.
     *
     * Sumsub requires HMAC-SHA256 signing of: ts + method + path + body.
     *
     * @throws RuntimeException on non-2xx responses
     */
    private function request(string $method, string $path, string $body = ''): array
    {
        $ts = (string) time();
        $signature = $this->buildSignature($ts, $method, $path, $body);

        $headers = [
            'Accept'           => 'application/json',
            'Content-Type'     => 'application/json',
            'X-App-Token'      => $this->appToken,
            'X-App-Access-Sig' => $signature,
            'X-App-Access-Ts'  => $ts,
        ];

        try {
            $response = $this->http->request($method, $path, [
                'headers' => $headers,
                'body'    => $body,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException("Sumsub API request failed: {$e->getMessage()}", $e->getCode(), $e);
        }

        $statusCode = $response->getStatusCode();
        $responseBody = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException("Sumsub API error [{$statusCode}]: {$responseBody}");
        }

        return json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    }

    private function buildSignature(string $ts, string $method, string $path, string $body): string
    {
        $data = $ts.strtoupper($method).$path.$body;

        return hash_hmac('sha256', $data, $this->secretKey);
    }
}
