<?php 
//网来common中的function
// +----------------------------------------------------------------------
// | Laravel [ WE CAN DO IT JUST LARAVEL IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://laravel.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------




function getEnums(array $enums) {
	$arr = [];
	foreach($enums as $key => $value) {
		$temp = [];
		$temp['id'] = $key;
		$temp['name'] = $value;
		$arr[] = $temp;
	}
	return $arr;
}

/**
 * @param {string} $name 用户名
 * @return {string}
 */
function getUuid(string $name = 'default') {
	$timeHash = hash('sha256', config('info.WL_UUID_KEY') . microtime());
	$hash = hash('sha256', $name . $timeHash . config('info.WL_UUID_KEY') . microtime());
	$uid =  Uuid::generate(5, $hash . microtime(), Uuid::NS_DNS);
	return implode('', explode('-', $uid->string));
}

/**
 * 设置接口★★★成功★★★返回函数，并设置数据转换成驼峰格式
 * @param  {array} $data
 * @return array
 */
function setTResult($data,$msg='Success') {

	if(gettype($data) == 'object'){
		$className = get_class($data);
		$model = new $className;
		if (property_exists($model, 'dicArr')) {
			foreach ($model->dicArr as $col => $dic) {
				if (array_key_exists($col, $data->toArray())) {
					if (empty($data->$col) && $data->$col != 0) {
						$data->$col = ['code' => '000000', 'name' => $data->$col];
					} else {
						$dics = \App\Http\Models\Admin\TypeDetailModel::getDetailsByCode($dic);
						if (array_key_exists($data->$col, $dics)) {
							$data->$col = $dics[$data->$col];
						} else {
							$data->$col = ['code' => '000000', 'name' => $data->$col];
						}
                        
                    }
				}
				
			}
		}
		
		$data = $data->toArray();
	}

	//返回列表时候
	if (is_array($data) && array_has($data, 'data')) {
		if(gettype($data['data']) == 'object'){
			
			foreach ($data['data'] as $class) {
				$className = get_class($class);
				$model = new $className;
				if (property_exists($model, 'dicArr')) {
					foreach ($model->dicArr as $col => $dic) {
                        if (array_key_exists($col, $class->toArray())) {
							if (empty($class->$col) && $class->$col != 0) {
								$class->$col = ['code' => '000000', 'name' => $class->$col];
							} else {
								$dics = \App\Http\Models\Admin\TypeDetailModel::getDetailsByCode($dic);
								if (array_key_exists($class->$col, $dics)) {
									$class->$col = $dics[$class->$col];
								} else {
									$class->$col = ['code' => '000000', 'name' => $class->$col];
								}
							}
							
                        }
					}
				}
			}
                        
			$data['data'] = $data['data']->toArray();
		}
	}
	
	$result = array();
	$result['status'] = 0;
	$result['msg'] = $msg;
	$result['timeStamp'] = date("Y-m-d H:i:s");
	$result['data'] = is_array($data)?setHump($data):$data;
	return $result;
}

/**
 * 设置接口★★★失败★★★返回函数，并设置数据转换成驼峰格式
 * @param  {String,String} $code $msg
 * @return array
 */
function setFResult($code, $msg) {
	$result = array();
	$result['status'] = $code;
	$result['msg'] = $msg;
	$result['timeStamp'] = date("Y-m-d H:i:s");
	$result['data'] = [];
	return $result;
}

/**
 * 获取已登录用户信息
 * @param  {String} $token
 * @return array
 */
function getUserMsg($token) {
	return \Illuminate\Support\Facades\Redis::get($token);
}

/*
 * 无限极分类
 * @param $data	二维数组，$pid  父ID
 * @return	array
 */
function getTree($data,$pid = 0){
	$tree = array();
	foreach ($data as $key => $value){
		if ($value['parent_id'] == $pid) {
			$value['children'] = getTree($data, $value['id']);
			if (!$value['children']) {
				unset($value['children']);
			}
			$tree[] =$value;
		}
	}
	return $tree;
}

/*
 * 无限极分类，element树形结构
 * @param $data:二维数组，$field:结构名称,$pid:父ID
 * @return	array
 */
