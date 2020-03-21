<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\TSysCompany;

class TSysCompanyController extends Controller
{
   
    /*
     * 采购主体列表
     */
    public function getSysCompany(){
        try{
            $result = (new TSysCompany())->getSysCompany();
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    
    
    


    
        
        
}
