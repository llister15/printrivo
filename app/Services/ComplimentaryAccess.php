<?php
namespace App\Services;

use App\Models\UserModel;
use RuntimeException;

class ComplimentaryAccess
{
    public const PROVIDERS = ['demo', 'internal'];

    public function current(int $userId): ?array
    {
        return db_connect()->table('subscriptions')->where('user_id', $userId)
            ->whereIn('provider', self::PROVIDERS)->where('status', 'active')
            ->where('current_period_end >', gmdate('Y-m-d H:i:s'))
            ->orderBy('current_period_end', 'DESC')->get()->getRowArray();
    }

    public function change(int $adminId, int $userId, string $kind, int $days = 0): void
    {
        $users = model(UserModel::class);
        $admin = $users->findById($adminId);
        $user = $users->findById($userId);
        if (!$admin || !$admin->active || $admin->isBanned() || !$admin->inGroup('admin')) {
            throw new RuntimeException('Administrator access required.');
        }
        if (!$user || !$user->active || $user->isBanned()) {
            throw new RuntimeException('An active, unbanned member is required.');
        }
        if ($kind !== 'revoke' && (!in_array($kind, self::PROVIDERS, true) || $days < 1 || $days > 3650)) {
            throw new RuntimeException('Choose demo or internal access for 1–3650 days.');
        }
        $db = db_connect();
        $now = gmdate('Y-m-d H:i:s');
        $db->transBegin();
        try {
            // Complimentary grants never modify or cancel payment agreements.
            $old = $db->table('subscriptions')->where('user_id', $userId)
                ->whereIn('provider', self::PROVIDERS)->where('status', 'active')->get()->getResultArray();
            foreach ($old as $row) {
                $db->table('subscriptions')->where('id', $row['id'])->update(['status'=>'canceled', 'updated_at'=>$now]);
                $db->table('admin_actions')->insert(['admin_id'=>$adminId, 'user_id'=>$userId,
                    'subscription_id'=>$row['id'], 'action'=>'revoke_complimentary', 'outcome'=>'confirmed', 'created_at'=>$now]);
            }
            if ($kind !== 'revoke') {
                $db->table('subscriptions')->insert(['user_id'=>$userId, 'provider'=>$kind, 'status'=>'active',
                    'current_period_start'=>$now, 'current_period_end'=>gmdate('Y-m-d H:i:s', time()+$days*86400),
                    'created_at'=>$now, 'updated_at'=>$now]);
                $id = $db->insertID();
                $db->table('admin_actions')->insert(['admin_id'=>$adminId, 'user_id'=>$userId,
                    'subscription_id'=>$id, 'action'=>'grant_'.$kind, 'outcome'=>'confirmed', 'created_at'=>$now]);
            }
            if (!$db->transStatus()) { throw new RuntimeException('Access could not be saved.'); }
            $db->transCommit();
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }
}