function getMapTree($data, $field, $pid = 0){
	$mapTree = array();
	$temp = array();
	foreach ($data as $key => $value){
		if ($value['parent_id'] == $pid) {
			$value['children'] = getMapTree($data, $field, $value['id']);
			$temp['id'] =$value['id'];
			$temp['label'] =$value[$field];
			$temp['parent_id'] = $value['parent_id'];
			$temp['children'] = array_key_exists('children',$value)?$value['children']:'';
			if (!$temp['children']) {
				unset($temp['children']);
			}
			$mapTree[] = $temp;
		}
	}
	return $mapTree;
}

/**
 * 处理uuid主键TreeBug的函数
 */
function backTree($tree, $pid = '0'){
	$backTree = array();
	foreach ($tree as $key => $val) {
		if($val['parent_id'] != $pid){
			unset($tree[$key]);
		}else{
			$backTree[] = $val;
		}
	}
	return $backTree;
}

/**
 * 将数组递归键转换成驼峰格式
 * @param [type] array $data
 * @param boolean $ucfirst
 * @return void
 */
function setHump ($data , $ucfirst = true) {
	$newData = array();
	foreach($data as $key => $value){
		$str = ucwords(str_replace('_', ' ', $key));
		$newKey = str_replace(' ','',lcfirst($str));
		// dump($value);
		if(is_array($value) || gettype($value) == 'object'){
			$newData[$newKey] = setHump($value);
        }else{
			$newData[$newKey] = $value;
		}
	}
	return $newData;
}

/**
 *  生成指定长度的随机字符串(包含大写英文字母, 小写英文字母, 数字)
 *
 * @author Wu Junwei <www.wujunwei.net>
 *
 * @param int $length 需要生成的字符串的长度
 * @return string 包含 大小写英文字母 和 数字 的随机字符串
 */
function random_str($length = 8)
{
	$str1 = '0123456789';
	$str2 = 'abcdefghijklmnopqrstuvwxyz';
	$str3 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

	$count1 = rand(1,3);
	$pass1 = substr(str_shuffle($str1),0,$count1);

	$count2 = rand(1,3);
	$pass2 = substr(str_shuffle($str2),0,$count2);

	$count3 = $length - $count1 - $count2;
	$pass3 = substr(str_shuffle($str3),0,$count3);
	$password = str_shuffle($pass1.$pass2.$pass3);

	return $password;
}
/*
 * 生成随机的编号(当前时间+6位随机数)
 */
function getOrderNo($str,$customerCode=''){
    $rand1 = rand(100000,999999);
    $date = date('Ymd',time());
    $code = $str.$customerCode.$date.str_shuffle($rand1);
    return $code;
}
/*
 * 生成复杂的随机的编号(当前时间+小时+6位随机数)
 */
function getComOrderNo($str){
    $rand1 = rand(100000,999999);
    $date = date('YmdH',time());
    $code = $str.$date.str_shuffle($rand1);
    return $code;
}
/*
 * 判断0的类型
 */
function is_zero($str){
    if($str === 0){
        return true;
    }
    if($str === '0'){
        return true;
    }
    return false;
}
/**
 * 读取excel转换成数组
 * @param string $excelFile 文件路径
 * @param array $header 设置字段从0开始 $header=['0'=>'cardNo',"1"=>'iccid',"2"=>"imsi"];
 * @param string $excelType excel后缀格式
 * @param int $startRow 开始读取的行数
 * @param int $endRow 结束读取的行数
 * @param array $notNullRow 不允许为空的列数组 默认false
 * @param array $dateRow 有日期的进行格式化处理 默认false
 * @author xyh
 * @retunr array
 */
