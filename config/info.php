<?php 
    /*
    * 根据项目需要自定义的Info信息配置
    * 相当于静态数据使用
    */
	return [
	    'WL_TITLE'      =>  '网来物联云管理平台',
	    'WL_LOGO'       =>  '123456',
        'WL_COPY'       =>  '&copy; 2019 All Rights Reserved. WL',
        'WL_UUID_KEY'   =>  env('WL_UUID_KEY', 'wl_uuid_key'),
        'SALT'          =>  'wliot@900107',
        'JWT_SECRET'    =>  env('JWT_SECRET', 'wl_jwt_secret'),
        'JWT_EXP_TIME'  =>  env('JWT_EXP_TIME', 3600 * 12),
        'ENV_ARR'       =>  [
                                'dev',
                                'local',
                                'test'
                            ],
        'operator_type' =>  [
                                'CMCC'  => '中国移动',
                                'CUCC'  => '中国联通',
                                'CTCC'  => '中国电信'
                            ],
        'role_xiaoshou_id' =>  '456e33858433527eb219cffb4a133698',  //销售人员角色ID
        'role_admin_id'    =>  '93c2d25688755be580748effafba9663',  //管理员角色
        'role_xszj_id'    =>  '3dfcbc9de35d5dcb8b87d8059d9a6337',  //销售总监
        'role_shouhou_id'    => env('ROLE_SHOUHOU_ID','bfbef97077c65904aa0e3049467d5e9f'),//售后人员角色ID
        'role_shzj_id'    => env('ROLE_SHZJ_ID','b37682b671d65b6780d8f9d85e1efed4'),//售后总监角色ID
        'role_first_customer_id' => 'd3bbc1ce6ffd55c9844571b600fe3154',//一级客户角色
        'role_second_customer_id' => 'da45c607be5f561fa2f5d6f3cc5f15d7',//二级客户角色    
        'role_finance_id' => '8dc288d0ff725d6f9fb9d720c084880f',//财务人员角色   
        'special_customer_id' => env('SPECIAL_CUSTOMER_ID','f052eaf129d052fa9d6045ced27e62a6'),//特定客户ID 
        'level' =>          [
                                2  => '二级用户',
                                0  => '网来科技',
                                1  => '一级用户',
                                
                            ],
        'customer_type' =>  [
                                1  => '行业卡客户',
                                2  => '个人卡累计客户',
                                3  => '个人卡月卡客户'
                            ],
        'renewal_way' =>  [
                            1  => '资费计划续费',
                            2  => '订单续费'
                        ],
        'company_type' =>  [ //公司性质
                            'ownstate'  => '国有企业',
                            'foreign'  => '外资企业',
                            'private '  => '民营企业',
                            'collective '  => '集体企业',
                            'joint'  => '股份制企业',
                            'venture'  => '合资企业',
                            'proprietor'  => '独资企业',
                            'other'  => '其它',
                        ],
        'source_type' =>  [//来源途径
                            'internet'  => '网络 ',
                            'provide'  => '主动上门 ',
                            'website '  => '客户网站 ',
                            'introducte '  => '介绍 ',
                            'other'  => '其它',
                        ],
        'area_type' =>  [ //所属区域
                            'huabei'  => '华北区',
                            'dongbei'  => '东北区',
                            'huadong'  => '华东区',
                            'zhongnan'  => '中南区',
                            'xinan'  => '西南区',
                            'xibei'  => '西北区',
                        ],
        'industry_type' =>  [ //行业分类 
                            'associate'  => '车载物联',
                            'finance'  => '移动金融',
                            'wifi'  => '无线应用',
                            'other'  => '其它',
                        ],    
        'customer_level' =>  [ 'A','B','C','D' ],//客户等级
        'company_size' =>  [ //公司规模 
                            '0-20'  => '0-20人',
                            '20-50'  => '20-50人',
                            '50-100'  => '50-100人',
                            '100-200'  => '100-200人',
                            '200-500'  => '200-500人',
                            '500'  => '500人以上',
                        ],  

        'package_type' => [//套餐类型
                            "sms"  => '短信',
                            "flow"  => '流量',
                            "voice"  => '语音',
                        ],                
        'settlement_type'=> [//结算类型
                            1  => '自定义',
                            0  => '按月',
                            
                        ],  
        'time_unit'=> [//时间单位
                            'month'  => '月',
                            'day'  => '天',
                        ], 
        'fees_type'=>[//计费类型
                            2  => '加餐包套餐 ',
                            0  => '标准套餐 ',
                            1  => '前置套餐 ',
                            
                    ], 
        'pay_type'=>[//付款方式
                            4  => '货到付款 ',
                            0  => '银行转账 ',
                            1  => '支付宝 ',
                            2  => '微信 ',
                            3  => '现金 ',
                            
                    ], 
        'card_status'=>[//卡片状态
                            '-1'  => '废卡 ',
                            '0'  => '白卡 ',
                            '1'  => '待激活 ',
                            '2'  => '正常 ',
                            '3'  => '停机 ',
                            '4'  => '异常 ',
                            '5'  => '停机保号 ',
                    ], 
        'machine_status'=>[//活动状态
                        2  => '未知 ',
                        0  => '开机 ',
                        1  => '关机 ',
                        
                ],

        'sale_order_status' => [
            [
                'key' => 0,
                'value' => '已提交',
            ],
            [
                'key' => 1,
                'value' => '已受理',
            ],
            [
                'key' => 2,
                'value' => '订单结束'
            ],
            [
                'key' => 3,
                'value' => '驳回'
            ],
            [
                'key' => -1,
                'value' => '删除'
            ],
            [
                'key' => 4,
                'value' => '待收款',
            ],
            [
                'key' => 5,
                'value' => '待发货',
            ],
        ],
        'sale_order_standard_type' => [
            '2G', '3G', '4G', '2G/3G自适应'
        ],
        'valid_date' =>  [ //服务期止
                            'pastDue'  => '已过期',
                            'threeDay'  => '三天内',
                            'week '  => '一周内',
                            'month '  => '一月内',
                            'threeMonth'  => '三月内',
        ],    
	];
 ?>