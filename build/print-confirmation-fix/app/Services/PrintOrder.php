<?php
namespace App\Services;
use Config\CswPrint;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
class PrintOrder
{
    public function payload(User $user,array $input,string $png): array
    {
        $id=$input['request_id']??null;
        $tool=$input['tool']??null;
        if (!is_string($id) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$id)
            || !is_string($tool) || !in_array($tool,['gang-sheet','image-studio'],true)) {
            throw new InvalidArgumentException('Invalid print request. Reload the tool and try again.',422);
        }
        $dpi=$input['dpi']??null;
        if (!is_scalar($dpi) || !in_array((string)$dpi,['150','300','600'],true) || ($tool==='gang-sheet' && (int)$dpi!==300)) {
            throw new InvalidArgumentException('Choose a supported print DPI.',422);
        }
        $info=@getimagesizefromstring($png);
        if (strlen($png)>CswPrint::MAX_BYTES) { throw new InvalidArgumentException('CSW accepts artwork up to 8 MB. Download this design and contact CSW to arrange a larger file.',413); }
        if (!$info || ($info['mime']??'')!=='image/png' || !str_starts_with($png,"\x89PNG\r\n\x1a\n") || $info[0]<1 || $info[1]<1) {
            throw new InvalidArgumentException('Send a valid exported PNG image.',422);
        }
        if ($info[0]*$info[1]>CswPrint::MAX_PIXELS) { throw new InvalidArgumentException('CSW accepts artwork up to 100 MP. Download this design and contact CSW to arrange a larger file.',422); }
        $name=$input['filename']??'artwork.png';
        if (!is_string($name) || mb_strlen($name)>160 || str_contains($name,"\0")) { throw new InvalidArgumentException('Invalid artwork filename.',422); }
        $name=trim(basename(str_replace('\\','/',$name)))?:'artwork.png';
        $name=mb_substr(pathinfo($name,PATHINFO_FILENAME),0,120).'.png';
        $fulfillment=$input['fulfillment']??null;
        $address=$input['shipping_address']??'';
        if (!in_array($fulfillment,['pickup','shipping'],true) || !is_string($address) || mb_strlen($address)>1000
            || ($fulfillment==='shipping' && mb_strlen(trim($address))<10)) {
            throw new InvalidArgumentException('Choose pickup or shipping and enter a complete shipping address when shipping.',422);
        }
        // Calculate from the actual PNG, never from a browser-supplied total.
        $priceCents=max(1500,(int)round($info[0]*$info[1]*3/((int)$dpi*(int)$dpi)));
        if ((string)($input['confirmed_price_cents']??'')!==(string)$priceCents) {
            throw new InvalidArgumentException('Confirm the current print price before sending your order.',422);
        }
        $printPrice=number_format($priceCents/100,2,'.','');
        $delivery=$fulfillment==='pickup'?'Pickup at CSW Print.':'Shipping requested. Ship to: '.trim($address).'. Shipping quoted separately by CSW.';
        $title=$tool==='gang-sheet'?'DTF gang sheet':'Image Studio print';
        $customer=trim($user->first_name.' '.$user->last_name)?:$user->email;
        return [
            'source'=>'printrivo-'.$tool,'external_id'=>'member-'.$user->id.'-'.$id,
            'customer_name'=>mb_substr($customer,0,160),'email'=>$user->email,
            'job_name'=>$title.' — '.$name,'design_needed'=>'no',
            'notes'=>'Sent by Printrivo member #'.$user->id.'. One copy requested. '.$delivery.' Customer confirmed print subtotal USD $'.$printPrice.' at $0.03 per square inch, $15 minimum. Tax and any shipping are additional; no payment was collected by Printrivo.',
            'production_specs'=>sprintf('Print at %.4f × %.4f inches; %d × %d pixels; %d DPI. Preserve the attached PNG dimensions and transparency. Do not rescale.',$info[0]/(int)$dpi,$info[1]/(int)$dpi,$info[0],$info[1],(int)$dpi),
            'designs'=>[[
                'id'=>'finished-design','name'=>$title,
                'artwork'=>[['id'=>'print-file','name'=>$name,'data_base64'=>base64_encode($png)]],
                'garments'=>[['id'=>'print-copy','item_type'=>$tool==='gang-sheet'?'DTF Gang Sheet':'Print-ready artwork',
                    'category'=>'Unisex','source'=>'csw_house','production'=>['print'],'sizes'=>['Units'=>1],
                    'item_price'=>'0.00','print_price'=>$printPrice]],
                'placements'=>[],
            ]],
        ];
    }

    public function send(int $userId,string $requestId,array $payload): array
    {
        $db=db_connect();$hash=hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
        $find=fn()=>$db->table('print_orders')->where('user_id',$userId)->where('request_id',$requestId)->get()->getRowArray();
        $row=$find();
        if (!$row) {
            // A unique key handles simultaneous submissions. CSW also deduplicates external_id.
            try {
                $db->table('print_orders')->insert(['user_id'=>$userId,'request_id'=>$requestId,'payload_hash'=>$hash,'created_at'=>gmdate('Y-m-d H:i:s')]);
            } catch (\Throwable $error) { if (!$find()) { throw $error; } }
            $row=$find();
        }
        if (!$row || !hash_equals($row['payload_hash'],$hash)) { throw new InvalidArgumentException('This request ID already belongs to different artwork. Start a new print request.',409); }
        if ($row['receipt']) { return json_decode($row['receipt'],true,512,JSON_THROW_ON_ERROR)+['duplicate'=>true]; }
        if ($row['retry_after'] && strtotime($row['retry_after'].' UTC')>time()) {
            throw new InvalidArgumentException('CSW is busy. Wait until '. $row['retry_after'].' UTC, then click Send to printer again.',429);
        }
        $result=service('cswPrintClient')->send($payload);
        $status=$result['status'];$body=$result['body'];
        if (in_array($status,[200,201],true) && isset($body['order_id'],$body['order_number']) && is_scalar($body['order_id']) && is_scalar($body['order_number'])) {
            $receipt=['order_id'=>(string)$body['order_id'],'order_number'=>(string)$body['order_number']];
            $db->table('print_orders')->where('id',$row['id'])->update(['receipt'=>json_encode($receipt,JSON_THROW_ON_ERROR)]);
            return $receipt+['duplicate'=>(bool)($body['duplicate']??false)];
        }
        if ($status===429) {
            $header=$result['retry_after']??'';
            $seconds=ctype_digit((string)$header)?(int)$header:max(60,(strtotime((string)$header)?:time()+60)-time());
            $seconds=max(1,min(86400,$seconds));
            $db->table('print_orders')->where('id',$row['id'])->update(['retry_after'=>gmdate('Y-m-d H:i:s',time()+$seconds)]);
            throw new InvalidArgumentException('CSW is busy. Wait '.$seconds.' seconds, then click Send to printer again.',429);
        }
        $message=match($status) {
            401,403=>'CSW could not authorize this connection. Ask the administrator to check the DTF webhook token and enabled status.',
            409=>'CSW already has a different order for this request. Contact support before sending again.',
            413=>'CSW rejected the artwork size. Download your PNG and contact CSW to arrange delivery.',
            400,415,422=>'CSW could not accept this artwork or order format. Download your PNG and contact support.',
            default=>'CSW has not confirmed the order. Click Send to printer again to retry the same request safely.',
        };
        throw new InvalidArgumentException($message,$status===409?409:502);
    }
}