function importExcel($excelFile, $header, $excelType = 'xls', $startRow = 1, $endRow = null, $notNullRow=false, $dateRow=false) {
    include_once public_path('org/PHPExcel/PHPExcel.php');
    include_once public_path('org/PHPExcel/PHPExcelReadFilter.php');
	if(empty($excelFile) or !file_exists($excelFile)){return [false,'文件不存在！'];}
	if($excelType === 'xls'){
		$excelReader = \PHPExcel_IOFactory::createReader("Excel5");
	}else if($excelType === 'xlsx'){
		$excelReader = \PHPExcel_IOFactory::createReader("Excel2007");
	}else{
		return [false,'请传入正确excel格式文件！'];
	}
    $excelReader->setReadDataOnly(true);
    //如果有指定行数，则设置过滤器
    if ($startRow && $endRow) {
        $perf           = new PHPExcelReadFilter();
        $perf->startRow = $startRow;
        $perf->endRow   = $endRow;
        $excelReader->setReadFilter($perf);
    }
    $phpExcel    = $excelReader->load($excelFile);
    $activeSheet = $phpExcel->getActiveSheet();
    if (!$endRow) {
        $endRow = $activeSheet->getHighestRow(); //总行数
    }
    $highestColumn      = $activeSheet->getHighestColumn(); //最后列数所对应的字母，例如第2行就是B
    $highestColumnIndex = \PHPExcel_Cell::columnIndexFromString($highestColumn); //总列数
	$data = array();
	for ($row = $startRow; $row <= $endRow; $row++) {
        for ($col = 0; $col < $highestColumnIndex; $col++) {
			$indexRow = $activeSheet->getCellByColumnAndRow($col, $row)->getValue();
			//富文本转换字符串
			if($indexRow instanceof PHPExcel_RichText){ 
                $indexRow = $indexRow->__toString();
			}
			//如果有日期的列进行格式化
			if($dateRow != false && is_array($dateRow)){
				if (in_array($col+1, $dateRow)) {
					$indexRow = gmdate('Y-m-d',PHPExcel_Shared_Date::ExcelToPHP($indexRow));
				}
			}
			$data[$row][$header[$col]] = $indexRow;
			//设置如果中间存在空值，直接返回，不再读取后面数据
			if($notNullRow != false  && is_array($notNullRow)){
				if(in_array($col+1, $notNullRow)){
					if(strlen(trim($indexRow)) < 1){
						array_pop($data);
						return $data;
					}
				}
			}
        }
	}
    return $data;
}

/**
 * 日志
 * @param [type] $logStr
 * @return void
 */
function appLog($logStr){
	// Linux上要写绝对路径
	// $filename = './logs/cmpp3Submit-' . date('Y-m-d', time()) . '.log';
	if(!is_dir(storage_path('app/Logs'))){
		mkdir(storage_path('app/Logs'),0777);
	}
	$filename = storage_path('app/Logs/applogs-'.date('Y-m-d').'.log');
	// file_put_contents($filename, $logStr . PHP_EOL, FILE_APPEND);
}


function putExcelData($result,$titleArr,$valueArr) {
    include_once public_path('org/PHPExcel/PHPExcel.php');
    ob_end_clean();//清除缓冲区,避免乱码
    error_reporting(E_ALL);
    date_default_timezone_set('Europe/London');
    $objPHPExcel = new PHPExcel();
    /* 以下是一些设置 ，什么作者  标题啊之类的 */
    /*$titleArr=['卡号','iccid','落地名称','所属公司','客户经理','运营商','卡状态','活动状态','发卡日期',
        '激活时间','服务期止','流量套餐','流量套餐总量(MB)','流量使用量(MB)','流量套餐剩余量(MB)',
        '短信套餐','短信套餐总量(条)','短信发送量(条)','短信套餐剩余量(条)','语音套餐','语音套餐总量(分钟)',
        '语音使用量(分钟)','语音套餐剩余量(分钟)'];*/
    /* 以下就是对处理Excel里的数据， 横着取数据，主要是这一步，其他基本都不要改 */
    /*$valueArr = ['card_no','iccid','station_name','customer','account_manager_name','operator_type',
        'status','machine_status','sale_date','active_date','valid_date','flow_package_name',
        'flow_total','flow_used','flow_residue','sms_package_name','sms_total','sms_used',
        'sms_residue','voice_package_name','voice_total','voice_used','voice_residue'];*/
    
    for($i = 0;$i<count($titleArr);$i++){
        $num = $i+65;
        $objPHPExcel->setActiveSheetIndex(0)
                        ->setCellValue(strtoupper(chr($num)).'1', $titleArr[$i]);
	}
    if(!empty($result)){
        for($i = 0; $i < count($result); $i++){
            $num = 64;
            $result[$i] = get_object_vars($result[$i]);
            foreach($valueArr as $key=>$value){
                $num++;
                /*$objPHPExcel->setActiveSheetIndex(0)
                            ->setCellValue(strtoupper(chr($num)).'1', $titleArr[$key]);*/
                $objPHPExcel->getActiveSheet(0)->setCellValue(strtoupper(chr($num)) . ($i + 2), $result[$i][$value]);
            }
        }
    }


    $objPHPExcel->getActiveSheet()->setTitle('card');
    $objPHPExcel->setActiveSheetIndex(0);
   header('Content-Type: application/vnd.ms-excel');
   header('Content-Disposition: attachment;filename="card.xls"');
   header('Cache-Control: max-age=0');
     
	$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
	// if(!is_dir(storage_path('app/export/expireCard'))){
	// 	mkdir(storage_path('app/export/expireCard'),0777);
	// 	if (!file_exists(storage_path('app/export/expireCard/excel')))
	// }
    // file_put_contents(storage_path('excel.xls'), '123');
	// $objWriter->save(storage_path('excel.xls'));
	return $objWriter->save("php://output");
}


			






























