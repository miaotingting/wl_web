<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Models\Admin\Station;
use App\Exceptions\CommonException;

class StationController extends Controller
{
    /*
     * get.api/Admin/station
     * 落地列表
     */
    public function index(Request $request)
    {
        try{
            $result = (new Station)->getStation($request->all(), $this->user);
            return $this->success($result);
        } catch (Exception $ex) {
            throw new CommonException('101010');
        }
        
    }
    
    
    
    

}
