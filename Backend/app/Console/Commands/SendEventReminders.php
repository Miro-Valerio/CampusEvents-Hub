<?php
namespace App\Console\Commands;
use App\Models\Rsvp;
use App\Services\Hub;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class SendEventReminders extends Command {
 protected $signature='events:remind';
 protected $description='Queue one in-app reminder per RSVP within 24 hours before an event';
 public function handle():int {
  $count=0;
  Rsvp::with(['event','user'])->where('status','active')->whereNull('reminder_sent_at')
   ->whereHas('event',fn($q)=>$q->where('status','published')->whereBetween('starts_at',[now(),now()->addDay()]))
   ->chunkById(100,function($rows)use(&$count){foreach($rows as $row){
    DB::transaction(function()use($row,&$count){
     $rsvp=Rsvp::with(['event','user'])->whereKey($row->id)->lockForUpdate()->first();
     if(!$rsvp || $rsvp->status!=='active' || $rsvp->reminder_sent_at || !$rsvp->event || $rsvp->event->status!=='published')return;
     if(!$rsvp->event->starts_at->between(now(),now()->addDay()))return;
     Hub::notify($rsvp->user,'reminder','Your event is coming up',
      $rsvp->event->title.' starts '.$rsvp->event->starts_at->format('M d, Y g:i A').'.',
      '/events/'.$rsvp->event->id,'event-reminder:rsvp:'.$rsvp->id.':'.$rsvp->event->starts_at->format('YmdHis'));
     $rsvp->update(['reminder_sent_at'=>now()]);$count++;
    });
   }});
  $this->info('Processed '.$count.' RSVP reminder(s).');return self::SUCCESS;
 }
}
