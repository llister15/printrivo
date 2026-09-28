<?php
namespace App\Services;
class PrintUploadTicket
{
    private const KEY='print_upload_tickets';
    private const TTL=1800;
    public function issue(int $userId,string $requestId): string
    {
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$requestId)) {
            throw new \InvalidArgumentException('Invalid print request. Refresh the tool and try again.');
        }
        $tickets=$this->active();
        foreach($tickets as $key=>$ticket){if($ticket['user_id']===$userId && $ticket['request_id']===$requestId)unset($tickets[$key]);}
        while(count($tickets)>=5)array_shift($tickets);
        $secret=bin2hex(random_bytes(32));
        $tickets[hash('sha256',$secret)]=['user_id'=>$userId,'request_id'=>$requestId,'expires'=>time()+self::TTL];
        session()->set(self::KEY,$tickets);
        return $secret;
    }
    public function consume(int $userId,mixed $requestId,string $secret): bool
    {
        if($userId<1 || !is_string($requestId) || !preg_match('/^[a-f0-9]{64}$/D',$secret))return false;
        $tickets=$this->active();$key=hash('sha256',$secret);$ticket=$tickets[$key]??null;
        if(!$ticket || $ticket['user_id']!==$userId || $ticket['request_id']!==$requestId)return false;
        unset($tickets[$key]);session()->set(self::KEY,$tickets);
        return true;
    }
    private function active(): array
    {
        $tickets=session()->get(self::KEY);
        return is_array($tickets)?array_filter($tickets,static fn($ticket)=>is_array($ticket)&&($ticket['expires']??0)>time()):[];
    }
}