/**
 * @param ************************以下函数未经测试，使用时需测试****************************
 */
//计算页面记录总数
function countNum($str){
    $a = strrpos($str," ");
    $b = mb_substr($str, $a+1);
    $c = strrpos($b,"条");
    $d = intval(mb_substr($b, 0,$c));
    return $d;
}

//模拟登陆，破解标签内容
function checkWLW($html){
    if (!is_null($html)) {
        foreach ($html as $element) {
            $nodes = $element->childNodes;
            foreach ($nodes as $node) {
                return $node->nodeValue;
            }
        }
    }
}

/**
 * 获取IP
 * @return string
 */
function get_client_ip2(){
	if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown")){
		$ip = getenv("HTTP_CLIENT_IP");
	}else if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown")){
		$ip = getenv("HTTP_X_FORWARDED_FOR");
	}else if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown")){
		$ip = getenv("REMOTE_ADDR");
	}else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown")){
		$ip = $_SERVER['REMOTE_ADDR'];
	}else{
		$ip = "unknown";
	}
	return($ip);
}

/**
 * 多维数组判断值是否存在
 */
function isExist($value, $array)
{
	return strpos(var_export($array, true), $value);
}

/**
 * 生成Excel文件直接导出(方法很好用)
 * $title           => excel名称
 * $tableTitle      => excel标题
 * $tableContent    => excel内容
 */
function ExportExcel($title,$tableTitle,$tableContent,$filter=true){
	set_time_limit(0);
	$filter_title_arr = array('创建人名称','创建时间','修改人名称','修改时间');
	$filter_arr = array('create_user_name','create_date','modify_user_name','modify_date');
	ini_set('memory_limit', '2048M');
	header("Content-type:application/vnd.ms-excel");
	header("Content-Disposition:attachment;filename=".$title.date("Y_m_d", time()).".xls");
	$html = '';
	$html .='<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"> 
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
	<html> 
	<head> 
		<meta http-equiv="Content-type" content="text/html;charset=UTF-8" /> 
	</head> 
		<body> 
			<div align=center x:publishsource="Excel"> 
				<table x:str border=1 cellpadding=0 cellspacing=0 >';
					$html .='<tr>';
						foreach($tableTitle as $t){
							if(!$filter || ($filter && !in_array($t,$filter_title_arr))) {
								$html .= '<td>' . $t . '</td>';
							}
						}
					$html .='</tr>';
					foreach($tableContent as $k=>$c){
						//获取所有数组键名
						$keys = array_keys($tableContent[$k]);
						$html .= "<tr>";
						for($j=0;$j<count($keys);$j++){
							if(!$filter || ($filter && !in_array($keys[$j],$filter_arr))){
								$content = $c[$keys[$j]]?$c[$keys[$j]]:"--";
								$html .= "<td>".$content."</td>";
							}
						}
						$html .= "</tr>";
					}
				$html .='</table>
			</div> 
		</body> 
	</html>';
	echo $html;
	//给定导出excel时的弹层状态
	session(C('EXPORT_EXCEL'),1);
	exit;
}

/**
 * 判断文件夹是否存在，不存在则增加（返回文件路径）
 * @$route_name => 文件夹名称
 * @$date_status => 子文件夹名称（是否需要时间做文件夹）
 */
