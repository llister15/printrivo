<?php
namespace App\Commands;

use App\Models\UserModel;
use App\Services\PayPalBilling;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PayPalInspect extends BaseCommand
{
    protected $group = 'Billing';
    protected $name = 'paypal:inspect';
    protected $description = 'Read safe subscription diagnostics for an existing member; does not change billing or access.';
    protected $usage = 'paypal:inspect member@example.com';

    public function run(array $params)
    {
        $email=$params[0]??'';
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { CLI::error('Usage: php spark paypal:inspect member@example.com'); return; }
        $user=model(UserModel::class)->findByCredentials(['email'=>$email]);
        if (!$user) { CLI::error('Account not found.'); return; }
        $row=(new PayPalBilling())->latest((int)$user->id);
        if (!$row || !$row['provider_subscription_id']) { CLI::error('No PayPal agreement in the configured environment.'); return; }
        try {
            $details=service('paypalClient')->request('GET','/v1/billing/subscriptions/'.rawurlencode($row['provider_subscription_id']));
            $billing=$details['billing_info']??[];
            $start=PayPalBilling::date($details['start_time']??'');
            $end=PayPalBilling::date($billing['next_billing_time']??'');
            // Deliberate allowlist: never print API responses, credentials, payer IDs,
            // customer names, addresses, tokens, or custom checkout references.
            $report=[
                'environment'=>config('PayPal')->environment,
                'local_subscription_id'=>(int)$row['id'],
                'local_status'=>$row['status'],
                'trial_requested'=>(bool)$row['trial_requested'],
                'trial_ends_at'=>$row['trial_ends_at'],
                'current_period_end'=>$row['current_period_end'],
                'remote_status'=>$details['status']??null,
                'ownership_matches'=>($details['id']??'')===$row['provider_subscription_id']
                    && ($details['plan_id']??'')===$row['provider_price_id']
                    && hash_equals((string)$row['paypal_reference'],(string)($details['custom_id']??'')),
                'payer_present'=>!empty($details['subscriber']['payer_id']),
                'start_time_utc'=>$start,
                'next_billing_time_utc'=>$end,
                'exact_14_day_seconds_match'=>$start && $end && strtotime($end.' UTC')===strtotime($start.' UTC')+14*86400,
                '14_day_billing_date_matches'=>$start && $end && substr($end,0,10)===gmdate('Y-m-d',strtotime($start.' UTC')+14*86400),
                'provider_trial_schedule_accepted'=>\App\Services\PayPalTrialSchedule::matches($start,$end),
                'last_payment_present'=>!empty($billing['last_payment']),
                'last_payment_is_zero'=>PayPalBilling::cents($billing['last_payment']['amount']['value']??'')===0,
                'payment_hold_present'=>!empty($row['paypal_hold_at']),
                'failed_payments_count'=>$billing['failed_payments_count']??null,
                'outstanding_balance_is_zero'=>PayPalBilling::cents($billing['outstanding_balance']['value']??'0')===0,
                'cycles'=>array_map(static fn(array $cycle):array=>array_intersect_key($cycle,array_flip([
                    'tenure_type','sequence','cycles_completed','cycles_remaining','total_cycles',
                ])), $billing['cycle_executions']??[]),
            ];
            CLI::write(json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        } catch (\Throwable) { CLI::error('PayPal could not be inspected. Check server configuration and connectivity. No changes were made.'); }
    }
}
