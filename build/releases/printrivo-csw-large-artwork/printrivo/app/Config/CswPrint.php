<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;
class CswPrint extends BaseConfig
{
    public string $token = '';
    public const ENDPOINT = 'https://app.cswprint.com/index.php/webhooks/dtf/orders';
    public const INLINE_BYTES = 8 * 1024 * 1024;
    public const MAX_BYTES = 500 * 1024 * 1024;
    public const MAX_PIXELS = 256000000;
    public function ready(): bool { return trim($this->token) !== ''; }
}
