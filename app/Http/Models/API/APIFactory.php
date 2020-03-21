<?php

namespace App\Http\Models\API;

class APIFactory {

    private $stationArr = [
        // 山东济南移动
        '0d41bc763fc1519dadab9da2580b1180' => [
            '\App\Http\Models\API\ChinaMobileAPI_Shandong', // 山东省接口
            '5dedceb8113154be9754cb9d0ff82795'  // 中移物联网网关ID
        ],
        // 新山东济南移动
        '0d41bc763fc1519dadab9da2580b1182' => [
            '\App\Http\Models\API\NewChinaMobileAPI', // 新中移基地接口
            '5dedceb8113154be9754cb9d0ff82795'  // 中移物联网网关ID
        ],
        // 新山西太原移动
        '0d41bc763fc1519dadab9da2580b1183' => [
            '\App\Http\Models\API\NewChinaMobileAPI',// 新中移基地接口
            '5dedceb8113154be9754cb9d0ff82795'  // 中移物联网网关ID
        ]
    ];

    const CMCC_IOT = 1;

    /**
     * @param $stationId 落地id
     * @param $type 类型 1-中国移动基地接口(老), 2-默认省配置接口,新平台则默认新基地接口
     */
    function factory($stationId, $type = 2) {
        if ($type == 1) {
            return new ChinaMobileAPI($stationId);
        }
        $obj = false;
        $class = $this->stationArr[$stationId][0];
        if (class_exists($class)) {
            $obj = new $class($stationId,$this->stationArr[$stationId][1]);
        }
        return $obj;
    }
}