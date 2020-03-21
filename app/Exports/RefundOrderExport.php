<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use App\Http\Models\Admin\TypeDetailModel;
use App\Http\Models\Order\TCCardRefundOrderDetailModel;

class RefundOrderExport implements FromCollection,WithHeadings,WithMapping
{

    private $no;
    private $search;
    function __construct(string $no, array $search)
    {
        $this->no = $no;
        $this->search = $search;
    }

    const pageIndex = 1;

    public function headings(): array
    {
        $heads=['卡号','iccid', 'imsi', '发卡时间', '激活时间', '服务期', '卡余额', '开卡单号', '退卡单号', '运营商', '卡状态'];
        return $heads;
    }

    /**
    * @var Invoice $invoice
    */
    public function map($row): array
    {
        
        $rows=[$row->card_no,$row->iccid, $row->imsi, $row->sale_date, $row->active_date, $row->valid_date, $row->card_account, $row->order_no, $row->no, $row->operator_type, $row->status];
        return $rows;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $detailModel = new TCCardRefundOrderDetailModel;
        $count = $detailModel->where('no', $this->no)->count('id');
        $cards = $detailModel->getCards(self::pageIndex, $count, $this->no, $this->search);
        foreach($cards['data'] as $data) {
            foreach($data->dicArr as $col => $dic) {
                $dics = TypeDetailModel::getDetailsByCode($dic);
                if (array_key_exists($data->$col, $dics) && array_key_exists('name', $dics[$data->$col])) {
                    $data->$col = $dics[$data->$col]['name'];
                }
				
            }
        }
        return $cards['data'];
    }
}
