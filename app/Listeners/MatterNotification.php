<?php

namespace App\Listeners;

use App\Events\MatterEvent;
use App\Http\Models\Admin\NoticeModel;
use App\Http\Models\Matter\DefineModel;
use App\Http\Models\Matter\NodeModel;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class MatterNotification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  MatterEvent  $event
     * @return void
     */
    public function handle(MatterEvent $event):void
    {
        //创建事项提醒
        $defineData = DefineModel::where('task_code',$event->code)->first(['task_id']);
        $nodeData = NodeModel::where('task_id',$defineData->task_id)->distinct()->get(['exec_role_id']);
        $noticeData['title'] = $event->title;
        $noticeData['content'] = $event->content;
        $noticeData['type'] = 0;
        $noticeData['level'] = 1;
        $impowerIds = '';
        foreach($nodeData as $value){
            $impowerIds = $impowerIds.$value->exec_role_id.',';
        }
        $noticeData['impowerIds'] = trim($impowerIds,',');
       (new NoticeModel)->addNoticeCaution($noticeData, $event->user);
    }
}
