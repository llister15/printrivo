<?php
namespace App\Controllers;
use App\Services\PayPalBilling;
use App\Services\SubscriptionAccess;
use CodeIgniter\Exceptions\PageNotFoundException;
class Admin extends BaseController
{
    private function users()
    {
        return db_connect()->table('users u')->select('u.id,u.first_name,u.last_name,u.active,u.status,u.created_at,u.last_active,i.secret AS email')
            ->join('auth_identities i', "i.user_id=u.id AND i.type='email_password'", 'left')->where('u.deleted_at',null);
    }
    public function index(): string
    {
        $db=db_connect();
        $active=$db->table('subscriptions s')->select('s.user_id')->distinct()
            ->join('users u','u.id=s.user_id')->where('u.deleted_at',null)->where('u.active',1)
            ->groupStart()->where('u.status !=','banned')->orWhere('u.status',null)->groupEnd()
            ->whereIn('s.status',['active','trialing'])->where('s.current_period_end >',gmdate('Y-m-d H:i:s'))
            ->groupStart()->where('s.provider !=','paypal')->orGroupStart()->where('s.provider','paypal')
            ->where('s.provider_environment',config('PayPal')->environment)->groupEnd()->groupEnd();
        if (!in_array(ENVIRONMENT,['testing','development'],true)) { $active->where('s.provider !=','test'); }
        $stats=['active'=>$active->countAllResults(), 'total'=>$this->users()->countAllResults(),
            'recent'=>$this->users()->where('u.last_active >=',gmdate('Y-m-d H:i:s',time()-86400))->countAllResults()];
        $q=$this->request->getGet('q'); $q=is_string($q)?mb_substr(trim($q),0,100):'';
        $query=$this->users();
        if ($q!=='') { $query->groupStart()->like('u.first_name',$q)->orLike('u.last_name',$q)->orLike('i.secret',$q)->groupEnd(); }
        $count=$query->countAllResults(false);
        $page=$this->request->getGet('page'); $page=is_scalar($page)?max(1,(int)$page):1;
        $page=min($page,max(1,(int)ceil($count/25)));
        $users=$query->orderBy('u.id','DESC')->get(25,($page-1)*25)->getResultArray();
        $activity=[];
        if ($users) {
            $rows=$db->table('auth_logins')->select('user_id')->selectMax('date','last_login')
                ->select('SUM(CASE WHEN date >= '.$db->escape(gmdate('Y-m-d H:i:s',time()-30*86400)).' THEN 1 ELSE 0 END) AS logins_30d',false)
                ->where('success',1)->whereIn('user_id',array_column($users,'id'))->groupBy('user_id')->get()->getResultArray();
            $activity=array_column($rows,null,'user_id');
        }
        foreach ($users as &$user) {
            $user['last_login']=$activity[$user['id']]['last_login']??null;
            $user['logins_30d']=(int)($activity[$user['id']]['logins_30d']??0);
            $user['subscription']=(new PayPalBilling())->latest((int)$user['id']);
            $user['access']=(bool)(new SubscriptionAccess())->activeFor((int)$user['id']) && $user['active'] && $user['status']!=='banned';
        }
        return view('admin/index',compact('stats','users','q','page','count'));
    }
    public function user(int $id): string
    {
        $user=$this->users()->where('u.id',$id)->get()->getRowArray();
        if (!$user) { throw PageNotFoundException::forPageNotFound(); }
        $subscription=(new PayPalBilling())->latest($id);
        $complimentary=(new \App\Services\ComplimentaryAccess())->current($id);
        $invitation=db_connect()->table('member_invitations')->select('expires_at,accepted_at')->where('user_id',$id)->get()->getRowArray();
        $price=(new \App\Services\MemberPrice())->available($id);
        $actions=db_connect()->table('admin_actions')->where('user_id',$id)->orderBy('id','DESC')->limit(20)->get()->getResultArray();
        return view('admin/user',compact('user','subscription','complimentary','invitation','price','actions'));
    }
    public function createView(): string { return view('admin/create'); }
    public function create()
    {
        try {
            $members=new \App\Services\AdminMembers();
            $result=$members->create((int)auth()->id(),$this->request->getPost(['first_name','last_name','email','access','amount','days']));
            $user=$result['user'];
            $message='Member created. Share the private setup link below.';
            if ($this->request->getPost('send_invitation')==='1') {
                $message=$members->sendInvitation($user,$result['link'])
                    ? 'Member created. Invitation submitted to the mail server; delivery is not guaranteed.'
                    : 'Member created, but invitation email could not be sent. Share the setup link below or check email settings.';
            }
            return redirect()->to(site_url('admin/users/'.$user->id))->with('admin_message',$message)
                ->with('member_setup',['user_id'=>(int)$user->id,'link'=>$result['link']]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(site_url('admin/users/create'))->with('admin_error',$e->getMessage());
        } catch (\Throwable) {
            return redirect()->to(site_url('admin/users/create'))->with('admin_error','Account could not be created. Check for an existing email and try again.');
        }
    }
    public function invite(int $id)
    {
        try {
            $members=new \App\Services\AdminMembers();
            $link=$members->issue((int)auth()->id(),$id);
            $user=model(\App\Models\UserModel::class)->findById($id);
            $sent=$this->request->getPost('send_invitation')==='1' && $members->sendInvitation($user,$link);
            db_connect()->table('admin_actions')->insert(['admin_id'=>(int)auth()->id(),'user_id'=>$id,'subscription_id'=>null,
                'action'=>'reissue_invitation','outcome'=>$sent?'mail_submitted':'link_created','created_at'=>gmdate('Y-m-d H:i:s')]);
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_message',$sent
                ? 'New invitation submitted to the mail server. The previous link is invalid.'
                : 'New setup link created below. No email was sent. The previous link is invalid.')
                ->with('member_setup',['user_id'=>$id,'link'=>$link]);
        } catch (\Throwable) {
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_error','Only pending invitations can be reissued. Reload this member before retrying.');
        }
    }
    public function price(int $id)
    {
        $db=db_connect();
        try {
            $amount=\App\Services\MemberPrice::amount($this->request->getPost('amount'));
            $db->transBegin();
            (new \App\Services\MemberPrice())->set((int)auth()->id(),$id,$amount);
            if (!$db->transStatus()) { throw new \RuntimeException('Price could not be saved.'); }
            $db->transCommit();
            $message='Agreed price saved for the next subscription. No charge or access grant was made.';
            if ($this->request->getPost('notify')==='1') {
                $user=model(\App\Models\UserModel::class)->findById($id);
                $sent=service('memberMailer')->send($user->email,'Your Printrivo subscription offer',
                    'Your agreed subscription price is $'.number_format($amount/100,2,'.','')." USD/month while this subscription continues.\n\n"
                    ."Sign in to review trial eligibility and authorize recurring payments with PayPal:\n".site_url('account/subscription')
                    ."\n\nThis offer is not a payment receipt and does not start billing. Cancel renewal anytime from your subscription page.");
                $message.=$sent?' Offer submitted to the mail server.':' Email failed; the saved offer is available on the member’s subscription page.';
            }
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_message',$message);
        } catch (\Throwable) {
            $db->transRollback();
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_error','Price could not be saved. Use $0.01–$9.99 for an active account with no open PayPal agreement. Existing subscribers use the discount control.');
        }
    }
    public function discount(int $id)
    {
        try {
            $expected=$this->request->getPost('subscription_id');
            if (!is_string($expected) || !ctype_digit($expected) || strlen($expected)>10 || $this->request->getPost('confirm')!=='1') {
                throw new \RuntimeException('Confirmation required.');
            }
            $billing=new PayPalBilling();
            if ($this->request->getPost('retry')==='1') { $billing->retryDiscount((int)auth()->id(),$id,(int)$expected); }
            else { $billing->discount((int)auth()->id(),$id,(int)$expected,\App\Services\MemberPrice::amount($this->request->getPost('amount'))); }
            $row=$billing->latest($id);
            $confirmed=$row && $row['discount_confirmed_at'];
            $message=$confirmed?'Discount confirmed by PayPal. Payments due within 10 days may still use the previous price.'
                :'Discount requested; PayPal confirmation is pending. Check status before retrying.';
            if ($confirmed && $this->request->getPost('notify')==='1') {
                $user=model(\App\Models\UserModel::class)->findById($id);
                $sent=service('memberMailer')->send($user->email,'Your Printrivo subscription discount',
                    'PayPal has confirmed your new monthly price of $'.number_format($row['billing_amount']/100,2,'.','')
                    ." USD. Payments due within 10 days of the change may still use the previous price. Your billing schedule is unchanged. No refund or new trial is created.\n\n"
                    .'Review your subscription: '.site_url('account/subscription'));
                $message.=$sent?' Notification submitted to the mail server.':' Email failed; the new price is visible on the subscription page.';
            }
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_message',$message);
        } catch (\Throwable) {
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_error','Discount could not be confirmed. Check PayPal status: a request may have reached PayPal. Use a lower positive price; another discount must wait until the previous one appears on a payment.');
        }
    }
    public function refresh(int $id)
    {
        try {
            (new PayPalBilling())->refresh($id);
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_message','PayPal status checked. Review the confirmation below.');
        } catch (\Throwable) {
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_error','PayPal status could not be checked. Try again shortly.');
        }
    }
    public function access(int $id)
    {
        $kind=$this->request->getPost('kind');
        $days=$this->request->getPost('days');
        if (!is_string($kind) || !in_array($kind,['demo','internal','revoke'],true)
            || ($kind!=='revoke' && (!is_string($days) || !ctype_digit($days) || strlen($days)>4 || (int)$days<1 || (int)$days>3650))) {
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_error','Choose demo or internal access for 1–3650 days.');
        }
        try {
            (new \App\Services\ComplimentaryAccess())->change((int)auth()->id(),$id,$kind,$kind==='revoke'?0:(int)$days);
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_message', $kind==='revoke'
                ? 'Complimentary access revoked. Any eligible PayPal subscription remains available.'
                : 'Complimentary access granted. The member can open all tools from the dashboard without payment setup.');
        } catch (\Throwable) {
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_error','Access could not be changed. Check that the member is active and try again.');
        }
    }
    public function cancel(int $id)
    {
        $target=$this->users()->where('u.id',$id)->get()->getRowArray();
        if (!$target) { throw PageNotFoundException::forPageNotFound(); }
        $row=(new PayPalBilling())->latest($id);
        $expected=$this->request->getPost('subscription_id');
        if (!$row || !is_string($expected) || (string)$row['id']!==$expected || $this->request->getPost('confirm')!=='1') {
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_error','Review the current subscription and confirm the cancellation.');
        }
        $db=db_connect();
        // Record intent before contacting PayPal; a timeout can have an uncertain outcome.
        $db->table('admin_actions')->insert(['admin_id'=>(int)auth()->id(),'user_id'=>$id,'subscription_id'=>$row['id'],
            'action'=>'cancel_subscription','outcome'=>'requested','created_at'=>gmdate('Y-m-d H:i:s')]);
        $audit=$db->insertID();
        try {
            (new PayPalBilling())->cancel($id,(int)$row['id']);
            $confirmed=(new PayPalBilling())->latest($id);
            if (!$confirmed || (!$confirmed['cancel_at_period_end'] && !in_array($confirmed['status'],['canceled','expired'],true))) { throw new \RuntimeException('Provider confirmation pending.'); }
            $db->table('admin_actions')->where('id',$audit)->update(['outcome'=>'confirmed']);
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_message','Cancellation confirmed with PayPal. Remaining confirmed trial or paid access is preserved.');
        } catch (\Throwable) {
            $db->table('admin_actions')->where('id',$audit)->update(['outcome'=>'unconfirmed']);
            return redirect()->to(site_url('admin/users/'.$id))->with('admin_error','Cancellation could not be confirmed. Check PayPal before retrying; the request may have reached PayPal.');
        }
    }
}
