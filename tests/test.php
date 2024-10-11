<?php

use OpsInsights\Auth;
use OpsInsights\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testGetData()
    {
        $auth = new Auth('https://app.opsinsights.com/api', 'client_id', 'client_secret');
        $auth->authenticate();
        
        $client = new Client($auth);
        $data = $client->getData('/some/endpoint');
        
        $this->assertIsArray($data);
    }
}
