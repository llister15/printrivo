<?php
namespace App\Services;
use Config\PayPal;
use RuntimeException;
class PayPalClient
{
    private ?string $token = null;
    public function __construct(private ?PayPal $config = null) { $this->config ??= config('PayPal'); }

    public function request(string $method, string $path, ?array $body = null, ?string $requestId = null): array
    {
        if (!$this->config->ready()) { throw new RuntimeException('PayPal is not configured.'); }
        if ($this->token === null) {
            $result = $this->send('POST', '/v1/oauth2/token', [
                'auth' => [$this->config->clientId, $this->config->secret, 'basic'],
                'form_params' => ['grant_type' => 'client_credentials'],
            ]);
            $this->token = $result['access_token'] ?? null;
            if (!$this->token) { throw new RuntimeException('PayPal authentication failed.'); }
        }
        $options = ['headers' => ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json']];
        if ($requestId) { $options['headers']['PayPal-Request-Id'] = $requestId; }
        if ($body !== null) { $options['json'] = $body; }
        return $this->send($method, $path, $options);
    }

    private function send(string $method, string $path, array $options): array
    {
        try {
            $response = service('curlrequest', [], null, null, false)->request($method, $this->config->apiBase() . $path, $options + [
                'timeout' => 25, 'connect_timeout' => 10, 'http_errors' => false, 'allow_redirects' => false,
            ]);
        } catch (\Throwable $e) { throw new RuntimeException('PayPal could not be reached. Please try again shortly.', 0, $e); }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            // Never expose or log provider payloads, credentials or customer details.
            throw new RuntimeException('PayPal could not complete the request. Please try again or contact support.');
        }
        if ($response->getStatusCode() === 204) { return []; }
        $data = json_decode($response->getBody(), true);
        if (!is_array($data)) { throw new RuntimeException('PayPal returned an invalid response.'); }
        return $data;
    }

    public function verify(array $headers, array $event): bool
    {
        foreach (['auth_algo','cert_url','transmission_id','transmission_sig','transmission_time'] as $field) {
            if (empty($headers[$field])) { return false; }
        }
        $result = $this->request('POST', '/v1/notifications/verify-webhook-signature', $headers + [
            'webhook_id' => $this->config->webhookId, 'webhook_event' => $event,
        ]);
        return ($result['verification_status'] ?? '') === 'SUCCESS';
    }
}
