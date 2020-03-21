<?php

namespace App\Http\Models\Admin;

use App\Http\Models\BaseModel;
use App\Http\Models\Admin\User;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\TypeDetailModel;


class TSysCompany extends BaseModel
{
    protected $table = 'sys_company';
    /*
     * 获取采购主体全部信息
     */
    public function getSysCompany(){
        $data = $this->get(['id','company_code','company_name','company_abbr']);
        return $data;
    }
    
    
    
    
    
    
    
    
    
    
    
    
}
