<?php

namespace App\Http\Utils;

trait Response {

    function error($code) {
        return response()->json(setFResult($code, $this->errors[$code])); 
    }

    function success($data = []) {
        return response()->json(setTResult($data));
    }

    function validaterError($errData) {
        return response()->json(setFResult($errData['code'], $errData['msg']));
    }
}