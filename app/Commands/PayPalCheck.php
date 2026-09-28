<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PayPalCheck extends BaseCommand
{
    protected $group = 'Billing';
    protected $name = 'paypal:check';
    protected $description = 'Check PayPal checkout configuration without exposing credentials.';

    public function run(array $params)
    {
        $config = config('PayPal');
        CLI::write('Environment: ' . $config->environment);
        foreach (['clientId', 'secret', 'webhookId', 'standardPlanId', 'alphaPlanId', 'standardTrialPlanId', 'alphaTrialPlanId', 'trialHashKey'] as $field) {
            CLI::write('PayPal.' . $field . ': ' . ($config->$field === '' ? 'MISSING' : 'set'));
        }
        CLI::write('PayPal.enabled: ' . ($config->enabled ? 'true' : 'false'));
        CLI::write('Checkout configuration: ' . ($config->ready() ? 'ready' : 'incomplete'));
        CLI::write('14-day trial configuration: ' . ($config->trialsReady() ? 'ready' : 'incomplete'));
        if ($config->ready() && CLI::getOption('remote')) {
            $client = new \App\Services\PayPalClient();
            $billing = new \App\Services\PayPalBilling($client);
            foreach (['standardPlanId' => 999, 'alphaPlanId' => 499, 'standardTrialPlanId' => 999, 'alphaTrialPlanId' => 499] as $field => $amount) {
                try {
                    $plan = $client->request('GET', '/v1/billing/plans/' . rawurlencode($config->$field));
                    $billing->validatePlan($plan, $amount, str_contains($field, 'Trial'));
                    CLI::write($field . ': remote plan verified');
                } catch (\RuntimeException $e) {
                    CLI::error($field . ': ' . $e->getMessage());
                    if ($previous = $e->getPrevious()) {
                        CLI::error('Transport failure: ' . get_class($previous) . ' at ' . basename($previous->getFile()) . ':' . $previous->getLine());
                    }
                }
            }
        }
        $url = site_url('webhooks/paypal');
        CLI::write('Webhook endpoint: ' . $url);
        $host = parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            CLI::error('Set app.baseURL to the public HTTPS staging URL for PayPal webhook delivery.');
        }
        CLI::write('This checks local configuration only. Complete the sandbox acceptance tests in docs/PAYPAL_SETUP.md before launch.');
    }
}
