<?php

namespace App\Http\Validater;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Mobile
 *
 * @author Admin
 */
class Mobile {
    public function validate($attribute,$value,$parameters,$validator){
        if(preg_match( '/^1[3,4,5,6,7,8,9][0-9]{9}$/',$value)){
            return true;
        }
        return false;
    }
    public function replace($message,$attribute,$rule,$parameters){
        return empty($message) ? $attribute.'手机号不合法' : $message;
    }
}
