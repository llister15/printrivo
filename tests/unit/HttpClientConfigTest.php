<?php

use CodeIgniter\Test\CIUnitTestCase;

final class HttpClientConfigTest extends CIUnitTestCase
{
    public function testHttpClientInitializesWithAvailableCurlSharingFeatures(): void
    {
        $config = new \Config\CURLRequest();
        $this->assertContains(CURL_LOCK_DATA_DNS, $config->shareConnectionOptions);
        $this->assertInstanceOf(
            \CodeIgniter\HTTP\CURLRequest::class,
            \Config\Services::curlrequest([], null, null, false)
        );
    }
}
