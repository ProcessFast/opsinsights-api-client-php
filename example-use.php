<?php
require 'vendor/autoload.php';

use OpsInsights\Auth;
use OpsInsights\Client;

// Initialize the Auth class and authenticate
$auth = new Auth('https://dev.opsinsights.com', 'TwDvl9NB1mxmV6QeviT9cxoBwigylxfp', '8wcPkPrEHl5_wxrJzJRuF6h7NHdFe4Hz');
$auth->authenticate();

// Initialize the Client class
$client = new Client($auth);

// Retrieve the client's information
$clientInfo = $client->getMyClientId();

if ($clientInfo) {
    echo "Client Name: " . $clientInfo->client_name . PHP_EOL;
    echo "Client ID: " . $clientInfo->client_id . PHP_EOL;
    echo "" . PHP_EOL;

    // Example of retrieving the stored client ID
    echo "Stored Client ID: " . $client->getClientId() . PHP_EOL;
} else {
    echo "Failed to retrieve client information.";
}

// List all available API endpoints
$endpoints = $client->listApiEndpoints();

if ($endpoints) {
    echo "Available API Endpoints:" . PHP_EOL;
    foreach ($endpoints as $endpoint) {
        echo "API Version: " . $endpoint['api_version'] . PHP_EOL;
        echo "HTTP Verb: " . $endpoint['http_verb_name'] . PHP_EOL;
        echo "Endpoint Name: " . $endpoint['endpoint_name'] . PHP_EOL;
        echo "Description: " . $endpoint['endpoint_description'] . PHP_EOL;
        echo "Documentation: " . $endpoint['developer_documentation_link'] . PHP_EOL;
        echo "----------------------------------------" . PHP_EOL;
    }
} else {
    echo "Failed to retrieve API endpoints.";
}

