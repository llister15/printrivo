<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class CURLRequest extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * CURLRequest Share Connection Options
     * --------------------------------------------------------------------------
     *
     * Share connection options between requests.
     *
     * @var list<int>
     *
     * @see https://www.php.net/manual/en/curl.constants.php#constant.curl-lock-data-connect
     */
    public array $shareConnectionOptions = [
        CURL_LOCK_DATA_DNS,
    ];

    /**
     * --------------------------------------------------------------------------
     * CURLRequest Share Options
     * --------------------------------------------------------------------------
     *
     * Whether share options between requests or not.
     *
     * If true, all the options won't be reset between requests.
     * It may cause an error request with unnecessary headers.
     */
    public bool $shareOptions = false;

    public function __construct()
    {
        // Some supported PHP/cURL builds omit connection sharing. DNS sharing
        // remains available; do not prevent all outbound HTTP requests.
        if (defined('CURL_LOCK_DATA_CONNECT')) {
            $this->shareConnectionOptions[] = constant('CURL_LOCK_DATA_CONNECT');
        }
        parent::__construct();
    }
}
