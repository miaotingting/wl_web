<?php

namespace App\Console\Commands;

use App\Http\Models\Operation\StoreOutDetailModel;
use DateTime;
use Illuminate\Console\Command;

class test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wl:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // 8.转移库存到已出库仓库中
        // $iccidList = StoreOutDetailModel::where('store_out_id','b2daecfb0e6b5bfcb780275aaad20549')->pluck('iccid')->toArray();
        // $iccidStr = implode("','",$iccidList);
        
        // echo $bantchMoveCardsSql = "SELECT * FROM t_c_warehouse_order_detail WHERE iccid IN ('$iccidStr')";
        $start = '2020-08-31';
        $end = date('Y-m-d');

        // dd($start);
        dd($this->getMonthNum($end, $start));

    }

    protected function getMonthNum( $date1, $date2, $tags='-' ){
        $date1 = explode($tags,$date1);
        $date2 = explode($tags,$date2);
        return abs($date1[0] - $date2[0]) * 12 - abs($date2[1] + $date1[1]);
    }

}
