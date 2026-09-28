<?php
namespace Config;
use CodeIgniter\Config\BaseConfig;
class BugReports extends BaseConfig
{
    public string $recipient = '';

    public function ready(): bool
    {
        $email=config('Email');
        return filter_var($this->recipient,FILTER_VALIDATE_EMAIL)!==false
            && filter_var($email->fromEmail,FILTER_VALIDATE_EMAIL)!==false
            && in_array($email->protocol,['smtp','sendmail','mail'],true)
            && ($email->protocol!=='smtp' || $email->SMTPHost!=='');
    }
}
