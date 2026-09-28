<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\PayPal;

final class PayPalConfigTest extends CIUnitTestCase
{
    public function testDocumentedMixedCaseSettingsLoadAndNativePrefixTakesPriority(): void
    {
        $original = $_ENV;
        try {
            foreach (['enabled' => 'true', 'environment' => 'sandbox', 'clientId' => 'test-client',
                'secret' => 'test-secret', 'webhookId' => 'test-webhook',
                'standardPlanId' => 'P-STANDARD', 'alphaPlanId' => 'P-ALPHA', 'currency' => 'USD'] as $key => $value) {
                $_ENV['PayPal.' . $key] = $value;
            }
            $config = new PayPal();
            $this->assertTrue($config->ready());
            $this->assertSame('test-client', $config->clientId);
            $_ENV['paypal.enabled'] = 'false';
            $this->assertFalse((new PayPal())->ready());
        } finally {
            $_ENV = $original;
        }
    }
}
