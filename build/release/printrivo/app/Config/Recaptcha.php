<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;
class Recaptcha extends BaseConfig
{
    public bool $enabled = false;
    public string $siteKey = '';
    public string $secretKey = '';
    public string $hostname = '';
    public float $minimumScore = 0.5;

    public function ready(): bool
    {
        return $this->siteKey !== '' && $this->secretKey !== ''
            && preg_match('/^[a-zA-Z0-9.-]+$/D', $this->hostname)
            && $this->minimumScore >= 0 && $this->minimumScore <= 1;
    }
}
