<?php
namespace App\Controllers;
use App\Services\SubscriptionAccess;
class Account extends BaseController
{
    public function billingHistory(): string
    {
        $id=$this->request->getGet('subscription');
        $before=$this->request->getGet('before');
        foreach ([$id,$before] as $value) {
            if ($value!==null && (!is_string($value) || !ctype_digit($value) || strlen($value)>10 || (int)$value<1)) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
        }
        $userId=(int)auth()->id();
        // Release the session lock before network I/O so billing history cannot block cancellation.
        session()->close();
        $history=(new \App\Services\BillingHistory())->forUser($userId,$id!==null?(int)$id:null,$before!==null?(int)$before:null);
        return view($this->request->isAJAX()?'account/billing_history':'account/billing_history_page',['history'=>$history]);
    }

    public function subscription(): string
    {
        return view('account/subscription', ['trialEligible' => (new \App\Services\TrialEligibility())->eligible(auth()->user()), 'subscription' => (new SubscriptionAccess())->activeFor((int) auth()->id()), 'paypal' => (new \App\Services\PayPalBilling())->latest((int) auth()->id()), 'paypalReady' => config('PayPal')->ready(), 'alphaEligible' => (new \App\Services\PayPalBilling())->alphaEligible(auth()->user())]);
    }
}
