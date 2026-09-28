<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;
class CswPrint extends BaseConfig
{
    public string $token = '';
    public const ENDPOINT = 'https://app.cswprint.com/index.php/webhooks/orders';
    public const MAX_BYTES = 8 * 1024 * 1024;
    public const MAX_PIXELS = 100000000;
    public function ready(): bool { return trim($this->token) !== ''; }
}