function FileName($route_name='files',$date_status=true){
	$file_url = C('FILE_UPLOAD_URL');
	$file_url .= $route_name."/";
	if($date_status)$file_url .= date("Ymd")."/";
	if(!file_exists($file_url)) {
		@mkdir($file_url, 0777, true);
		@chmod($file_url, 0777);
	}
	return $file_url;
}

/**
 * 上传图片、文件功能
 */
function FileUpload($photo,$savePath='file',$type='all'){
	FileName($savePath);
	$upload = new \Think\Upload();
	//$upload = new Upload();// 实例化上传类
	if($type=='img'){
		$upload->exts = array('jpg', 'gif', 'png', 'jpeg','bmp','ico');// 设置附件上传类型（图片类）
	}else if($type=='file'){
		$upload->exts = array('docx','doc','xlsx','xls','rar','zip','pem','pdf');// 设置附件上传类型（文件类）
	}else{
		$upload->exts = array('docx','doc','xlsx','xls','rar','zip','pem','pdf','jpg', 'gif', 'png', 'jpeg','bmp','ico');// 设置附件上传类型（所有类型）
	}
	$upload->maxSize   =     5242880 ;// 设置附件上传大小       5M
	$upload->rootPath  =     C('FILE_UPLOAD_URL').$savePath.'/'; // 设置附件上传根目录
	//$upload->savePath  =     $savePath.'/'; // 设置附件上传（子）目录
	$upload->subName   =     array('date', 'Ymd');
	//上传文件
	$file = $upload->uploadOne($photo);
	if($file){
		$info['status'] = 1;
		$info['name'] = $file['savepath'].$file['savename'];
	}else{
		$info['status'] = 0;
		$info['name'] = $upload->getError();
	}
	return $info;
}

/**
 * 下载图片、文件功能
 */
function FileDownload($filename,$status=false){
	$filepath=$filename;
	$filename =basename($filename);
	if(!$status){
		$type=explode('.',$filename);
		$filename = $type[0].time().'.'.$type[1];
	}
	$filesize = filesize($filepath);
	header("content-type:application/octet-stream");
	header("content-disposition:attachment;filename=".$filename);
	header("content-length:{$filesize}");
	readfile($filepath);
}

/**
 * 打包成压缩包方式后下载（生成压缩包的文件地址对应把包文件的地址）
 * @$save_path => 需打包的文件目录
 * @$file_url => 文件所在目录
 * @$file_name_prefix => 压缩包名称
 * @$download_file => 需下载的文件名称（数组格式或字符串格式）
 * @$date_status => 压缩包名称是否需要加时间做名称
 */
function ZipFileDownload($save_path,$file_url,$download_file,$zip_name='zip',$date_status=true){
	import('Org.Util.FileToZip');
	$handler = opendir($file_url);		//$file_url 文件所在目录
	closedir($handler);
	$scandir=new \traverseDir($save_path,$file_url);	//需打包的文件目录，zip包文件目录
	if($date_status)$zip_name = $zip_name.date("YmdHis",time());	//新建zip文件名
	$scandir->tozip($download_file,$zip_name);
}

/**
 * 删除图片、文件功能
 * @$files => 需删除的文件
 */
function FileDelete($files){
	if(!is_array($files)){
		$files = explode('|',$files);
	}
	if($files){
		foreach($files as $v){
			if(file_exists($v))@unlink($v);
		}
	}
}

/**
 * 生成订单流水编号
 * @$digit  生成随时的个数(默认为4位)
 */
function apply_number($number,$digit = 4){
	$salttype = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
	$keys = array_rand($salttype, $digit);
	$ints = '';
	foreach ($keys as $v) {
		$ints.= $salttype[$v];
	}
	return $number.time().$ints;
}

/**
 * 分割字符串（用于权限判断）
 */
function action_name($action){
	//获取指定字符串在字符串中的位置
	$i = strrpos($action,'_');
	if($i){
		//截取字符串(从第一个到指定位置)
		$list['name'] = mb_substr($action,0,$i,'utf-8');
		//截取字符串(从指定位置到最后)
		$list['postfix'] = mb_substr($action,$i+1,mb_strlen($action,'utf-8'),'utf-8');
		return $list;
	}else{
		return $action;
	}
}

