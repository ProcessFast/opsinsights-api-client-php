<?php
namespace OpsInsights;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

class Client
{
    private GuzzleClient $httpClient;
    private Auth $auth;
    private ?string $clientId = null;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
        // Initialize Guzzle
        $this->httpClient = new GuzzleClient(['base_uri' => $auth->getApiUrl()]);
    }

    /**
     * Append /api/v1 to all endpoint requests.
     */
    private function buildEndpoint(string $endpoint): string
    {
        return "/api/v1" . $endpoint;
    }

    /**
     * Get request with automatic token handling
     */
    public function getData(string $endpoint): array
    {
        try {
            $response = $this->httpClient->get($this->buildEndpoint($endpoint), [
                'headers' => $this->getHeaders(),
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new \Exception('Request failed: ' . $e->getMessage());
        }
    }

    /**
     * Post request with automatic token handling
     */
    public function postData(string $endpoint, array $data): array
    {
        try {
            $response = $this->httpClient->post($this->buildEndpoint($endpoint), [
                'headers' => $this->getHeaders(),
                'json' => $data,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new \Exception('Request failed: ' . $e->getMessage());
        }
    }

    /**
     * Gets the authorization headers with an up-to-date token.
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->auth->getToken()}",
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'User-Agent' => 'OpsInsights-API-Client',
        ];
    }

    /**
     * Retrieves information about the client's account using the /clients/me endpoint.
     * Also stores the client's ID for further use.
     *
     * @throws \Exception
     * @return object|null Returns an object with the client's details, or null on failure.
     */
    public function getMyClientId(): ?object
    {
        $endpoint = '/clients/me';

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            $clientData = $response['data'][0];

            // Store the client ID in the class property
            $this->clientId = $clientData['your_client_id'];

            return (object)[
                'client_name' => $clientData['client_name'],
                'client_id' => $clientData['your_client_id'],
                'api_keys' => $clientData['api_keys'],
                'api_connectors' => $clientData['api_connectors']
            ];
        }

        return null; // Return null if the request wasn't successful
    }

    /**
     * Gets the client ID stored from getMyClientId.
     *
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * Retrieves the list of all available API endpoints from the /api/v1/helpers/api-endpoints endpoint.
     *
     * @throws \Exception
     * @return array|null Returns an array of API endpoint details, or null on failure.
     */
    public function listApiEndpoints(): ?array
    {
        $endpoint = '/helpers/api-endpoints';

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            // Return the 'data' part of the response, which contains the API endpoints
            return $response['data'];
        }

        return null; // Return null if the request wasn't successful
    }
}
