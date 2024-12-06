<?php
require 'vendor/autoload.php';

use OpsInsights\Auth;
use OpsInsights\Client;

$apiBaseUrl = "https://app.opsinsights.com";
$client_key = 'your_key';
$client_secret = 'your_secret';

// Initialize the Auth class and authenticate
$auth = new Auth($apiBaseUrl, $client_key, $client_secret);
$auth->authenticate();

// Initialize the Client class
$client = new Client($auth);

// Retrieve the Needed ClientID and ConnectorID(s) information used in other API endpoints / methods
$clientInfo = $client->getMyClientId();
$myClientId = $clientInfo->client_id;
$myConnectorId = $client->getConnectorId();


// Print Key Info and Connectors Available to Your API Key
$client->printMyKeyInfoAndAvailableConnectors();

// List all available API endpoints
// $endpoints = $client->listApiEndpoints();

// if ($endpoints) {
//     echo "Available API Endpoints: \n" . PHP_EOL;
//     foreach ($endpoints as $endpoint) {
//         echo "API Version: " . $endpoint['api_version'] . PHP_EOL;
//         echo "HTTP Verb: " . $endpoint['http_verb_name'] . PHP_EOL;
//         echo "Endpoint Name: " . $endpoint['endpoint_name'] . PHP_EOL;
//         echo "Description: " . $endpoint['endpoint_description'] . PHP_EOL;
//         echo "Documentation: " . $endpoint['developer_documentation_link'] . PHP_EOL;
//         echo "----------------------------------------\n" . PHP_EOL;
//     }
// } else {
//     echo "Failed to retrieve API endpoints.";
// }


// Lookup a File by a given address
// $myExampleFile = $client->fileLookupByAddress('123 Main Street, Columbia, SC  29212',$myClientId,$myConnectorId);

// if ($myExampleFile) {
//     echo "Example File(s) Information Returned by Address Lookup:\n\n" . PHP_EOL;
//     $client->printAddressesFromLookup($myExampleFile);
// } else {
//     echo "Failed to retrieve file information.";
// }



// Now lookup a file by a returned FileID (example FileID=492524)
// echo "\n\n" . PHP_EOL;
// echo "Looking up File Information based on a FileID (ex. 492524)\n" . PHP_EOL;
// $myFile = $client->fileLookupByFileId($myClientId,$myConnectorId,'492524');
// echo "File info retrieved.\n" . PHP_EOL;
// echo "Printing out File Info for File Looked Up by FileID: \n" . PHP_EOL;
// $client->printFileInfo($myFile);


// Now lookup a file by a Lender Loan Number (example Lender File # 265410444)
// echo "\n\n" . PHP_EOL;
// echo "Looking up File Information from a Lender's Loan Number\n" . PHP_EOL;
// $myLenderFile = $client->fileLookupByLenderLoanNumber($myClientId,$myConnectorId,'265410444');
// echo "Lender file info retrieved.\n" . PHP_EOL;
// echo "Printing the File(s) from Lender Loan Lookup to Standard Out: \n" . PHP_EOL;
// $client->printLenderFileInfo($myLenderFile);


// Now lookup all Partners on a File using a FileID
// echo "\n\n" . PHP_EOL;
// echo "Looking up All Partners on a given file" . PHP_EOL;
// $myFilePartners = $client->getAllPartnersOnaFile($myClientId,$myConnectorId,'168953');
// echo "File partners retrieved.\n" . PHP_EOL;
// echo "Printing All File Partner Information Inputted for the given file: \n" . PHP_EOL;
// $client->printAllPartnersOnaFile($myFilePartners);


// Now lookup all information on a given Buyer
// echo "\n\n" . PHP_EOL;
// echo "Looking up information for a given Buyer" . PHP_EOL;
// $myBuyerInfo = $client->getBuyersInfo($myClientId,$myConnectorId,'405605');
// echo "Buyer Info retrieved.\n" . PHP_EOL;
// echo "Printing info about the given Buyer(s) that is in the system: \n" . PHP_EOL;
// $client->printBuyersInfo($myBuyerInfo);


// Now lookup all information for a Disbursement of a file
// echo "\n\n" . PHP_EOL;
// echo "Looking up Disbursement information for a given file" . PHP_EOL;
// $myDisbursementInfo = $client->getDisbursementInfo($myClientId,$myConnectorId,'854922');
// echo "Disbursement Info retrieved.\n" . PHP_EOL;
// echo "Printing info about the Disbursements for this file that are in the system: \n" . PHP_EOL;
// $client->printDisbursementInfo($myDisbursementInfo);


