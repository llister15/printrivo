<?php
namespace App\Services;
use CodeIgniter\Shield\Entities\User;

class TrialEligibility
{
    public function device(): string
    {
        $value = (string) service('request')->getCookie('printrivo_device');
        if (!preg_match('/^[a-f0-9]{64}$/D', $value)) {
            $value = session('trial_device') ?: bin2hex(random_bytes(32));
            service('response')->setCookie('printrivo_device', $value, 365 * 86400, '', '/', '', service('request')->isSecure(), true, 'Lax');
        }
        session()->set('trial_device', $value);
        return $value;
    }
    public function hash(string $kind, string $value): string
    {
        $key = config('PayPal')->trialHashKey;
        if (strlen($key) < 32) { throw new \RuntimeException('Trial protection is not configured.'); }
        return hash_hmac('sha256', $kind . ':' . $value, $key);
    }
    private function signals(User $user): array
    {
        return ['user'=>(string)$user->id, 'email'=>strtolower(trim($user->email)), 'device'=>$this->device()];
    }
    public function eligible(User $user): bool
    {
        if (!config('PayPal')->trialsReady()) { return false; }
        // Existing subscribers cannot receive a new introductory trial.
        if (db_connect()->table('subscriptions')->where('user_id',$user->id)
            ->where('provider_environment',config('PayPal')->environment)
            ->groupStart()->where('trial_requested',1)->orWhere('current_period_start !=',null)->groupEnd()->countAllResults()) { return false; }
        foreach ($this->signals($user) as $kind=>$value) {
            if ($this->claim($kind,$value)) { return false; }
        }
        // A shared IP is only a velocity signal, never proof that two people are identical.
        return db_connect()->table('trial_claims')->where('environment',config('PayPal')->environment)
            ->where('kind','user')->where('ip_hash',$this->hash('ip',service('request')->getIPAddress()))
            ->where('created_at >',gmdate('Y-m-d H:i:s',time()-86400))->countAllResults() < 5;
    }
    private function claim(string $kind,string $value): ?array
    {
        return db_connect()->table('trial_claims')->where(['environment'=>config('PayPal')->environment,
            'kind'=>$kind,'signal_hash'=>$this->hash($kind,$value)])->get()->getRowArray();
    }
    public function reserve(User $user,int $subscriptionId): void
    {
        // Called inside checkout's transaction; unique keys stop concurrent duplicate trials.
        foreach ($this->signals($user) as $kind=>$value) { $this->insert($kind,$value,$subscriptionId); }
    }
    private function insert(string $kind,string $value,int $subscriptionId): void
    {
        db_connect()->table('trial_claims')->insert(['environment'=>config('PayPal')->environment,'kind'=>$kind,
            'signal_hash'=>$this->hash($kind,$value),'subscription_id'=>$subscriptionId,
            'ip_hash'=>$kind==='user'?$this->hash('ip',service('request')->getIPAddress()):null,'created_at'=>gmdate('Y-m-d H:i:s')]);
    }
    public function claimPayer(string $payer,int $subscriptionId): bool
    {
        if ($payer==='') { return false; }
        $existing=$this->claim('payer',$payer);
        if ($existing) { return (int)$existing['subscription_id']===$subscriptionId; }
        $this->insert('payer',$payer,$subscriptionId);
        return true;
    }
}
