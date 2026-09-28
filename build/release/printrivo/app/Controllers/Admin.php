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
        foreach ($users as &$user) {
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
        $actions=db_connect()->table('admin_actions')->where('user_id',$id)->orderBy('id','DESC')->limit(20)->get()->getResultArray();
        return view('admin/user',compact('user','subscription','actions'));
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
