<?php
namespace OpsInsights;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

class Auth
{
    private string $apiUrl;
    private string $clientKey;
    private string $clientSecret;
    private ?string $token = null;
    private ?int $expiresAt = null;

    public function __construct(string $apiUrl, string $clientKey, string $clientSecret)
    {
        $this->apiUrl = $apiUrl;
        $this->clientKey = $clientKey;
        $this->clientSecret = $clientSecret;
    }

    public function authenticate(): void
    {
        $client = new GuzzleClient();

        try {
            $response = $client->post("{$this->apiUrl}/api/v1/oauth2/token", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'Accept' => 'application/json',
                    'User-Agent' => 'OpsInsights-API-Client',
                ],
                'json' => [
                    'key' => $this->clientKey,
                    'secret' => $this->clientSecret,
                    'auth_type' => 'api_key_secret',
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['success']) && $body['success'] === true && $body['status_code'] === 200) {
                $data = $body['data'][0];
                $this->token = $data['token'];
                $this->expiresAt = $data['expires_at'];
            } else {
                throw new \Exception('Authentication failed: Invalid response from API.');
            }
        } catch (GuzzleException $e) {
            throw new \Exception('Authentication failed: ' . $e->getMessage());
        }
    }

    public function getToken(): string
    {
        if ($this->isTokenExpired()) {
            $this->authenticate();
        }

        return $this->token;
    }

    private function isTokenExpired(): bool
    {
        if ($this->expiresAt === null) {
            return true;
        }

        return time() >= $this->expiresAt;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }
}
