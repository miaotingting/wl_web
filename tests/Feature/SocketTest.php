<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SocketTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        dump('测试socket移动短信接口');
        // socket接入中国移动短信代码

        // 设置报告所有错误
        // error_reporting(E_ALL);
        set_time_limit(0);

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            dd("socket_create failed: code:". socket_last_error($socket) . "msg: " . socket_strerror($socket));
        }
        dump($socket);

        $ip = "183.230.96.94";
        $port = 17890;

        $connect = socket_connect($socket, $ip, $port);
        if (!$connect) {
            dd("socket_connect failed: code: ". socket_last_error($connect) . "msg: ". socket_strerror($connect));
        }

        dump($connect);

        $cid = 0x00000001;
        $sid = 00000001;
        $addr = 150278;
        $time = date('mdHis');
        $secret = '147258wl';
        $md5 = md5($addr.pack("a9", "") . $secret . $time, true);
        $version = 0x30;
        $bodyData = pack("a6a16CN", $addr, $md5, $version, $time);
        $len = strlen($bodyData) + 12;
        $headData = pack("NNN", $len, $cid, $sid);
        $resW = socket_write($socket, $headData.$bodyData, $len);
        $headDataRes = socket_read($socket, $len);
        $arryResult =  unpack("NTotal_Length/NCommand_Id/NSequence_Id/CStatus/a16AuthenticatorISMG/CVersion", $headDataRes);
        // $bodyData = socket_read($socket,21);
        dd($arryResult);
        // $body = unpack("CStatus/a16AuthenticatorISMG/CVersion", $bodyData);
        // foreach($arryResult as $key=>$value){
        //     //消息需要的话自己组下
        // }
        $Msg_Id = rand(1,100);
        $Service_Id = 'BJWL';
        $sbodyData = pack("N", $Msg_Id);
        // $sbodyData = 1;
        $sbodyData .= pack("C", 1).pack("C", 1);
        $sbodyData .= pack("C", 0).pack("C", 0);
        $sbodyData .= pack("a10", $Service_Id);
        $SP_ID = 150278;
        $Dest_Id = '1064899150278';
        $tomsisdn = '13263463442';
        $sbodyData .= pack("C", 0).pack("a32", "").pack("C", 0).pack("C", 0).pack("C", 0).pack("C", 0).pack("a6", $SP_ID).pack("a2", "02").pack("a6", "").pack("a17", "").pack("a17", "").pack("a21", $Dest_Id).pack("C", 1);
        $sbodyData .= pack("a32", $tomsisdn);
        $sbodyData .= pack("C", 0);
        $contents = '123';
        $len = strlen($contents);
        $sbodyData .= pack("C", $len);
        $sbodyData .= pack("a".$len, $contents);
        $sbodyData .= pack("a20", "00000000000000000000");
        $len = strlen($sbodyData) + 12;
        $cid = 0x00000004;
        $sid = $Msg_Id;
        $headData = pack("NNN", $len, $cid, $sid);
        
        $resw = socket_write($socket, $headData.$sbodyData, $len); 
        $buf = '';
        socket_recv($socket, $buf, $len, MSG_WAITALL);
        dd($buf);
        // while ($res = socket_read($socket, 1)) {
        //     dd($res);
        // }
        
        // unpack("NTotal_Length/NCommand_Id/NSequence_Id/CStatus/a16AuthenticatorISMG/CVersion", $res);
        



        // // MsgConnect connect=new MsgConnect();
        // // connect.setTotalLength(12+6+16+1+4);//消息总长度，级总字节数:4+4+4(消息头)+6+16+1+4(消息主体)
        // // connect.setCommandId(MsgCommand.CMPP_CONNECT);//标识创建连接
        // // connect.setSequenceId(MsgUtils.getSequence());//序列，由我们指定
        // // connect.setSourceAddr(config.getSpId());//我们的企业代码
        // // String timestamp = MsgUtils.getTimestamp();
        // // connect.setAuthenticatorSource(MsgUtils.getAuthenticatorSource(config.getSpId(), config.getSharedSecret(), timestamp));//md5(企业代码+密匙+时间戳)
        // // connect.setVersion((byte)0x30);//版本号 高4bit为3，低4位为0
        // // connect.setTimestamp(Integer.parseInt(timestamp));//时间戳(MMDDHHMMSS)

        // //发送的手机号及内容
        // $mobile = '';
        // $content = '';

        // //平台帐号密码
        // $accountId = '1064899150278';
        // $password = "150278";
        // $sourceAddr = base_convert("192002", 16, 8);

        // // 创建一个 TCP/IP Socket
        // // $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        // // if ($socket < 0) {
        // //     echo "socket_create() failed: reason: " . socket_strerror($socket) . "\n";
        // // }

        // // // 连接Socket服务器
        // // $result = socket_connect($socket, $ip, $port);
        // // if ($result < 0) {
        // //     echo "socket_connect() failed.\nReason: ($result) " . socket_strerror($result) . "\n";
        // // }

        // $Timestamp = date("mdHis");

        // //消息头 
        // $len = pack("N", 27);
        // $Command_Id = pack("N",1);	//请求连接0x00000001
        // $Sequence_Id = pack("N",1);
        // dump("len: $len");
        // dump("Command id: $Command_Id");
        // dump("Sequence id: $Sequence_Id");

        // //消息体
        // $account = $accountId.pack("C*",0,0,0,0,0,0,0);    //加二进制的0补全到21位
        // $accountMD5 = $accountId.pack("C*",0,0,0,0,0,0,0,0,0); //加9个二进制的0
        // $AuthenticatorSource = md5($sourceAddr.$accountMD5.$Timestamp, true);
        // $Version = '30';
        // dd($sourceAddr);
        // $Timestamp = pack("N",$Timestamp);
        // $Message = $Command_Id.$Sequence_Id.$account.$AuthenticatorSource.$Version.$Timestamp;
        // $Total_Length = pack("N",strlen($Message)+4);
        // $out = '';

        // $in = $Total_Length.$Message;
        // if(!socket_write($socket, $in, strlen($Message)+4)) {
        //     echo "socket_write() failed: reason: " . socket_strerror($socket) . "\n";
        // }

        // //--------------------------接受移动返回消息-----------------------------------------
        // $out = socket_read($socket,37);
        // $arryResult = unpack("C*",$out);
        // foreach($arryResult as $key=>$value){
        //     //消息需要的话自己组下
        // }

        // /*没激活的此处可能需要写激活代码，也可以去平台激活帐号*/
        // //===========================短信发送=================================================

        // //消息头
        // $Command_Id = pack("N",4);	//发送短信0x00000004
        // $Sequence_Id = pack("N",0);

        // //消息体,保留字段全为0
        // $Msg_Id = pack("C*",0,0,0,0,0,0,0,0,0,0);
        // $Pk_total = pack("h",1);
        // $Pk_number = pack("h",1);
        // $Registered_Delivery = pack("h",1); //返回状态
        // $Msg_Fmt = pack("C",15); //含GB汉字格式
        // $ValId_Time = pack("C*",0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
        // $At_Time = pack("C*",0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0); //即时发送
        // $DestUsr_tl = pack("N",1);
        // $moblieAscii = '';

        // for($i=0;$i<strlen($mobile);$i++){
        //     $moblieAscii .= pack("C",ord(substr($mobile,$i,1)));
        // }

        // $Dest_terminal_Id = $moblieAscii.pack("C*",0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0); //手机号补全到32字节
        // $Msg_Length = pack("C",strlen($content));
        // $contentAscii = '';

        // for($i=0;$i<strlen($content);$i++){
        //     $contentAscii .= pack("C",ord(substr($content,$i,1)));
        // }

        // $Msg_Content = $contentAscii;
        // $Msg_src = pack("C*",0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0); //保留字段，默认抓包内容
        // $Src_Id = $account; //帐号取上面的21位
        // $Service_Id = pack("C*",48,0,0,0,0,0,0,0,0,0);

        // //==========以下保留字段===============
        // $LinkID = pack("C*",0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
        // $Msg_level = pack("C",1);
        // $Fee_UserType = pack("C",2);
        // $Fee_terminal_Id = pack("C*",0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0);
        // $Fee_terminal_type = pack("C",0);
        // $TP_pId = pack("C",0);
        // $TP_udhi = pack("C",0);
        // $FeeType = pack("CC",48,49);
        // $FeeCode = pack("C*",48,0,0,0,0,0);
        // $Dest_terminal_type = pack("C",0);

        // $Message = $Command_Id.$Sequence_Id.$Msg_Id.$Pk_total.$Pk_number.$Registered_Delivery.$Msg_Fmt.$ValId_Time.$At_Time.$DestUsr_tl.$Dest_terminal_Id.$Msg_Length.$Msg_Content.$Msg_src.$Src_Id.$Service_Id.$LinkID.$Msg_level.$Fee_UserType.$Fee_terminal_Id.$Fee_terminal_type.$TP_pId.$TP_udhi.$FeeType.$FeeCode.$Dest_terminal_type;

        // $Total_Length = pack("N",strlen($Message)+4);
        // $in = $Total_Length.$Message;
        // if(!socket_write($socket, $in, strlen($Message)+4)) {
        //     echo "socket_write() failed: reason: " . socket_strerror($socket) . "\n";
        // }

        // //下面可以接受发送返回的状态

        // // 关闭Socket
        // socket_close($socket);










        /*
        // 设置报告所有错误
        error_reporting(E_ALL);

        // 获取www的端口号：80
        $service_port = getservbyname('www', 'tcp');
        // 获取IP地址
        $address = gethostbyname('www.example.com');

        // 创建一个 TCP/IP Socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            echo "Socket创建失败，失败原因: " . socket_strerror(socket_last_error()) . "<br />";
        } else {
            echo "Socket创建成功！<br />";
        }

        echo "正在尝试连接： '$address' ,端口号为： '$service_port'...<br />";

        // 连接Socket： $connection = socket_connect($socket, 'localhost',service_port);
        $connection = socket_connect($socket, $address, $service_port);
        if ($connection === false) {
            echo "Socket连接失败，失败原因: " . socket_strerror(socket_last_error($socket)) . "<br />";
        } else {
            echo "Socket连接成功<br />";
        }

        $in = "HEAD / HTTP/1.1\r\n";
        $in .= "Host: www.example.com\r\n";
        $in .= "Connection: Close\r\n\r\n";

        // 写数据到socket缓存
        if(!socket_write($socket, $in, strlen($in))){
            printf("Write failed");
        }

        sleep(3);  //机器运算要比网络传输快几百倍，服务器还没有返回数据呢就已经开始运行了，当然就收的是空值了

        // 读取指定长度的数据
        while($buffer = socket_read($socket, 1024,PHP_BINARY_READ)){
            if($buffer == "NO DATA"){
                printf("NO DATA");
                break;
            }else{
                //输出buffer
                printf("Buffer Data: " . $buffer . "");
            }
        }

        // 关闭Socket
        socket_close($socket);
        echo "<br />Socket关闭成功！";*/
        
    }
}


