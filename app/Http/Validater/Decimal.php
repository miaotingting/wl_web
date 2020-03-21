<?php

namespace App\Http\Validater;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Decimal
 *
 * @author Admin
 */
class Decimal {
    public function validate($attribute,$value,$parameters,$validator){
        if(preg_match( '/^[0-9]+(.[0-9]{1,2})?$/',$value)){
            return true;
        }
        return false;
    }
    public function replace($message,$attribute,$rule,$parameters){
        return empty($message) ? $attribute.'必须为整数或小数' : $message;
    }
}
