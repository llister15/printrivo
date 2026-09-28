<?php
namespace App\Services;
use CodeIgniter\Shield\Entities\User;
class BugReportMailer
{
    public function send(User $user,array $app,string $summary,string $description,string $expected): bool
    {
        if (!config('BugReports')->ready()) { return false; }
        $mailer=service('email');
        try {
            $mailer->clear(true);
            $mailer->setMailType('text');
            $mailer->setFrom(config('Email')->fromEmail,config('Email')->fromName ?: 'Printrivo');
            $mailer->setTo(config('BugReports')->recipient);
            $mailer->setReplyTo($user->email);
            $mailer->setSubject('[Printrivo bug report] '.$app['shortName']);
            $mailer->setMessage("Tool: ".$app['name']."\nPage: ".site_url($app['route'])."\nMember ID: ".$user->id
                ."\nReply to: ".$user->email."\nSubmitted (UTC): ".gmdate('Y-m-d H:i:s')
                ."\n\nSummary\n".$summary."\n\nWhat happened / steps to reproduce\n".$description
                ."\n\nExpected result\n".($expected!==''?$expected:'Not provided.'));
            return $mailer->send();
        } catch (\Throwable) {
            return false; // Never log email credentials, member details, or report text.
        } finally { $mailer->clear(true); }
    }
}
