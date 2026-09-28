<?php
namespace App\Services;
use CodeIgniter\Shield\Entities\User;
class SignupNotifier
{
    public function send(User $user): bool
    {
        $config=config('Email');
        if (!filter_var($config->fromEmail,FILTER_VALIDATE_EMAIL)
            || ($config->protocol==='smtp' && $config->SMTPHost==='')) { return false; }
        $mailer=service('email');
        try {
            $mailer->clear(true);
            $mailer->setMailType('text');
            $mailer->setFrom($config->fromEmail,$config->fromName ?: 'Printrivo');
            $mailer->setTo('info@mberdigital.com');
            $mailer->setSubject('[Printrivo] New user signup');
            $mailer->setMessage("A new Printrivo account has been created.\n\nName: ".trim($user->first_name.' '.$user->last_name)
                ."\nEmail: ".$user->email."\nAccount ID: ".$user->id."\nSigned up (UTC): ".gmdate('Y-m-d H:i:s')
                ."\nAlpha promotion: ".($user->promo_code==='ALPHA'?'Saved':'Not saved')
                ."\n\nManage member: ".site_url('admin/users/'.$user->id)
                ."\n\nAccount creation does not confirm payment or an active subscription.");
            return $mailer->send();
        } catch (\Throwable) { return false; }
        finally { $mailer->clear(true); }
    }
}
