<?php
namespace OpsInsights;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

class Client
{
    private GuzzleClient $httpClient;
    private Auth $auth;
    private ?string $clientId = null;
    private ?string $connectorId = null;
    private ?string $fileId = null;
    private ?string $propertyId = null;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
        // Initialize Guzzle with base URI excluding /api/v1, we will append it manually in requests
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

            $responseData = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() !== 200 && isset($responseData['data'][0])) {
                $error = $responseData['data'][0];
                throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
            }

            return $responseData;
        } catch (GuzzleException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';
            $responseData = json_decode($responseBody, true);

            if (isset($responseData['data'][0])) {
                $error = $responseData['data'][0];
                throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
            }

            throw new \Exception('Request failed: ' . $e->getMessage() . '. Response: ' . $responseBody);
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
     * Also stores the client's ID and the first connector ID for further use.
     *
     * @throws \Exception
     * @return object Returns an object with the client's details, or throws an exception on failure.
     */
    public function getMyClientId(): object
    {
        $endpoint = '/clients/me';

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            $clientData = $response['data'][0];

            // Store the client ID in the class property
            $this->clientId = $clientData['your_client_id'];

            // Store the first connector ID in the class property, if available
            if (!empty($clientData['api_connectors'])) {
                $this->connectorId = $clientData['api_connectors'][0]['connector_id'];
            }

            return (object)[
                'client_name' => $clientData['client_name'],
                'client_id' => $clientData['your_client_id'],
                'api_keys' => $clientData['api_keys'],
                'api_connectors' => $clientData['api_connectors']
            ];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve client information.');
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
     * Gets the connector ID stored from getMyClientId.
     *
     * @return string|null
     */
    public function getConnectorId(): ?string
    {
        return $this->connectorId;
    }

    /**
     * Sets the connector ID.
     *
     * @param string $connectorId
     */
    public function setConnectorId(string $connectorId): void
    {
        $this->connectorId = $connectorId;
    }

    /**
     * Gets the file ID stored from fileLookupByAddress.
     *
     * @return string|null
     */
    public function getFileId(): ?string
    {
        return $this->fileId;
    }

    /**
     * Sets the file ID.
     *
     * @param string $fileId
     */
    public function setFileId(string $fileId): void
    {
        $this->fileId = $fileId;
    }

    /**
     * Gets the property ID stored from fileLookupByAddress.
     *
     * @return string|null
     */
    public function getPropertyId(): ?string
    {
        return $this->propertyId;
    }

    /**
     * Sets the property ID.
     *
     * @param string $propertyId
     */
    public function setPropertyId(string $propertyId): void
    {
        $this->propertyId = $propertyId;
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

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve API endpoints.');
    }

    /**
     * Queries the /files/:client_id/:client_connector_id/address/:property_address endpoint to get file information by a given address.
     * Spaces in the address should be replaced with underscores.
     *
     * @param string $address The address to look up.
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @return array Returns the file information, or throws an exception on failure.
     * @throws \Exception
     */
    public function fileLookupByAddress(string $address, string $clientId, string $connectorId): array
    {
        // Replace spaces with underscores in the address
        $formattedAddress = str_replace(' ', '_', $address);

        $endpoint = "/files/$clientId/$connectorId/address/$formattedAddress";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            $fileData = $response['data'][0]['file_info'][0] ?? null;

            if ($fileData) {
                // Store the first file ID and property ID in the class properties
                $this->fileId = $fileData['file_id'] ?? null;
                $this->propertyId = $fileData['property_info'][0]['property_id'] ?? null;
            }

            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve file information.');
    }

    /**
     * Retrieves the list of available connectors from the /clients/me endpoint.
     *
     * @throws \Exception
     * @return array Returns an array of available connectors, or throws an exception on failure.
     */
    public function getMyListOfAvailableConnectors(): array
    {
        $endpoint = '/clients/me';

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            $clientData = $response['data'][0];

            if (!empty($clientData['api_connectors'])) {
                return $clientData['api_connectors'];
            }
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve available connectors.');
    }

    /**
     * Prints out the list of addresses returned by the fileLookupByAddress method.
     *
     * @param array $fileLookupData The data returned by the fileLookupByAddress method.
     * @return void
     */
    public function printAddressesFromLookup(array $fileLookupData): void
    {
        echo "File Info Returned With Matching Property Address:\n" . PHP_EOL;
        foreach ($fileLookupData as $fileinfo) {
            if (isset($fileinfo['file_info'])) {
                foreach ($fileinfo['file_info'] as $file) {
                    echo "File ID: " . $file['file_id'] . PHP_EOL;
                    echo "File Number: " . $file['file_number'] . PHP_EOL;
                    if (isset($file['property_info'][0])) {
                        echo "Property ID: " . $file['property_info'][0]['property_id'] . PHP_EOL;
                        echo "Property Address: " . $file['property_info'][0]['street_address'] . PHP_EOL;
                    }
                    echo "----------------------------------------\n" . PHP_EOL;
                }
            }
        }
    }

    /**
     * Prints the client's key information and available connectors.
     *
     * @return void
     * @throws \Exception
     */
    public function printMyKeyInfoAndAvailableConnectors(): void
    {
        // Retrieve the client's information
        $clientInfo = $this->getMyClientId();

        echo "\n" . PHP_EOL;
        echo "My Key Info: \n" . PHP_EOL;
        echo "Client Name: " . $clientInfo->client_name . PHP_EOL;
        echo "Client ID: " . $clientInfo->client_id . PHP_EOL;
        echo "Stored Client ID: " . $this->getClientId() . PHP_EOL;
        echo "Current Bearer Authentication Token: " . $this->auth->getToken() . PHP_EOL;
        echo "My Current Connector ID = " . $this->getConnectorId() . PHP_EOL;
        echo "\n" . PHP_EOL;

        // Retrieve and print the list of available connectors
        $availableConnectors = $this->getMyListOfAvailableConnectors();

        if ($availableConnectors) {
            echo "List of Available Connectors Assigned to Your Current API Key:\n" . PHP_EOL;
            foreach ($availableConnectors as $connector) {
                echo "Client Name: " . $connector['client_name'] . PHP_EOL;
                echo "Client ID: " . $connector['client_id'] . PHP_EOL;
                echo "Connector ID: " . $connector['connector_id'] . PHP_EOL;
                echo "Connector Name: " . $connector['connector_name'] . PHP_EOL;
                echo "----------------------------------------\n\n" . PHP_EOL;
            }
        } else {
            echo "Failed to retrieve API connectors.";
        }
    }

    /**
     * Queries the /files/:client_id/:client_connector_id/:file_id endpoint to get file information by file ID.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $fileId The file ID to look up.
     * @return array Returns the file information, or throws an exception on failure.
     * @throws \Exception
     */
    public function fileLookupByFileId(string $clientId, string $connectorId, string $fileId): array
    {
        $endpoint = "/files/$clientId/$connectorId/$fileId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve file information.');
    }

    /**
     * Prints out all of the information returned in the 'data' section of the fileLookupByFileId response.
     *
     * @param array $fileData The data returned by the fileLookupByFileId method.
     * @return void
     */
    public function printFileInfo(array $fileData): void
    {
        foreach ($fileData as $file) {
            echo "File ID: " . $file['file_id'] . PHP_EOL;
            echo "File Number: " . $file['file_number'] . PHP_EOL;
            echo "File Status: " . $file['file_status'] . PHP_EOL;
            echo "Product: " . $file['product'] . PHP_EOL;
            echo "Sales Price: " . $file['sales_price'] . PHP_EOL;
            echo "Open Date: " . $file['open_date'] . PHP_EOL;
            echo "Estimated Settlement Date: " . $file['estimated_settlement_date'] . PHP_EOL;
            echo "Actual Settlement Date: " . $file['actual_settlement_date'] . PHP_EOL;
            echo "Disbursement Date: " . $file['disbursement_date'] . PHP_EOL;

            if (isset($file['property_info'])) {
                foreach ($file['property_info'] as $property) {
                    echo "Property ID: " . $property['property_id'] . PHP_EOL;
                    echo "Property Address: " . $property['street_address'] . PHP_EOL;
                    echo "Property URL: " . $property['property_url'] . PHP_EOL;
                }
            }

            if (isset($file['client_info'])) {
                echo "Client ID: " . $file['client_info']['client_id'] . PHP_EOL;
                echo "Client Name: " . $file['client_info']['client_name'] . PHP_EOL;
                echo "File Contact: " . $file['client_info']['file_contact'] . PHP_EOL;
                echo "Client Address: " . $file['client_info']['address'] . PHP_EOL;
            }

            if (isset($file['lender_info'])) {
                foreach ($file['lender_info'] as $lender) {
                    echo "Lender ID: " . $lender['lender_id'] . PHP_EOL;
                    echo "Lender Name: " . $lender['lender_name'] . PHP_EOL;
                    echo "Lender Address: " . $lender['address'] . PHP_EOL;

                    if (isset($lender['loan_info'])) {
                        foreach ($lender['loan_info'] as $loan) {
                            echo "Lien Position: " . $loan['lien_position'] . PHP_EOL;
                            echo "Loan Amount: " . $loan['loan_amount'] . PHP_EOL;
                            echo "Loan Number: " . $loan['loan_number'] . PHP_EOL;
                        }
                    }
                }
            }

            if (isset($file['buyer_info'])) {
                foreach ($file['buyer_info'] as $buyer) {
                    echo "Buyer ID: " . $buyer['buyer_id'] . PHP_EOL;
                    echo "Buyer One: " . $buyer['buyer_one'] . PHP_EOL;
                    echo "Buyer Two: " . $buyer['buyer_two'] . PHP_EOL;
                    echo "Buyer URL: " . $buyer['buyer_url'] . PHP_EOL;
                }
            }

            if (isset($file['seller_info'])) {
                foreach ($file['seller_info'] as $seller) {
                    echo "Seller ID: " . $seller['seller_id'] . PHP_EOL;
                    echo "Company Name: " . $seller['company_name'] . PHP_EOL;
                    echo "Seller One: " . $seller['seller_one'] . PHP_EOL;
                    echo "Seller Two: " . $seller['seller_two'] . PHP_EOL;
                    echo "Seller URL: " . $seller['seller_url'] . PHP_EOL;
                }
            }

            echo "----------------------------------------\n" . PHP_EOL;
        }
    }

    /**
     * Queries the /files/:client_id/:client_connector_id/lender-file/:lender_file_number endpoint to get file information by lender file number.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $lenderFileNumber The lender file number to look up.
     * @return array Returns the file information, or throws an exception on failure.
     * @throws \Exception
     */
    public function fileLookupByLenderLoanNumber(string $clientId, string $connectorId, string $lenderFileNumber): array
    {
        $endpoint = "/files/$clientId/$connectorId/lender-file/$lenderFileNumber";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve file information by lender file number.');
    }

    /**
     * Prints out all of the information returned in the 'data' section of the fileLookupByLenderLoanNumber response.
     *
     * @param array $fileData The data returned by the fileLookupByLenderLoanNumber method.
     * @return void
     */
    public function printLenderFileInfo(array $fileData): void
    {
        foreach ($fileData as $fileInfo) {
            if (isset($fileInfo['file_info'])) {
                foreach ($fileInfo['file_info'] as $file) {
                    echo "File ID: " . ($file['file_id'] ?? 'N/A') . PHP_EOL;
                    echo "File Number: " . ($file['file_number'] ?? 'N/A') . PHP_EOL;
                    echo "Files URL: " . ($file['files_url'] ?? 'N/A') . PHP_EOL;

                    if (isset($file['loan_number'])) {
                        echo "Loan Number: " . implode(', ', $file['loan_number']) . PHP_EOL;
                    }

                    if (isset($file['property_info'])) {
                        foreach ($file['property_info'] as $property) {
                            echo "Property ID: " . ($property['property_id'] ?? 'N/A') . PHP_EOL;
                            echo "Street Address: " . ($property['street_address'] ?? 'N/A') . PHP_EOL;
                            echo "Property URL: " . ($property['properties_url'] ?? 'N/A') . PHP_EOL;
                        }
                    }

                    if (isset($file['lender_info'])) {
                        foreach ($file['lender_info'] as $lender) {
                            echo "Lender ID: " . ($lender['lender_id'] ?? 'N/A') . PHP_EOL;
                            echo "Lender Name: " . ($lender['lender_name'] ?? 'N/A') . PHP_EOL;
                            echo "Lender Address: " . ($lender['address'] ?? 'N/A') . PHP_EOL;
                            echo "File Partners URL: " . ($lender['file_partners_url'] ?? 'N/A') . PHP_EOL;
                        }
                    }

                    echo "----------------------------------------\n" . PHP_EOL;
                }
            }
        }
    }


    /**
     * Queries the /file-partners/:client_id/:client_connector_id/:file_id endpoint to get all partners on a file.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $fileId The file ID to look up.
     * @return array Returns the file partners information, or throws an exception on failure.
     * @throws \Exception
     */
    public function getAllPartnersOnaFile(string $clientId, string $connectorId, string $fileId): array
    {
        $endpoint = "/file-partners/$clientId/$connectorId/$fileId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve file partners information.');
    }


    /**
     * Prints out all of the information returned in the 'data' section of the getAllPartnersOnaFile response.
     *
     * @param array $partnersData The data returned by the getAllPartnersOnaFile method.
     * @return void
     */
    public function printAllPartnersOnaFile(array $partnersData): void
    {
        foreach ($partnersData as $filePartnerInfo) {
            echo "File ID: " . ($filePartnerInfo['file_id'] ?? 'N/A') . PHP_EOL;
            echo "File Number: " . ($filePartnerInfo['file_number'] ?? 'N/A') . PHP_EOL;
            echo "Files URL: " . ($filePartnerInfo['files_url'] ?? 'N/A') . PHP_EOL;

            if (isset($filePartnerInfo['file_partners'])) {
                foreach ($filePartnerInfo['file_partners'] as $partnerType => $partners) {
                    echo "Partner Type: " . $partnerType . PHP_EOL;

                    foreach ($partners as $partner) {
                        echo "Partner ID: " . ($partner['partner_id'] ?? 'N/A') . PHP_EOL;
                        echo "Partner Name: " . ($partner['partner_name'] ?? 'N/A') . PHP_EOL;
                        echo "Email: " . ($partner['email'] ?? 'N/A') . PHP_EOL;
                        echo "Phone: " . ($partner['phone'] ?? 'N/A') . PHP_EOL;
                        echo "Fax: " . ($partner['fax'] ?? 'N/A') . PHP_EOL;
                        echo "Address: " . ($partner['address'] ?? 'N/A') . PHP_EOL;

                        if (isset($partner['file_contact'])) {
                            echo "File Contact Information:" . PHP_EOL;
                            echo "  Contact ID: " . ($partner['file_contact']['contact_id'] ?? 'N/A') . PHP_EOL;
                            echo "  Contact Name: " . ($partner['file_contact']['contact_name'] ?? 'N/A') . PHP_EOL;
                            echo "  Job Title: " . ($partner['file_contact']['job_title'] ?? 'N/A') . PHP_EOL;
                            echo "  Email: " . ($partner['file_contact']['email'] ?? 'N/A') . PHP_EOL;
                            echo "  Phone: " . ($partner['file_contact']['phone'] ?? 'N/A') . PHP_EOL;
                            echo "  Cell Phone: " . ($partner['file_contact']['cell_phone'] ?? 'N/A') . PHP_EOL;
                            echo "  Fax: " . ($partner['file_contact']['fax'] ?? 'N/A') . PHP_EOL;
                        }

                        echo "----------------------------------------\n" . PHP_EOL;
                    }
                }
            }
        }
    }

    /**
     * Queries the /buyers/:client_id/:client_connector_id/:buyer_id endpoint to get buyer information.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $buyerId The buyer ID to look up.
     * @return array Returns the buyer information, or throws an exception on failure.
     * @throws \Exception
     */
    public function getBuyersInfo(string $clientId, string $connectorId, string $buyerId): array
    {
        $endpoint = "/buyers/$clientId/$connectorId/$buyerId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve buyer information.');
    }


    /**
     * Prints out all of the information returned in the 'data' section of the getBuyersInfo response.
     *
     * @param array $buyersData The data returned by the getBuyersInfo method.
     * @return void
     */
    public function printBuyersInfo(array $buyersData): void
    {
        foreach ($buyersData as $buyerInfo) {
            echo "File ID: " . ($buyerInfo['file_id'] ?? 'N/A') . PHP_EOL;
            echo "File Number: " . ($buyerInfo['file_number'] ?? 'N/A') . PHP_EOL;
            echo "Files URL: " . ($buyerInfo['files_url'] ?? 'N/A') . PHP_EOL;
            echo "Buyer ID: " . ($buyerInfo['buyer_id'] ?? 'N/A') . PHP_EOL;

            if (isset($buyerInfo['buyer_one'])) {
                echo "Buyer One - First Name: " . ($buyerInfo['buyer_one']['first_name'] ?? 'N/A') . PHP_EOL;
                echo "Buyer One - Middle Name: " . ($buyerInfo['buyer_one']['middle_name'] ?? 'N/A') . PHP_EOL;
                echo "Buyer One - Last Name: " . ($buyerInfo['buyer_one']['last_name'] ?? 'N/A') . PHP_EOL;
            }

            if (isset($buyerInfo['buyer_two'])) {
                echo "Buyer Two - First Name: " . ($buyerInfo['buyer_two']['first_name'] ?? 'N/A') . PHP_EOL;
                echo "Buyer Two - Middle Name: " . ($buyerInfo['buyer_two']['middle_name'] ?? 'N/A') . PHP_EOL;
                echo "Buyer Two - Last Name: " . ($buyerInfo['buyer_two']['last_name'] ?? 'N/A') . PHP_EOL;
            }

            if (isset($buyerInfo['contact_info'])) {
                echo "Contact Information:" . PHP_EOL;
                echo "  Home Phone: " . ($buyerInfo['contact_info']['home_phone'] ?? 'N/A') . PHP_EOL;
                echo "  Cell Phone One: " . ($buyerInfo['contact_info']['cell_phone_one'] ?? 'N/A') . PHP_EOL;
                echo "  Cell Phone Two: " . ($buyerInfo['contact_info']['cell_phone_two'] ?? 'N/A') . PHP_EOL;
                echo "  Work Phone One: " . ($buyerInfo['contact_info']['work_phone_one'] ?? 'N/A') . PHP_EOL;
                echo "  Work Phone Two: " . ($buyerInfo['contact_info']['work_phone_two'] ?? 'N/A') . PHP_EOL;
                echo "  Fax One: " . ($buyerInfo['contact_info']['fax_one'] ?? 'N/A') . PHP_EOL;
                echo "  Fax Two: " . ($buyerInfo['contact_info']['fax_two'] ?? 'N/A') . PHP_EOL;
                echo "  Email Address One: " . ($buyerInfo['contact_info']['email_address_one'] ?? 'N/A') . PHP_EOL;
                echo "  Email Address Two: " . ($buyerInfo['contact_info']['email_address_two'] ?? 'N/A') . PHP_EOL;
                echo "  Mailing Address: " . ($buyerInfo['contact_info']['mailing_address'] ?? 'N/A') . PHP_EOL;
                echo "  Forwarding Address: " . ($buyerInfo['contact_info']['forwarding_address'] ?? 'N/A') . PHP_EOL;
            }

            echo "----------------------------------------\n" . PHP_EOL;
        }
    }

    /**
     * Queries the /disbursements/:client_id/:client_connector_id/:file_id endpoint to get disbursement information for a given file.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $fileId The file ID to look up.
     * @return array Returns the disbursement information, or throws an exception on failure.
     * @throws \Exception
     */
    public function getDisbursementInfo(string $clientId, string $connectorId, string $fileId): array
    {
        $endpoint = "/disbursements/$clientId/$connectorId/$fileId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve disbursement information.');
    }

    /**
     * Prints out all of the information returned in the 'data' section of the getDisbursementInfo response.
     *
     * @param array $disbursementData The data returned by the getDisbursementInfo method.
     * @return void
     */
    public function printDisbursementInfo(array $disbursementData): void
    {
        foreach ($disbursementData as $disbursement) {
            echo "File ID: " . ($disbursement['file_id'] ?? 'N/A') . PHP_EOL;
            echo "File Number: " . ($disbursement['file_number'] ?? 'N/A') . PHP_EOL;
            echo "Files URL: " . ($disbursement['files_url'] ?? 'N/A') . PHP_EOL;

            if (isset($disbursement['disbursements'])) {
                foreach ($disbursement['disbursements'] as $type => $disbursements) {
                    echo "Disbursement Type: " . $type . PHP_EOL;

                    foreach ($disbursements as $disb) {
                        echo "Disbursement ID: " . ($disb['disbursement_id'] ?? 'N/A') . PHP_EOL;
                        echo "Check Number: " . ($disb['check_number'] ?? 'N/A') . PHP_EOL;
                        echo "Status: " . ($disb['status'] ?? 'N/A') . PHP_EOL;
                        echo "Date Created: " . ($disb['date_created'] ?? 'N/A') . PHP_EOL;
                        echo "Date Issued: " . ($disb['date_issued'] ?? 'N/A') . PHP_EOL;
                        echo "Date Cleared: " . ($disb['date_cleared'] ?? 'N/A') . PHP_EOL;
                        echo "Date Voided: " . ($disb['date_voided'] ?? 'N/A') . PHP_EOL;
                        echo "Payee: " . ($disb['payee'] ?? 'N/A') . PHP_EOL;
                        echo "Payee Address: " . ($disb['payee_address'] ?? 'N/A') . PHP_EOL;
                        echo "Disbursement Amount: " . ($disb['disbursement_amount'] ?? 'N/A') . PHP_EOL;
                        echo "Disbursement Memo: " . ($disb['disbursement_memo'] ?? 'N/A') . PHP_EOL;

                        if (isset($disb['disbursement_item'])) {
                            foreach ($disb['disbursement_item'] as $item) {
                                echo "  Disbursement Item ID: " . ($item['disbursement_item_id'] ?? 'N/A') . PHP_EOL;
                                echo "  Held: " . ($item['held'] ?? 'N/A') . PHP_EOL;
                                echo "  Item Memo: " . ($item['item_memo'] ?? 'N/A') . PHP_EOL;
                                echo "  Item Description: " . ($item['item_description'] ?? 'N/A') . PHP_EOL;
                                echo "  Item Amount: " . ($item['item_amount'] ?? 'N/A') . PHP_EOL;
                            }
                        }

                        echo "----------------------------------------\n" . PHP_EOL;
                    }
                }
            }
        }
    }

    /**
     * Queries the /properties/:client_id/:client_connector_id/:property_id endpoint to get property information.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $propertyId The property ID to look up.
     * @return array Returns the property information, or throws an exception on failure.
     * @throws \Exception
     */
    public function getPropertyInfo(string $clientId, string $connectorId, string $propertyId): array
    {
        $endpoint = "/properties/$clientId/$connectorId/$propertyId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve property information.');
    }

    /**
     * Prints out all of the information returned in the 'data' section of the getPropertyInfo response.
     *
     * @param array $propertyData The data returned by the getPropertyInfo method.
     * @return void
     */
    public function printPropertyInfo(array $propertyData): void
    {
        foreach ($propertyData as $property) {
            echo "File ID: " . ($property['file_id'] ?? 'N/A') . PHP_EOL;
            echo "File Number: " . ($property['file_number'] ?? 'N/A') . PHP_EOL;
            echo "Files URL: " . ($property['files_url'] ?? 'N/A') . PHP_EOL;

            if (isset($property['property_info'])) {
                echo "Property ID: " . ($property['property_info']['property_id'] ?? 'N/A') . PHP_EOL;
                echo "Street Address: " . ($property['property_info']['street_address'] ?? 'N/A') . PHP_EOL;
                echo "Subdivision: " . ($property['property_info']['subdivision'] ?? 'N/A') . PHP_EOL;
                echo "Unit: " . ($property['property_info']['unit'] ?? 'N/A') . PHP_EOL;
                echo "County: " . ($property['property_info']['county'] ?? 'N/A') . PHP_EOL;
                echo "Township: " . ($property['property_info']['township'] ?? 'N/A') . PHP_EOL;
                echo "District: " . ($property['property_info']['district'] ?? 'N/A') . PHP_EOL;
                echo "Parcel One: " . ($property['property_info']['parcel_one'] ?? 'N/A') . PHP_EOL;
                echo "Parcel Two: " . ($property['property_info']['parcel_two'] ?? 'N/A') . PHP_EOL;
                echo "Parcel Three: " . ($property['property_info']['parcel_three'] ?? 'N/A') . PHP_EOL;
                echo "Parcel Four: " . ($property['property_info']['parcel_four'] ?? 'N/A') . PHP_EOL;

                if (isset($property['property_info']['past_recording_info'])) {
                    echo "Past Recording Info:" . PHP_EOL;
                    echo "  Instrument Number: " . ($property['property_info']['past_recording_info']['instrument_number'] ?? 'N/A') . PHP_EOL;
                    echo "  Lot: " . ($property['property_info']['past_recording_info']['lot'] ?? 'N/A') . PHP_EOL;
                    echo "  Block: " . ($property['property_info']['past_recording_info']['block'] ?? 'N/A') . PHP_EOL;
                    echo "  Book: " . ($property['property_info']['past_recording_info']['book'] ?? 'N/A') . PHP_EOL;
                    echo "  Page: " . ($property['property_info']['past_recording_info']['page'] ?? 'N/A') . PHP_EOL;
                    echo "  Legal Description: " . ($property['property_info']['past_recording_info']['legal_description'] ?? 'N/A') . PHP_EOL;
                }
            }

            echo "----------------------------------------\n" . PHP_EOL;
        }
    }

    /**
     * Retrieves recording information for a given file using the /recordings/:client_id/:client_connector_id/:file_id endpoint.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $fileId The file ID to use in the endpoint.
     * @return array Returns the recording information, or throws an exception on failure.
     * @throws \Exception
     */
    public function getRecordingInfo(string $clientId, string $connectorId, string $fileId): array
    {
        $endpoint = "/recordings/$clientId/$connectorId/$fileId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve recording information.');
    }

    /**
     * Prints the recording information retrieved by the getRecordingInfo method.
     *
     * @param array $recordingData The recording data to print.
     */
    public function printRecordingInfo(array $recordingData): void
    {
        foreach ($recordingData as $recording) {
            echo "File ID: " . $recording['file_id'] . PHP_EOL;
            echo "File Number: " . $recording['file_number'] . PHP_EOL;
            echo "Files URL: " . $recording['files_url'] . PHP_EOL;
            echo "Recording Partner Information:" . PHP_EOL;
            foreach ($recording['recording_partner'] as $partner) {
                echo "- Recording Office: " . $partner['recording_office'] . PHP_EOL;
                echo "- Address: " . $partner['address'] . PHP_EOL;
            }
            echo "Recording Instruments:" . PHP_EOL;
            foreach ($recording['recording_instruments'] as $instrumentType => $instruments) {
                echo "- Instrument Type: " . $instrumentType . PHP_EOL;
                foreach ($instruments as $instrument) {
                    echo "  Recording Instrument ID: " . $instrument['recording_instrument_id'] . PHP_EOL;
                    echo "  Sent for Recording: " . $instrument['sent_for_recording'] . PHP_EOL;
                    echo "  Recorded Date: " . $instrument['recorded_date'] . PHP_EOL;
                    echo "  Mortgage Amount: " . $instrument['mortgage_amount'] . PHP_EOL;

                    echo "  Fees:" . PHP_EOL;
                    echo "    Estimated Recording Fee: " . $instrument['fees']['estimated_recording_fee'] . PHP_EOL;
                    echo "    Actual Recording Fee: " . $instrument['fees']['actual_recording_fee'] . PHP_EOL;
                    echo "    Tax Amount: " . $instrument['fees']['tax_amount'] . PHP_EOL;
                    echo "    Amount Not Taxed: " . $instrument['fees']['amount_not_taxed'] . PHP_EOL;

                    echo "  Index Information:" . PHP_EOL;
                    echo "    Instrument Number: " . $instrument['index_info']['instrument_number'] . PHP_EOL;
                    echo "    Book: " . $instrument['index_info']['book'] . PHP_EOL;
                    echo "    Page: " . $instrument['index_info']['page'] . PHP_EOL;
                    echo "    Liber: " . $instrument['index_info']['liber'] . PHP_EOL;
                    echo "    Volume: " . $instrument['index_info']['volume'] . PHP_EOL;

                    echo "  Vesting Information:" . PHP_EOL;
                    echo "    Trustee: " . $instrument['vesting_info']['trustee'] . PHP_EOL;
                    echo "    Mortgagor: " . $instrument['vesting_info']['mortgagor'] . PHP_EOL;
                    echo "    Beneficiary: " . $instrument['vesting_info']['beneficiary'] . PHP_EOL;
                    echo "    Grantor: " . $instrument['vesting_info']['grantor'] . PHP_EOL;
                    echo "    Grantee: " . $instrument['vesting_info']['grantee'] . PHP_EOL;

                    echo "  Notes: " . $instrument['notes'] . PHP_EOL;
                    echo "----------------------------------------\n" . PHP_EOL;
                }
            }
        }
    }


    /**
     * Retrieves seller information using the /sellers/:client_id/:client_connector_id/:seller_id endpoint.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $sellerId The seller ID to use in the endpoint.
     * @return array Returns the seller information, or throws an exception on failure.
     * @throws \Exception
     */
    public function getSellerInfo(string $clientId, string $connectorId, string $sellerId): array
    {
        $endpoint = "/sellers/$clientId/$connectorId/$sellerId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve seller information.');
    }

    /**
     * Prints seller information returned by the getSellerInfo method.
     *
     * @param array $sellerData The seller data array returned from the API.
     */
    public function printSellerInfo(array $sellerData): void
    {
        foreach ($sellerData as $seller) {
            echo "File ID: " . ($seller['file_id'] ?? 'N/A') . PHP_EOL;
            echo "File Number: " . ($seller['file_number'] ?? 'N/A') . PHP_EOL;
            echo "Seller ID: " . ($seller['seller_id'] ?? 'N/A') . PHP_EOL;

            if (!empty($seller['seller_one'])) {
                echo "Seller One: " . ($seller['seller_one']['first_name'] ?? '') . " " . ($seller['seller_one']['middle_name'] ?? '') . " " . ($seller['seller_one']['last_name'] ?? '') . PHP_EOL;
            }

            if (!empty($seller['seller_two'])) {
                echo "Seller Two: " . ($seller['seller_two']['first_name'] ?? '') . " " . ($seller['seller_two']['middle_name'] ?? '') . " " . ($seller['seller_two']['last_name'] ?? '') . PHP_EOL;
            }

            if (!empty($seller['contact_info'])) {
                echo "Contact Information:" . PHP_EOL;
                echo "  Home Phone: " . ($seller['contact_info']['home_phone'] ?? 'N/A') . PHP_EOL;
                echo "  Cell Phone One: " . ($seller['contact_info']['cell_phone_one'] ?? 'N/A') . PHP_EOL;
                echo "  Cell Phone Two: " . ($seller['contact_info']['cell_phone_two'] ?? 'N/A') . PHP_EOL;
                echo "  Work Phone One: " . ($seller['contact_info']['work_phone_one'] ?? 'N/A') . PHP_EOL;
                echo "  Work Phone Two: " . ($seller['contact_info']['work_phone_two'] ?? 'N/A') . PHP_EOL;
                echo "  Fax One: " . ($seller['contact_info']['fax_one'] ?? 'N/A') . PHP_EOL;
                echo "  Fax Two: " . ($seller['contact_info']['fax_two'] ?? 'N/A') . PHP_EOL;
                echo "  Email Address One: " . ($seller['contact_info']['email_address_one'] ?? 'N/A') . PHP_EOL;
                echo "  Email Address Two: " . ($seller['contact_info']['email_address_two'] ?? 'N/A') . PHP_EOL;
                echo "  Mailing Address: " . ($seller['contact_info']['mailing_address'] ?? 'N/A') . PHP_EOL;
                echo "  Forwarding Address: " . ($seller['contact_info']['forwarding_address'] ?? 'N/A') . PHP_EOL;
            }

            echo "----------------------------------------" . PHP_EOL;
        }
    }


    /**
     * Retrieves settlement fee information for a specific file using the /settlement-fees/:client_id/:client_connector_id/:file_id endpoint.
     *
     * @param string $clientId The client ID.
     * @param string $connectorId The connector ID.
     * @param string $fileId The file ID to retrieve settlement fee information for.
     * @return array Returns the settlement fee information, or throws an exception on failure.
     * @throws \Exception
     */
    public function getFileSettlementFees(string $clientId, string $connectorId, string $fileId): array
    {
        $endpoint = "/settlement-fees/$clientId/$connectorId/$fileId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve settlement fee information.');
    }


    /**
     * Prints the settlement fee information returned by the getFileSettlementFees method.
     *
     * @param array $settlementData The settlement data array returned from the API.
     */
    public function printFileSettlementFees(array $settlementData): void
    {
        foreach ($settlementData as $settlement) {
            echo "File ID: " . ($settlement['file_id'] ?? 'N/A') . PHP_EOL;
            echo "File Number: " . ($settlement['file_number'] ?? 'N/A') . PHP_EOL;

            if (!empty($settlement['settlement_sections'])) {
                foreach ($settlement['settlement_sections'] as $sectionName => $items) {
                    echo "Section: " . $sectionName . PHP_EOL;

                    foreach ($items as $item) {
                        echo "  Line ID: " . ($item['line_id'] ?? 'N/A') . PHP_EOL;
                        echo "  Line Number: " . ($item['line_number'] ?? 'N/A') . PHP_EOL;
                        echo "  Line Item: " . ($item['line_item'] ?? 'N/A') . PHP_EOL;
                        echo "  Payee: " . ($item['payee'] ?? 'N/A') . PHP_EOL;
                        echo "  Buyer Debit: " . ($item['buyer_debit'] ?? 'N/A') . PHP_EOL;
                        echo "  Buyer Credit: " . ($item['buyer_credit'] ?? 'N/A') . PHP_EOL;
                        echo "  Seller Debit: " . ($item['seller_debit'] ?? 'N/A') . PHP_EOL;
                        echo "  Seller Credit: " . ($item['seller_credit'] ?? 'N/A') . PHP_EOL;
                        echo "  Total Amount POC: " . ($item['total_amount_poc'] ?? 'N/A') . PHP_EOL;
                        echo "  Buyer POC: " . ($item['buyer_poc'] ?? 'N/A') . PHP_EOL;
                        echo "  Seller POC: " . ($item['seller_poc'] ?? 'N/A') . PHP_EOL;
                        echo "  Other POC: " . ($item['other_poc'] ?? 'N/A') . PHP_EOL;
                        echo "  POC Party: " . ($item['poc_party'] ?? 'N/A') . PHP_EOL;
                        echo "----------------------------------------" . PHP_EOL;
                    }
                }
            }
        }
    }

    /**
     * Retrieves settlement information for a specific file using the /settlements/:client_id/:client_connector_id/:file_id endpoint.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $fileId The file ID to use in the endpoint.
     * @return array Returns the settlement information, or throws an exception on failure.
     * @throws \Exception
     */
    public function getSettlementInfo(string $clientId, string $connectorId, string $fileId): array
    {
        $endpoint = "/settlements/$clientId/$connectorId/$fileId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve settlement information.');
    }

    /**
     * Prints the settlement information returned by the getSettlementInfo method.
     *
     * @param array $settlementData The settlement data array returned from the API.
     */
    public function printSettlementInfo(array $settlementData): void
    {
        foreach ($settlementData as $settlement) {
            echo "File ID: " . ($settlement['file_id'] ?? 'N/A') . PHP_EOL;
            echo "File Number: " . ($settlement['file_number'] ?? 'N/A') . PHP_EOL;
            echo "Files URL: " . ($settlement['files_url'] ?? 'N/A') . PHP_EOL;

            if (!empty($settlement['settlement_info']['Signing_1'])) {
                foreach ($settlement['settlement_info']['Signing_1'] as $signing) {
                    echo "Settlement Number: " . ($signing['settlement_number'] ?? 'N/A') . PHP_EOL;
                    echo "Closing Office: " . ($signing['closing_office'] ?? 'N/A') . PHP_EOL;
                    echo "Scheduled Date: " . ($signing['scheduled_date'] ?? 'N/A') . PHP_EOL;
                    echo "Settlement Type: " . ($signing['settlement_type'] ?? 'N/A') . PHP_EOL;
                    echo "Settlement Requested By Company: " . ($signing['settlement_requested_by_company'] ?? 'N/A') . PHP_EOL;

                    if (!empty($signing['signing_agent_info'])) {
                        echo "Signing Agent Information:" . PHP_EOL;
                        echo "  Agent Type: " . ($signing['signing_agent_info']['agent_type'] ?? 'N/A') . PHP_EOL;
                        echo "  Agent Name: " . ($signing['signing_agent_info']['agent_name'] ?? 'N/A') . PHP_EOL;
                        echo "  Phone Number: " . ($signing['signing_agent_info']['phone_number'] ?? 'N/A') . PHP_EOL;
                        echo "  Email: " . ($signing['signing_agent_info']['email'] ?? 'N/A') . PHP_EOL;
                        echo "  Special Instructions: " . ($signing['signing_agent_info']['special_instructions'] ?? 'N/A') . PHP_EOL;
                        echo "  File Partners URL: " . ($signing['signing_agent_info']['file_partners_url'] ?? 'N/A') . PHP_EOL;
                    }

                    if (!empty($signing['settlement_party'])) {
                        echo "Settlement Party Information:" . PHP_EOL;
                        echo "  Party Name: " . ($signing['settlement_party']['party_name'] ?? 'N/A') . PHP_EOL;
                        echo "  Phone: " . ($signing['settlement_party']['phone'] ?? 'N/A') . PHP_EOL;
                        echo "  Work Phone: " . ($signing['settlement_party']['work_phone'] ?? 'N/A') . PHP_EOL;
                        echo "  Cell Phone: " . ($signing['settlement_party']['cell_phone'] ?? 'N/A') . PHP_EOL;
                        echo "  Email Address: " . ($signing['settlement_party']['email_address'] ?? 'N/A') . PHP_EOL;
                    }

                    if (!empty($signing['location_info'])) {
                        echo "Location Information:" . PHP_EOL;
                        echo "  Settlement Location: " . ($signing['location_info']['settlement_location'] ?? 'N/A') . PHP_EOL;
                        echo "  Settlement Address: " . ($signing['location_info']['settlement_address'] ?? 'N/A') . PHP_EOL;
                        echo "  Settlement County: " . ($signing['location_info']['settlement_county'] ?? 'N/A') . PHP_EOL;
                    }

                    if (!empty($signing['settlement_status'])) {
                        echo "Settlement Status:" . PHP_EOL;
                        echo "  Settlement Verified: " . ($signing['settlement_status']['settlement_verified'] ?? 'N/A') . PHP_EOL;
                        echo "  Settlement Docs Drawn: " . ($signing['settlement_status']['settlement_docs_drawn'] ?? 'N/A') . PHP_EOL;
                        echo "  Settlement Docs Printed: " . ($signing['settlement_status']['settlement_docs_printed'] ?? 'N/A') . PHP_EOL;
                        echo "  Settlement Completed: " . ($signing['settlement_status']['settlement_completed'] ?? 'N/A') . PHP_EOL;
                        echo "  Settlement Not Completed: " . ($signing['settlement_status']['settlement_not_completed'] ?? 'N/A') . PHP_EOL;
                        echo "  Settlement Canceled: " . ($signing['settlement_status']['settlement_canceled'] ?? 'N/A') . PHP_EOL;
                    }

                    echo "----------------------------------------" . PHP_EOL;
                }
            }
        }
    }


    /**
     * Retrieves policy information for a specific file using the /policies/:client_id/:client_connector_id/:file_id endpoint.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $fileId The file ID to use in the endpoint.
     * @return array Returns the settlement information, or throws an exception on failure.
     * @throws \Exception
     */    
    public function getPolicyInfo(string $clientId, string $connectorId, string $fileId): array
    {
        $endpoint = "/policies/$clientId/$connectorId/$fileId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve policy information.');
    }


     /**
     * Prints the policy information returned by the getPolicyInfo method.
     *
     * @param array $policyData The settlement data array returned from the API.
     */   
    public function printPolicyInfo(array $policyData): void
    {
        foreach ($policyData as $policy) {
            echo "File ID: " . ($policy['file_id'] ?? 'N/A') . PHP_EOL;
            echo "File Number: " . ($policy['file_number'] ?? 'N/A') . PHP_EOL;
            echo "Files URL: " . ($policy['files_url'] ?? 'N/A') . PHP_EOL;
            echo "Recordings URL: " . ($policy['recordings_url'] ?? 'N/A') . PHP_EOL;
            echo "Disbursements URL: " . ($policy['disbursements_url'] ?? 'N/A') . PHP_EOL;

            if (isset($policy['policies'])) {
                foreach ($policy['policies'] as $policyType => $policies) {
                    echo "Policy Type: " . $policyType . PHP_EOL;
                    foreach ($policies as $policyDetails) {
                        echo "  Policy ID: " . ($policyDetails['policy_id'] ?? 'N/A') . PHP_EOL;
                        echo "  Policy Number: " . ($policyDetails['policy_number'] ?? 'N/A') . PHP_EOL;
                        echo "  Issue Date: " . ($policyDetails['issue_date'] ?? 'N/A') . PHP_EOL;
                        echo "  Effective Date: " . ($policyDetails['effective_date'] ?? 'N/A') . PHP_EOL;

                        if (isset($policyDetails['underwriter_info'])) {
                            echo "  Underwriter Information:" . PHP_EOL;
                            echo "    Underwriter ID: " . ($policyDetails['underwriter_info']['underwriter_id'] ?? 'N/A') . PHP_EOL;
                            echo "    Underwriter Name: " . ($policyDetails['underwriter_info']['underwriter_name'] ?? 'N/A') . PHP_EOL;
                            echo "    Phone: " . ($policyDetails['underwriter_info']['phone'] ?? 'N/A') . PHP_EOL;
                            echo "    Email: " . ($policyDetails['underwriter_info']['email'] ?? 'N/A') . PHP_EOL;
                            echo "    Address: " . ($policyDetails['underwriter_info']['address'] ?? 'N/A') . PHP_EOL;
                            echo "    File Partners URL: " . ($policyDetails['underwriter_info']['file_partners_url'] ?? 'N/A') . PHP_EOL;
                        }

                        if (isset($policyDetails['property_info'])) {
                            echo "  Property Information:" . PHP_EOL;
                            echo "    Property ID: " . ($policyDetails['property_info']['property_id'] ?? 'N/A') . PHP_EOL;
                            echo "    Property Type: " . ($policyDetails['property_info']['property_type'] ?? 'N/A') . PHP_EOL;
                            echo "    Street Address: " . ($policyDetails['property_info']['street_address'] ?? 'N/A') . PHP_EOL;
                            echo "    County: " . ($policyDetails['property_info']['county'] ?? 'N/A') . PHP_EOL;
                            echo "    Properties URL: " . ($policyDetails['property_info']['properties_url'] ?? 'N/A') . PHP_EOL;
                        }

                        echo "----------------------------------------\n" . PHP_EOL;
                    }
                }
            }
        }
    }


    /**
     * Client Specific Custom Endpoint Example that looks up a Referral Partner's information when passed a name /custom/shaddock/:client_id/:connector_id/lookup-referral-agent/:referral_agent_name endpoint.
     *
     * @param string $clientId The client ID to use in the endpoint.
     * @param string $connectorId The connector ID to use in the endpoint.
     * @param string $referralAgentName The name of the referral agent to lookup - will automatically replace spaces with underscores.
     * @return array Returns the agent's unique ID, name and other applicable information.
     * @throws \Exception
     */
    public function customLookupReferralAgent(string $clientId, string $connectorId, string $referralAgentName): array
    {
        $formattedAgentName = str_replace(' ', '_', $referralAgentName);
        $endpoint = "/custom/shaddock/$clientId/$connectorId/lookup-referral-agent/$formattedAgentName";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve referral agent information.');
    }


    /**
     * Prints the referral agent information returned by the customLookupReferralAgent method.
     *
     * @param array $referralAgentData The referral agent data array returned from the API.
     */
    public function printCustomLookupReferralAgent(array $referralAgentData): void
    {
        foreach ($referralAgentData as $data) {
            // Print the Referral Agent Sales Volume URL
            echo "Referral Agent Sales Volume URL: " . ($data['referral-agent-sales-volume'] ?? 'N/A') . PHP_EOL;

            // Check if agent_info key exists and print the details of each agent
            if (isset($data['agent_info'])) {
                echo "Referral Agents Information:" . PHP_EOL;

                foreach ($data['agent_info'] as $agent) {
                    echo "Agent ID: " . ($agent['agent_id'] ?? 'N/A') . PHP_EOL;
                    echo "Agent Name: " . ($agent['agent_name'] ?? 'N/A') . PHP_EOL;
                    echo "Agent Contact: " . ($agent['agent_contact'] ?? 'N/A') . PHP_EOL;
                    echo "Total Orders in the Past Year: " . ($agent['total_orders_in_the_past_year'] ?? 'N/A') . PHP_EOL;
                    echo "Last Order Date in the Past Year: " . ($agent['last_order_date_in_the_past_year'] ?? 'N/A') . PHP_EOL;
                    echo "----------------------------------------\n" . PHP_EOL;
                }
            } else {
                echo "No Referral Agents Information Found." . PHP_EOL;
            }

            echo "========================================\n" . PHP_EOL;
        }
    }


    /**
     * Client Specific Custom Endpoint Example that returns a Referral Agent's Sales Volume and other Sale Info when passing the Referral Agent ID
     *  /custom/shaddock/:client_id/:client_connector_id/referral-agent-sales-volume/:referral_agent_id endpoint.
     *
     * @param string  $clientId The client ID to use in the endpoint.
     * @param string  $connectorId The connector ID to use in the endpoint.
     * @param integer $referralAgentId The unique ID of the referral agent that you want to return sales volume information on.
     * @return array  Returns the agent's unique ID, name and other applicable information.
     * @throws \Exception
     */
    public function customGetReferralAgentSalesVolume(string $clientId, string $connectorId, string $referralAgentId): array
    {
        $endpoint = "/custom/shaddock/$clientId/$connectorId/referral-agent-sales-volume/$referralAgentId";

        $response = $this->getData($endpoint);

        if ($response['success'] === true && $response['status_code'] === 200) {
            return $response['data'];
        }

        if (isset($response['data'][0])) {
            $error = $response['data'][0];
            throw new \Exception("Error Code: {$error['code']} - {$error['name']}. Error: {$error['message']}. Resolution: {$error['resolution']}");
        }

        throw new \Exception('Failed to retrieve referral agent sales volume information.');
    }


    /**
     * Prints the referral agent sales volume information returned by the customGetReferralAgentSalesVolume method.
     *
     * @param array $referralAgentSalesData The referral agent data array returned from the API.
     */
    public function printCustomGetReferralAgentSalesVolume(array $salesVolumeData): void
    {
        foreach ($salesVolumeData as $agentData) {
            echo "Referral Agent ID: " . ($agentData['referral_agent_id'] ?? 'N/A') . PHP_EOL;
            echo "Referral Agent Name: " . ($agentData['referral_agent_name'] ?? 'N/A') . PHP_EOL;
            echo "Referral Agent Contact: " . ($agentData['referral_agent_contact'] ?? 'N/A') . PHP_EOL;
            echo "Lifetime Orders: " . ($agentData['lifetime_orders'] ?? 'N/A') . PHP_EOL;
            echo "Lifetime Closed Orders: " . ($agentData['lifetime_closed_orders'] ?? 'N/A') . PHP_EOL;
            echo "Lifetime Closed Sales Volume: " . ($agentData['lifetime_closed_sales_volume'] ?? 'N/A') . PHP_EOL;
            echo "New Orders Current Year: " . ($agentData['new_orders_current_year'] ?? 'N/A') . PHP_EOL;
            echo "Closed Orders Current Year: " . ($agentData['closed_orders_current_year'] ?? 'N/A') . PHP_EOL;
            echo "Sales Volume Current Year: " . ($agentData['sales_volume_current_year'] ?? 'N/A') . PHP_EOL;
            echo "New Orders Current Month: " . ($agentData['new_orders_current_month'] ?? 'N/A') . PHP_EOL;
            echo "Closed Orders Current Month: " . ($agentData['closed_orders_current_month'] ?? 'N/A') . PHP_EOL;
            echo "Sales Volume Current Month: " . ($agentData['sales_volume_current_month'] ?? 'N/A') . PHP_EOL;
            echo "New Orders Last 30 Days: " . ($agentData['new_orders_last_30_days'] ?? 'N/A') . PHP_EOL;
            echo "Closed Orders Last 30 Days: " . ($agentData['closed_orders_last_30_days'] ?? 'N/A') . PHP_EOL;
            echo "Sales Volume Last 30 Days: " . ($agentData['sales_volume_last_30_days'] ?? 'N/A') . PHP_EOL;

            echo "----------------------------------------\n" . PHP_EOL;
        }
    }




}