/**
 * 判断用户是否有操作权限（用于页面权限判断）
 */
function is_jurisdiction($rbacurl){
	//判断是否有SESSION
	if(!$admin_info = session('admin')){
		//跳转到登录页面
		$this->redirect('Index/login');
	}else{
		//如果是超级管理员直接为真
		if(!$admin_info['is_admin']){
			//判断是否有可操作的权限
			if(!in_array(strtolower($rbacurl),$admin_info['func_list'])){
				return false;
			}else{
				return true;
			}
		}else{
			return true;
		}
	}
}

/**
 *  判断字符串是否包含大写英文字母, 小写英文字母, 数字
 *
 */
function check_random_str($password, $length=8){
	if(preg_match('/\d/is', $password) && preg_match('/[a-z]+/', $password) && preg_match('/[A-Z]+/', $password) && strlen($password)>=$length){
		return true;
	}else{
		return false;
	}
}

/**
 *  生成地区二级联动json数据
 * @$type => 是否读取全国，为真不读取，为假读取
 */
function get_address($type=true){
	if($type)$where['sort'] = array('not in',array(0));
	$province_list = M('sys_province')->field('province_id,province_name')->where($where)->index('province_id')->select();
	$province = array();
	foreach($province_list as $key=>$val){
		$province[$key] = $val['province_name'];
	}
	$city_list = M('sys_city')->field('city_id,city_name,province_id')->index('city_id')->select();
	$city = array();
	foreach($city_list as $key=>$val){
		$city['0,'.$val['province_id']][$key] = $val['city_name'];
	}
	$address = array_merge(array(0=>$province),$city);
	return json_encode($address);
}

// create by Admin aioluos
function array_to_excel($header,$data,$filename='default'){
    if(!class_exists('PHPExcel')){
	require '/st_rbac/Public/PhpExcel/PHPExcel.php';
    }
    date_default_timezone_set('PRC');
    $file_write = "/st_rbac/Upload/checkCard/".$filename.".xls";
    fopen($file_write,"wb");
    $objPHPExcel = new \PHPExcel();
    /*以下是一些设置 ，什么作者  标题啊之类的*/
    $objPHPExcel->getProperties()->setCreator("admin")
        ->setLastModifiedBy("admin")
        ->setTitle("用户导出")
        ->setSubject("数据EXCEL导出")
        ->setDescription("备份数据")
        ->setKeywords("excel")
        ->setCategory("数据导出");
    /*以下就是对处理Excel里的数据， 横着取数据，主要是这一步，其他基本都不要改*/
    foreach ($header as $column => $value ){
        //Excel的第A列
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue($column.'1', $value['label']);
    }
    foreach($data as $key => $val){
        $num= $key+2;
        foreach ($header as $ke => $va ) {
            $objPHPExcel->setActiveSheetIndex()->setCellValue($ke.$num, $val[$va['key']]);
        }
    }
    $objPHPExcel->getActiveSheet()->setTitle('User');
    $objPHPExcel->setActiveSheetIndex(0);
    header('Content-Type: application/vnd.ms-excel');
    //header('Content-Disposition: attachment;filename="'.$filename.'.xls"');
    header('Content-Disposition: attachment;filename="/st_rbac/Upload/checkCard/'.$filename.'.xls"');
    header('Cache-Control: max-age=0');
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    //$objWriter->save('php://output');
    // 如果不是直接下载可以存到一个默认的文件夹
    $objWriter->save("/st_rbac/Upload/checkCard/{$filename}.xls");
    unset($objPHPExcel);
    unset($objWriter);
}

// 文件下载
function down_load($url,$filename='') {
    $date = date("Y-m-d");
    // $save_dir = "D:/work/st_rbac/Upload/EC_down_load_backups/{$date}/";
    $save_dir = "/st_rbac/Upload/EC_down_load_backups/{$date}/";
    if (!is_dir($save_dir)) {
        mkdir($save_dir);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    $content = curl_exec($ch);
    curl_close($ch);
    $fp2 = @fopen($save_dir.$filename, 'ab');
    $res = fwrite($fp2, $content);
    fclose($fp2);
    if ($res && $content) {
        return true;
    }else{
        return false;
    }
}