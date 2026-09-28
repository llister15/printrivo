<?php
namespace App\Controllers;
use App\Services\PayPalBilling;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
class PayPal extends BaseController
{
    public function checkout(): RedirectResponse
    {
        if (! config('PayPal')->ready()) {
            return redirect()->to(site_url('account/subscription'))->with(
                'billing_error',
                'We could not start your purchase because payment setup is incomplete. Please contact support. You have not been charged.'
            );
        }
        $amount=$this->request->getPost('price_offer');
        if ($this->request->getPost('billing_consent') !== '1' || !in_array($this->request->getPost('trial_offer'), ['0','1'], true)
            || !is_string($amount) || !ctype_digit($amount) || strlen($amount)>3 || (int)$amount<1) {
            return redirect()->to(site_url('account/subscription'))->with('billing_error','Please review and accept the recurring billing terms before continuing.');
        }
        try { return redirect()->to((new PayPalBilling())->checkout(auth()->user(), $this->request->getPost('trial_offer') === '1',(int)$amount)); }
        catch (\Throwable) { return redirect()->to(site_url('account/subscription'))->with('billing_error','PayPal checkout could not start or eligibility changed. Review the current offer and check payment status before retrying, or contact support.'); }
    }
    public function returned(): RedirectResponse
    {
        // Verify the stored agreement server-side; never trust redirect parameters.
        try { (new PayPalBilling())->refresh((int)auth()->id()); }
        catch (\Throwable) { return redirect()->to(site_url('account/subscription'))->with('billing_message','PayPal confirmation is pending. Check payment status shortly.'); }
        if ((new \App\Services\SubscriptionAccess())->activeFor((int)auth()->id())) {
            return redirect()->to(site_url('dashboard'));
        }
        return redirect()->to(site_url('account/subscription'))->with('billing_message','Your PayPal subscription status has been checked. Review your access and renewal date below.');
    }
    public function refresh(): RedirectResponse
    {
        try {
            (new PayPalBilling())->refresh((int)auth()->id());
            if ((new \App\Services\SubscriptionAccess())->activeFor((int)auth()->id())) {
                return redirect()->to(site_url('dashboard'));
            }
            return redirect()->to(site_url('account/subscription'))->with('billing_message','Payment status checked. If payment is still pending, please wait a moment and check again.');
        } catch (\Throwable) { return redirect()->to(site_url('account/subscription'))->with('billing_error','PayPal status could not be checked. Please try again shortly.'); }
    }
    public function cancel(): RedirectResponse
    {
        try {
            (new PayPalBilling())->cancel((int)auth()->id());
            return redirect()->to(site_url('account/subscription'))->with('billing_message','Cancellation requested. Review your subscription status below; confirmed trial or paid access remains until the displayed end date.');
        } catch (\Throwable) { return redirect()->to(site_url('account/subscription'))->with('billing_error','Cancellation could not be confirmed. Check your PayPal automatic payments before retrying.'); }
    }
    public function webhook(): ResponseInterface
    {
        if (!config('PayPal')->ready()) { return $this->response->setStatusCode(503)->setJSON(['error'=>'Billing is not configured.']); }
        if (strlen($this->request->getBody())>262144) { return $this->response->setStatusCode(413)->setJSON(['error'=>'Payload too large.']); }
        $event=json_decode($this->request->getBody(),true);
        if (!is_array($event)) { return $this->response->setStatusCode(400)->setJSON(['error'=>'Invalid event.']); }
        $headers=[];
        foreach (['auth_algo'=>'PAYPAL-AUTH-ALGO','cert_url'=>'PAYPAL-CERT-URL','transmission_id'=>'PAYPAL-TRANSMISSION-ID',
            'transmission_sig'=>'PAYPAL-TRANSMISSION-SIG','transmission_time'=>'PAYPAL-TRANSMISSION-TIME'] as $key=>$header) {
            $headers[$key]=$this->request->getHeaderLine($header);
        }
        try {
            (new PayPalBilling())->webhook($headers,$event);
            return $this->response->setJSON(['ok'=>true]);
        } catch (\UnexpectedValueException) { return $this->response->setStatusCode(400)->setJSON(['error'=>'Invalid webhook.']); }
        catch (\Throwable) { return $this->response->setStatusCode(503)->setJSON(['error'=>'Please retry delivery.']); }
    }
}