// Now lookup all information for a given Property using the PropertyID
// echo "\n\n" . PHP_EOL;
// echo "Looking up Property information for a given PropertyID" . PHP_EOL;
// $myPropertyInfo = $client->getPropertyInfo($myClientId,$myConnectorId,'491491');
// echo "Property Info retrieved.\n" . PHP_EOL;
// echo "Printing info about the Property that is saved in the system: \n" . PHP_EOL;
// $client->printPropertyInfo($myPropertyInfo);


// Lookup Recording information for a given FileID
// echo "\n\n" . PHP_EOL;
// echo "Looking up Recording information for a given FileID" . PHP_EOL;
// $myRecordingInfo = $client->getRecordingInfo($myClientId,$myConnectorId,'589201');
// echo "Recording Info retrieved.\n" . PHP_EOL;
// echo "Printing info about the Recording that is saved in the system: \n" . PHP_EOL;
// $client->printRecordingInfo($myRecordingInfo);


// Now lookup all information on a given Seller
// echo "\n\n" . PHP_EOL;
// echo "Looking up information for a given Seller" . PHP_EOL;
// $mySellerInfo = $client->getSellerInfo($myClientId,$myConnectorId,'1600483');
// echo "Seller Info retrieved.\n" . PHP_EOL;
// echo "Printing info about the given Seller(s) that is in the system: \n" . PHP_EOL;
// $client->printSellerInfo($mySellerInfo);


// Lookup Settlement Fee Data for a given FileID
// echo "\n\n" . PHP_EOL;
// echo "Looking up Settlement Fee Information for a given FileID" . PHP_EOL;
// $mySettlementFeeData = $client->getFileSettlementFees($myClientId,$myConnectorId,'56522');
// echo "Settlement Fee Data retrieved.\n" . PHP_EOL;
// echo "Printing info about the Settlement Fees for this file: \n" . PHP_EOL;
// $client->printFileSettlementFees($mySettlementFeeData);


// Lookup Settlement Info for a given FileID
// echo "\n\n" . PHP_EOL;
// echo "Looking up Settlement Information for a given FileID" . PHP_EOL;
// $mySettlementInfo = $client->getSettlementInfo($myClientId,$myConnectorId,'438364');
// echo "Settlement Info retrieved.\n" . PHP_EOL;
// echo "Printing info about the Settlement for this file: \n" . PHP_EOL;
// $client->printSettlementInfo($mySettlementInfo);


// Lookup Policy Info for a given FileID
// echo "\n\n" . PHP_EOL;
// echo "Looking up Policy Information for a given FileID" . PHP_EOL;
// $myPolicyInfo = $client->getPolicyInfo($myClientId,$myConnectorId,'438364');
// echo "Policy Info retrieved.\n" . PHP_EOL;
// echo "Printing info about the Policies Issued for this file: \n" . PHP_EOL;
// $client->printPolicyInfo($myPolicyInfo);


// Demonstrating Custom Endpoint - Looking up a Referral Agent's ID and info from RamQuest
echo "\n\n" . PHP_EOL;
echo "Looking up Referral Agent records in the system that match 'Joseph Martin'." . PHP_EOL;
$myCustomReferralAgentLookupData = $client->customLookupReferralAgent($myClientId,$myConnectorId,'Joseph Martin');
echo "Matching Referral Agent records retrieved.\n" . PHP_EOL;
echo "Printing records received that are a match for that Referral Agent returned by the system: \n" . PHP_EOL;
$client->printCustomLookupReferralAgent($myCustomReferralAgentLookupData);


// Demonstrating Custom Endpoint - Looking up a Referral Agent's Sales Volume and other summary sales information using the Referral Agent's ID
echo "\n\n" . PHP_EOL;
echo "Looking up the Sales Volume and other Summary Sales Info for a given Referral Agent ID - 403396221" . PHP_EOL;
$myCustomReferralAgentSalesVolumeData = $client->customGetReferralAgentSalesVolume($myClientId,$myConnectorId,'403396221');
echo "Sales Volume Data Retieved!.\n" . PHP_EOL;
echo "Printing info about the Sales Volume for this Referral Agent: \n" . PHP_EOL;
$client->printCustomGetReferralAgentSalesVolume($myCustomReferralAgentSalesVolumeData);

