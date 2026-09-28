<?php
namespace App\Services;

class MemberMailer
{
    public function send(string $email,string $subject,string $message): bool
    {
        $config=config('Email');
        if (!filter_var($config->fromEmail,FILTER_VALIDATE_EMAIL)
            || ($config->protocol==='smtp' && $config->SMTPHost==='')) { return false; }
        $mailer=service('email');
        try {
            $mailer->clear(true);
            $mailer->setMailType('text');
            $mailer->setFrom($config->fromEmail,$config->fromName ?: 'Printrivo');
            $mailer->setTo($email);
            $mailer->setSubject($subject);
            $mailer->setMessage($message);
            return $mailer->send(false);
        } catch (\Throwable) { return false; }
        finally { $mailer->clear(true); }
    }
}
