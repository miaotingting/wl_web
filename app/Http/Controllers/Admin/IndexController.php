<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exceptions\CommonException;
use App\Http\Models\Admin\IndexModel;

class IndexController extends Controller
{
    public function Index()
    {
        try{
            $result = (new IndexModel)->getIndex($this->user);
            return $this->success($result);
        } catch (Exception $ex) {

        }
    }


}
