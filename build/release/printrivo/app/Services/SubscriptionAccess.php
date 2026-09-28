<?php
namespace App\Services;
class SubscriptionAccess
{
    public function activeFor(int $userId): ?array
    {
        // Only confirmed, unexpired entitlements grant access. Sandbox cannot unlock live tools.
        $query = db_connect()->table('subscriptions');
        // Synthetic test entitlements must never unlock a deployed production app.
        if (! in_array(ENVIRONMENT, ['development', 'testing'], true)) {
            $query->where('provider !=', 'test');
        }
        return $query
            ->where('user_id', $userId)->whereIn('status', ['active', 'trialing'])
            ->groupStart()->where('provider !=', 'paypal')->orGroupStart()
                ->where('provider', 'paypal')->where('provider_environment', config('PayPal')->environment)
            ->groupEnd()->groupEnd()
            ->where('current_period_end >', gmdate('Y-m-d H:i:s'))
            ->orderBy('current_period_end', 'DESC')->get()->getRowArray();
    }
}
