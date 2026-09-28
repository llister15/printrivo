<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;
class PayPal extends BaseConfig
{
    public bool $enabled = false;
    public string $environment = 'sandbox';
    public string $clientId = '';
    public string $secret = '';
    public string $webhookId = '';
    public string $standardPlanId = '';
    public string $alphaPlanId = '';
    public string $currency = 'USD';
    public string $standardTrialPlanId = '';
    public string $alphaTrialPlanId = '';
    public string $trialHashKey = '';

    public function trialsReady(): bool
    {
        $ids = [$this->standardPlanId, $this->alphaPlanId, $this->standardTrialPlanId, $this->alphaTrialPlanId];
        return $this->ready() && strlen($this->trialHashKey) >= 32
            && preg_match('/^P-[A-Z0-9]+$/', $this->standardTrialPlanId)
            && preg_match('/^P-[A-Z0-9]+$/', $this->alphaTrialPlanId)
            && count(array_unique($ids)) === 4;
    }

    protected function getEnvValue(string $property, string $prefix, string $shortPrefix)
    {
        // CI uses lowercase short prefixes. Preserve the PayPal.* names shipped
        // in our setup guide, while letting native paypal.* settings take priority.
        $value = parent::getEnvValue($property, $prefix, $shortPrefix);
        return $value ?? parent::getEnvValue($property, $prefix, 'PayPal');
    }

    public function ready(): bool
    {
        return $this->enabled && in_array($this->environment, ['sandbox', 'live'], true)
            && $this->clientId !== '' && $this->secret !== '' && $this->webhookId !== ''
            && preg_match('/^P-[A-Z0-9]+$/', $this->standardPlanId)
            && preg_match('/^P-[A-Z0-9]+$/', $this->alphaPlanId)
            && $this->standardPlanId !== $this->alphaPlanId && $this->currency === 'USD';
    }
    public function apiBase(): string
    {
        return $this->environment === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }
}
