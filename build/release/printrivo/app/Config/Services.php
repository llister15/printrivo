<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function signupNotifier($getShared = true): \App\Services\SignupNotifier
    {
        if ($getShared) { return static::getSharedInstance('signupNotifier'); }
        return new \App\Services\SignupNotifier();
    }

    public static function bugReportMailer($getShared = true): \App\Services\BugReportMailer
    {
        if ($getShared) { return static::getSharedInstance('bugReportMailer'); }
        return new \App\Services\BugReportMailer();
    }

    public static function recaptchaVerifier($getShared = true): \App\Services\RecaptchaVerifier
    {
        if ($getShared) { return static::getSharedInstance('recaptchaVerifier'); }
        return new \App\Services\RecaptchaVerifier();
    }

    public static function paypalClient($getShared = true): \App\Services\PayPalClient
    {
        if ($getShared) { return static::getSharedInstance('paypalClient'); }
        return new \App\Services\PayPalClient();
    }

    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
