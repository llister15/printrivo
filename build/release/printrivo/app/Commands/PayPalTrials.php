<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\PayPalBilling;
use App\Services\PayPalClient;

class PayPalTrials extends BaseCommand
{
    protected $group='Billing';
    protected $name='paypal:setup-trials';
    protected $description='Create and save sandbox-only 14-day trial plans using existing monthly products.';
    public function run(array $params)
    {
        $config=config('PayPal');
        if ($config->environment!=='sandbox' || !$config->ready()) {
            CLI::error('Requires configured PayPal sandbox billing. Live plans are never created by this command.'); return;
        }
        $client=new PayPalClient(); $billing=new PayPalBilling($client);
        try {
            foreach (['standard'=>999,'alpha'=>499] as $name=>$amount) {
                $field=$name.'TrialPlanId';
                if ($config->$field==='') {
                    $base=$client->request('GET','/v1/billing/plans/'.rawurlencode($config->{$name.'PlanId'}));
                    $billing->validatePlan($base,$amount);
                    $body=['product_id'=>$base['product_id'],'name'=>'Printrivo '.ucfirst($name).' — 14-day trial',
                        'description'=>'14 days free, then monthly until canceled. All Printrivo tools included.',
                        'status'=>'ACTIVE','billing_cycles'=>[
                            ['frequency'=>['interval_unit'=>'DAY','interval_count'=>14],'tenure_type'=>'TRIAL','sequence'=>1,'total_cycles'=>1,
                                'pricing_scheme'=>['fixed_price'=>['value'=>'0.00','currency_code'=>'USD']]],
                            ['frequency'=>['interval_unit'=>'MONTH','interval_count'=>1],'tenure_type'=>'REGULAR','sequence'=>2,'total_cycles'=>0,
                                'pricing_scheme'=>['fixed_price'=>['value'=>number_format($amount/100,2,'.',''),'currency_code'=>'USD']]],
                        ],'payment_preferences'=>['auto_bill_outstanding'=>false,'payment_failure_threshold'=>1]];
                    $result=$client->request('POST','/v1/billing/plans',$body,substr(hash('sha256','printrivo-trial-v1-'.$base['id']),0,32));
                    $id=$result['id']??'';
                    if (!preg_match('/^P-[A-Z0-9]+$/',$id)) { throw new \RuntimeException('Invalid trial plan reference.'); }
                    $config->$field=$id;
                    $this->saveSetting('PayPal.'.$field,$id);
                }
                $billing->validatePlan($client->request('GET','/v1/billing/plans/'.rawurlencode($config->$field)),$amount,true);
                CLI::write($field.': verified and saved');
            }
            if (strlen($config->trialHashKey)<32) { $this->saveSetting('PayPal.trialHashKey',bin2hex(random_bytes(32))); }
            CLI::write('Sandbox trial configuration saved. No customer subscription or payment was created.');
        } catch (\Throwable) { CLI::error('Sandbox trial setup could not complete. Saved plan references are retained; retry the command.'); }
    }
    private function saveSetting(string $key,string $value): void
    {
        $path=ROOTPATH.'.env';
        $handle=fopen($path,'c+');
        if (!$handle || !flock($handle,LOCK_EX)) { throw new \RuntimeException('Cannot save billing configuration.'); }
        try {
            $text=stream_get_contents($handle);
            // Remove case variants so native lowercase prefixes cannot shadow these settings.
            $text=preg_replace('/^'.preg_quote($key,'/').'\s*=.*\R?/mi','',$text);
            $text=rtrim($text)."\n".$key.' = '.$value."\n";
            rewind($handle);
            if (fwrite($handle,$text)!==strlen($text) || !ftruncate($handle,strlen($text))) { throw new \RuntimeException('Cannot save billing configuration.'); }
            fflush($handle);
        } finally { flock($handle,LOCK_UN); fclose($handle); }
    }
}
