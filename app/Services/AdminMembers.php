<?php
namespace App\Services;

use App\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

class AdminMembers
{
    public static function assertAdmin(int $id): void
    {
        $admin=model(UserModel::class)->findById($id);
        if (!$admin || !$admin->active || $admin->isBanned() || !$admin->inGroup('admin')) {
            throw new RuntimeException('Administrator access required.');
        }
    }
    public function create(int $adminId,array $data): array
    {
        self::assertAdmin($adminId);
        if (is_string($data['email']??null)) { $data['email']=strtolower(trim($data['email'])); }
        $rules=[
            'first_name'=>'required|max_length[80]', 'last_name'=>'required|max_length[80]',
            'email'=>'required|valid_email|max_length[254]|is_unique[auth_identities.secret]',
            'access'=>'required|in_list[paid,demo,internal]',
        ];
        $validator=service('validation');
        if (!$validator->reset()->setRules($rules)->run($data)) { throw new \InvalidArgumentException(implode(' ',$validator->getErrors())); }
        $amount=$data['access']==='paid'?MemberPrice::amount($data['amount']??null):null;
        $days=$data['days']??'';
        if ($amount===null && (!is_string($days) || !ctype_digit($days) || strlen($days)>4 || (int)$days<1 || (int)$days>3650)) {
            throw new \InvalidArgumentException('Free access must last 1–3650 days.');
        }
        $db=db_connect(); $users=model(UserModel::class);
        $db->transBegin();
        try {
            // No shared/default password: only the holder of the setup link can set one.
            $user=new User(['first_name'=>$data['first_name'],'last_name'=>$data['last_name'],
                'email'=>strtolower(trim($data['email'])),'password'=>bin2hex(random_bytes(32)),'active'=>1]);
            if (!$users->save($user)) { throw new RuntimeException('Account could not be created.'); }
            $user=$users->findById($users->getInsertID());
            $users->addToDefaultGroup($user);
            $db->table('member_invitations')->insert(['user_id'=>$user->id,'token_hash'=>hash('sha256',random_bytes(32)),
                'expires_at'=>gmdate('Y-m-d H:i:s'),'created_at'=>gmdate('Y-m-d H:i:s')]);
            if ($amount!==null) { (new MemberPrice())->set($adminId,(int)$user->id,$amount); }
            else { (new ComplimentaryAccess())->change($adminId,(int)$user->id,$data['access'],(int)$days); }
            $link=$this->issue($adminId,(int)$user->id);
            $db->table('admin_actions')->insert(['admin_id'=>$adminId,'user_id'=>$user->id,'subscription_id'=>null,
                'action'=>'create_member','outcome'=>'confirmed','created_at'=>gmdate('Y-m-d H:i:s')]);
            if (!$db->transStatus()) { throw new RuntimeException('Account could not be created.'); }
            $db->transCommit();
            return ['user'=>$user,'link'=>$link];
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }
    public function issue(int $adminId,int $userId): string
    {
        self::assertAdmin($adminId);
        $db=db_connect();
        $user=model(UserModel::class)->findById($userId);
        $existing=$db->table('member_invitations')->where('user_id',$userId)->get()->getRowArray();
        if (!$user || !$user->active || $user->isBanned() || ($existing && $existing['accepted_at'])) {
            throw new RuntimeException('This account cannot receive a setup link.');
        }
        // Only admin-created accounts may receive setup links; this is not a password-reset endpoint.
        if (!$existing) { throw new RuntimeException('No pending invitation.'); }
        $token=bin2hex(random_bytes(32));
        $row=['token_hash'=>hash('sha256',$token),'expires_at'=>gmdate('Y-m-d H:i:s',time()+7*86400),
            'created_at'=>gmdate('Y-m-d H:i:s'),'accepted_at'=>null];
        $db->table('member_invitations')->where('user_id',$userId)->where('accepted_at',null)
            ->where('token_hash',$existing['token_hash'])->update($row);
        if ($db->affectedRows()!==1) { throw new RuntimeException('Invitation changed; reload the member page.'); }
        // Fragment avoids placing the token in server access logs or Referer headers.
        return site_url('account/setup').'#'.$token;
    }
    public function accept(string $token,string $password): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) { throw new RuntimeException('Setup link is invalid or expired.'); }
        $maximum=config('Auth')->hashAlgorithm===PASSWORD_BCRYPT?72:255;
        if (strlen($password)>$maximum) { throw new \InvalidArgumentException('Password exceeds the supported length.'); }
        $db=db_connect(); $db->transBegin();
        try {
            $hash=hash('sha256',$token);
            $row=$db->table('member_invitations')->where('token_hash',$hash)->where('accepted_at',null)
                ->where('expires_at >',gmdate('Y-m-d H:i:s'))->get()->getRowArray();
            $users=model(UserModel::class); $user=$row?$users->findById($row['user_id']):null;
            if (!$user || !$user->active || $user->isBanned()) { throw new RuntimeException('Setup link is invalid or expired.'); }
            $result=service('passwords')->check($password,$user);
            if (!$result->isOK()) { throw new \InvalidArgumentException($result->reason()); }
            $db->table('member_invitations')->where('token_hash',$hash)->where('accepted_at',null)
                ->where('expires_at >',gmdate('Y-m-d H:i:s'))->update(['accepted_at'=>gmdate('Y-m-d H:i:s')]);
            if ($db->affectedRows()!==1) { throw new RuntimeException('Setup link is invalid or expired.'); }
            $user->password=$password;
            if (!$users->save($user) || !$db->transStatus()) { throw new RuntimeException('Account setup could not be saved.'); }
            $db->transCommit();
        } catch (\Throwable $e) { $db->transRollback(); throw $e; }
    }
    public function sendInvitation(User $user,string $link): bool
    {
        $grant=(new ComplimentaryAccess())->current((int)$user->id);
        $price=(new MemberPrice())->available((int)$user->id);
        $terms=$grant ? 'Your '. $grant['provider'].' access is free through '.$grant['current_period_end'].' UTC. No payment information is required.'
            : 'Your agreed price is $'.number_format(($price['amount']??999)/100,2,'.','').' USD/month, renewing until canceled. Review trial eligibility and authorize payment in PayPal after setting up your account. This invitation does not charge you or confirm payment.';
        return service('memberMailer')->send($user->email,'Your Printrivo account invitation',
            "An administrator created your Printrivo account.\n\n".$terms."\n\nSet your password using this one-time link (valid for 7 days):\n".$link
            ."\n\nKeep this link private. If you were not expecting this invitation, contact Printrivo support.");
    }
}
