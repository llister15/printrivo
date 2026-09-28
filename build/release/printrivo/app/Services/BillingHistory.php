<?php
namespace App\Services;
use CodeIgniter\Exceptions\PageNotFoundException;
use RuntimeException;

class BillingHistory
{
    private PayPalClient $client;
    public function __construct(?PayPalClient $client=null) { $this->client=$client??service('paypalClient'); }

    public function forUser(int $userId, ?int $subscriptionId=null, ?int $before=null): array
    {
        $config=config('PayPal');
        // Only the authenticated member's agreements in this environment are selectable.
        // Historical/canceled agreements remain available without a current entitlement.
        $subscriptions=db_connect()->table('subscriptions')->where('user_id',$userId)->where('provider','paypal')
            ->where('provider_environment',$config->environment)->where('provider_subscription_id !=',null)
            ->orderBy('id','DESC')->get()->getResultArray();
        $selected=null;
        foreach ($subscriptions as $row) {
            if ($subscriptionId===null || (int)$row['id']===$subscriptionId) { $selected=$row; break; }
        }
        if ($subscriptionId!==null && !$selected) { throw PageNotFoundException::forPageNotFound(); }
        $result=['subscriptions'=>$subscriptions,'selected'=>$selected,'transactions'=>[], 'error'=>null,
            'start'=>null,'end'=>null,'older'=>null,'newer'=>null];
        if (!$selected) { return $result; }
        if (!$config->ready()) { $result['error']='Billing history is temporarily unavailable. Please try again later.'; return $result; }
        try {
            $path='/v1/billing/subscriptions/'.rawurlencode($selected['provider_subscription_id']);
            $details=$this->client->request('GET',$path);
            if (($details['id']??'')!==$selected['provider_subscription_id']
                || ($details['plan_id']??'')!==$selected['provider_price_id']
                || !hash_equals((string)$selected['paypal_reference'],(string)($details['custom_id']??''))) {
                throw new RuntimeException('Subscription ownership mismatch.');
            }
            $created=PayPalBilling::date($details['create_time']??$selected['created_at']??'');
            if (!$created) { throw new RuntimeException('Missing subscription creation date.'); }
            $earliest=strtotime($created.' UTC')-1;
            $now=time();
            $end=$before!==null ? max($earliest+1,min($now,$before)) : $now;
            $start=max($earliest,$end-90*86400);
            $result['start']=$start; $result['end']=$end;
            $result['older']=$start>$earliest ? $start : null;
            $result['newer']=$end<$now-60 ? min($now,$end+90*86400) : null;
            $query=http_build_query(['start_time'=>gmdate('Y-m-d\TH:i:s\Z',$start),'end_time'=>gmdate('Y-m-d\TH:i:s\Z',$end)],'', '&',PHP_QUERY_RFC3986);
            $endpoint=$path.'/transactions'; $next=$endpoint.'?'.$query;
            $seen=[]; $transactions=[]; $expectedTotal=null;
            // Follow only PayPal's same-subscription pagination links. Never follow arbitrary URLs.
            for ($page=0;$next!==null && $page<20;$page++) {
                if (isset($seen[$next])) { throw new RuntimeException('Repeated transaction page.'); }
                $seen[$next]=true;
                $payload=$this->client->request('GET',$next);
                if (!isset($payload['transactions']) || !is_array($payload['transactions'])) { throw new RuntimeException('Invalid transaction response.'); }
                if (isset($payload['total_items'])) { $expectedTotal=(int)$payload['total_items']; }
                foreach ($payload['transactions'] as $transaction) {
                    $id=$transaction['id']??''; $date=PayPalBilling::date($transaction['time']??'');
                    $gross=$transaction['amount_with_breakdown']['gross_amount']??[];
                    $amount=PayPalBilling::cents($gross['value']??''); $currency=$gross['currency_code']??'';
                    if (!is_string($id)||$id===''||!$date||$amount<0||!is_string($currency)||!preg_match('/^[A-Z]{3}$/D',$currency)) {
                        throw new RuntimeException('Invalid transaction details.');
                    }
                    $status=$transaction['status']??'UNKNOWN';
                    $label=match($status) {'COMPLETED'=>'Paid','PENDING'=>'Pending','REFUNDED'=>'Refunded',
                        'PARTIALLY_REFUNDED'=>'Partially refunded','REVERSED'=>'Reversed','DENIED','DECLINED'=>'Declined','FAILED'=>'Failed',default=>'Unknown'};
                    $transactions[$id]=['id'=>$id,'date'=>$date,'amount'=>$amount,'currency'=>$currency,'status'=>$label,
                        'tone'=>$status==='COMPLETED'?'paid':(in_array($status,['FAILED','DENIED','DECLINED','REVERSED'],true)?'attention':'neutral')];
                }
                $next=null;
                foreach ($payload['links']??[] as $link) {
                    if (($link['rel']??'')!=='next') { continue; }
                    $url=parse_url($link['href']??'');
                    if (!$url || ($url['scheme']??'')!=='https' || ($url['host']??'')!==parse_url($config->apiBase(),PHP_URL_HOST)
                        || ($url['path']??'')!==$endpoint || isset($url['user']) || isset($url['port']) || empty($url['query'])) {
                        throw new RuntimeException('Invalid transaction pagination.');
                    }
                    $next=$endpoint.'?'.$url['query'];
                }
            }
            if ($next!==null || ($expectedTotal!==null && count($transactions)<$expectedTotal)) { throw new RuntimeException('Incomplete transaction history.'); }
            $transactions=array_filter($transactions,static function($row)use($start,$end) {
                $time=strtotime($row['date'].' UTC'); return $time>$start && $time<=$end;
            });
            usort($transactions,static fn($a,$b)=>strcmp($b['date'],$a['date']) ?: strcmp($b['id'],$a['id']));
            $result['transactions']=$transactions;
        } catch (\Throwable) {
            // Never show a partial fetch as a complete billing record; access is not modified.
            $result['error']='We could not load your billing history from PayPal. Please try again. Your subscription has not changed.';
        }
        return $result;
    }
}
