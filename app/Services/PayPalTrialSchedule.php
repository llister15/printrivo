<?php
namespace App\Services;

class PayPalTrialSchedule
{
    public static function matches(?string $start, ?string $end): bool
    {
        if (!$start || !$end) { return false; }
        $startAt=strtotime($start.' UTC');
        $endAt=strtotime($end.' UTC');
        if ($startAt===false || $endAt===false) { return false; }
        // Checkout separately validates one free DAY/14 plan cycle. PayPal's
        // actual billing schedule need not share the agreement's UTC calendar
        // date/time: the observed live 14-day plan spans 13d 5h 11m 46s.
        // Allow less than one day of scheduling variance, never arbitrary trial
        // lengths. Always keep the exact provider end; never extend from refresh.
        return abs(($endAt-$startAt)-14*86400)<86400;
    }
}
