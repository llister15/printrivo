<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;
class Analytics extends BaseConfig
{
    public bool $enabled = false;
    public string $measurementId = '';
    public function ready(): bool
    {
        return $this->enabled && preg_match('/\AG-[A-Z0-9]{5,20}\z/', $this->measurementId) === 1;
    }
}
