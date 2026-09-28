<?php
namespace App\Services;

class MemberPrice
{
    public static function amount($value): int
    {
        if (!is_string($value) || !preg_match('/^\d{1,2}(?:\.\d{1,2})?$/D',$value)) {
            throw new \InvalidArgumentException('Enter a monthly amount from $0.01 to $9.99, or choose free access.');
        }
        $amount=PayPalBilling::cents($value);
        if ($amount<1 || $amount>999) { throw new \InvalidArgumentException('Enter a monthly amount from $0.01 to $9.99, or choose free access.'); }
        return $amount;
    }
    public function available(int $userId): ?array
    {
        return db_connect()->table('member_prices')->where(['user_id'=>$userId,'environment'=>config('PayPal')->environment])
            ->where('subscription_id',null)->get()->getRowArray();
    }
    public function set(int $adminId,int $userId,int $amount): void
    {
        AdminMembers::assertAdmin($adminId);
        $user=model(\App\Models\UserModel::class)->findById($userId);
        if (!$user || !$user->active || $user->isBanned()) { throw new \RuntimeException('An active member is required.'); }
        if ($amount<1 || $amount>999) { throw new \InvalidArgumentException('Invalid monthly amount.'); }
        $latest=(new PayPalBilling())->latest($userId);
        if ($latest && !in_array($latest['status'],['canceled','expired'],true)) {
            throw new \RuntimeException('An agreement already exists. Use its discount control or resolve pending checkout first.');
        }
        $db=db_connect();
        $db->transBegin();
        try {
            $existing=$db->table('member_prices')->where(['user_id'=>$userId,'environment'=>config('PayPal')->environment])->get()->getRowArray();
            if ($existing) {
                $db->table('member_prices')->where('id',$existing['id'])->where('subscription_id',$existing['subscription_id'])->delete();
                if ($db->affectedRows()!==1) { throw new \RuntimeException('Checkout changed; reload the account.'); }
            }
            $db->table('member_prices')->insert(['user_id'=>$userId,'environment'=>config('PayPal')->environment,'amount'=>$amount,
                'admin_id'=>$adminId,'created_at'=>gmdate('Y-m-d H:i:s')]);
            $db->table('admin_actions')->insert(['admin_id'=>$adminId,'user_id'=>$userId,'subscription_id'=>null,
                'action'=>'offer_monthly_'.$amount,'outcome'=>'confirmed','created_at'=>gmdate('Y-m-d H:i:s')]);
            if (!$db->transStatus()) { throw new \RuntimeException('Price could not be saved.'); }
            $db->transCommit();
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }
}
