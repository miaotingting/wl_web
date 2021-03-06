<?php

namespace App\Http\Utils;

use App\Http\Utils\Response;

class Errors {
    use Response;
    const DATABASE_ERROR = '101009';
    const DIC_DELETE_ERROR = '101451';
    const STORE_OUT_NOT_FOUND = '103051';
    const USER_ROLE_NOTFOUND = '105001';
    const MATTER_DEFINE_NOT_FOUND = '105002';
    const MATTER_NODE_NOT_FOUND = '105003';
    const MATTER_THREAD_NOT_FOUND = '105004';
    const MATTER_PROCESS_NOT_FOUND = '105005';
    const MATTER_PROCESS_STATUS_ERROR = '105006';
    const MATTER_NODE_INDEX_ERROR = '105007';
    const MATTER_ROLE_ERROR = '105008';
    const MATTER_STORE_OUT_STATUS_ERROR = '105009';
    const MATTER_STORE_OUT_EMPTY = '105010';
    const MATTER_CUSTOMER_ACCOUNT_ERROR = '105011';
    const MATTER_STORE_OUT_STATUS_CHECK = '105012';
    const MATTER_DELETE_ERROR = '105013';

    const ORDER_AMOUNT_ERROR = '107001';
    const ORDER_NOT_FOUND = '107002';
    const ORDER_STATUS_ERROR = '107003';

    const REFUND_ORDER_COUNT_ERROR ='107051';
    const REFUND_OUT_COUNT_ERROR ='107052';
    const REFUND_COUNT_ERROR ='107053';
    const REFUND_STATUS_ERROR ='107054';
    const TEMP_STATUS_ERROR = '105014';
    const REFUND_CARD_REQUIRED_ERROR ='107055';

    const NOT_LOGIN = '300001';
    const PERMISSION_ERROR = '300006';
    public $errors = [
        '101002' => '客户ID不存在',
        '101003' => '用户名已存在',
        '101004' => '用户添加失败',
        '101005' => '用户编辑失败',
        '101006' => '用户删除失败',
        '101007' => '此用户不存在',
        '101008' => '重置密码失败',
        '101009' => '操作失败',
        '101010' => '查询失败',
        '101011' => '修改密码失败',
        '101012' => '修改密码失败,原密码输入错误！',
        '101013' => '此用户你无权删除！',
        '101051' => '数据操作失败，请重新删除！',
        '101052' => '删除失败，请先清理部门人员！',
        '101053' => '修改部门失败！',
        '101054' => '添加部门失败！',
        '101101' => '添加角色失败！',
        '101102' => '此角色不存在！',
        '101103' => '编辑角色失败！',
        '101104' => '该角色下面有用户，无法删除！',
        '101105' => '删除角色失败！',
        '101106' => '角色ID有误！',
        '101107' => '获取权限失败！',
        '101108' => '角色ID缺失！',
        '101109' => '设置权限失败！',
        '101110' => '此角色有关联流程，无法删除！',
        '101151' => '存在子菜单,不允许删除！',
        '101152' => '角色正在使用,不允许删除！',
        '101153' => '数据操作失败，请重新删除！',
        '101154' => '添加菜单失败！',
        '101155' => '修改菜单失败！',
        '101156' => '删除菜单失败！',
        '101201' => '该落地不存在！',
        '101251' => '添加网关信息失败！',
        '101252' => '编辑网关信息失败！',
        '101253' => '删除网关信息失败！',
        '101254' => '此网关信息不存在！',
        '101301' => '编辑资费信息失败！',
        '101351' => '流程定义失败',
        '101352' => '节点定义失败',
        '101353' => '节点已定义',
        
        '101401' => '新增系统公告操作失败！',
        '101402' => '编辑系统公告操作失败！',
        '101403' => '删除系统公告操作失败！',
        '101404' => '此条系统公告不存在！',
        '101405' => '此条系统公告没有授权权限！',
        '101406' => '系统公告授权失败！',
        '101407' => '要授权的角色不存在！',
        '101408' => '要授权的用户不存在！',
        '101409' => '删除授权操作失败！',
        '101410' => '此条系统公告没有删除授权权限！',
        '101411' => '此条授权信息不存在！',
        '101412' => '此条系统公告无法查看权限列表！',
        '101413' => '阅读失败！',
        '101414' => '获取未读的公告及通知个数失败！',
        '101415' => '没有此公告级别！',

        '101451' => '存在字典详情，不允许删除！',
        
        '102001' => '获取信息失败！',
        '102002' => '添加客户失败！',
        '102003' => '此客户不存在！',
        '102004' => '编辑客户失败！',
        '102005' => '删除客户失败！',
        '102006' => '请填写要变更的经理！',
        '102007' => '变更经理失败！',
        '102008' => '添加客户联系人失败！',
        '102009' => '编辑客户联系人失败！',
        '102010' => '删除客户联系人失败！',
        '102011' => '请输入要设置联系人的ID！',
        '102012' => '请输入客户ID！',
        '102013' => '设置主要联系人失败！',
        '102014' => '该客户下面有二级客户不能删除！',
        '102015' => '上级客户不存在！',
        '102016' => '该客户下有卡片，不能删除！',
        '102017' => '原客户经理不存在！',
        '102018' => '新客户经理不存在！',
        '102019' => '原客户经理下没有客户！',
        '102020' => '设置经理操作失败！',
        '102021' => '原客户经理和新客户经理不能相同！',
        '102022' => '操作失败，当前用户不是客户！',
        '102023' => '您无创建客户权限！',
        '102024' => '客户经理为空！',
        '102025' => '您无创建充值申请单权限！',
        '102026' => '您无编辑充值申请单权限！',
        '102027' => '客户是必填项！',
        '102028' => '您无删除充值申请单权限！',
        
        '103001' => '新建套餐失败！',
        '103002' => '编辑套餐失败！',
        '103003' => '删除套餐失败！',
        '103004' => '该ID信息未找到！',

        '103051' => '出库单不存在！',
        '103052' => '操作失败,失败原因:所选订单号不存在！',
        '103053' => '操作失败,失败原因:订单关联出库数量差异',
        '103054' => '操作失败,失败原因:关联订单在平台已存在！',
        '103055' => '操作失败,失败原因:关联订单已经出库',
        '103056' => '操作失败,失败原因:批量新增出错',
        '103057' => '操作失败,出库单已审核',
        '103058' => '获取订单卡片详情出错！',

        '103251' => '获取维护列表出错！',
        '103252' => '数据初始化出错！',
        '103253' => '数据重复维护！',
        '103254' => '获取订单卡列表出错！',
        '103255' => '获取订单卡片详情出错！',
        
        
        '103101' => '新建仓库失败！',
        '103102' => '此仓库不存在！',
        '103103' => '修改仓库失败！',
        
        '103151' => '新建入库订单失败！',
        '103152' => '修改入库订单失败！',
        '103153' => '此入库订单不存在！',
        '103154' => '入库订单ID不能为空！',
        '103155' => '请传入卡片excel文件！',
        '103156' => '当前支持Excel导入卡片数量为10万张',
        '103157' => '数据初始化失败！',
        '103158' => '您已完成导卡，不可再次导入！',
        '103159' => '此入库单已审核通过，不能修改！',
        '103160' => '导入失败，导入的卡片数量超过了此订单批次数量！',
        '103161' => '导入失败，导入的部分卡片已存在于仓库中！',
        '103162' => '操作失败，此入库单已经审核通过，不允许数据初始化操作！',
        '103163' => '操作失败，此入库单已审核或未完成！',
        '103164' => '操作失败，请同意或驳回！',
        '103165' => '审核失败！',
        '103166' => '修改失败，批次数量不能小于实际入库数量！',
        '103167' => '文件暂不支持大于15M！',


        '103251' => '维护卡片包含多个落地！',
        '103252' => '维护落地与库存落地不匹配，请联系库管核实！',
        '103253' => '包含待审核入库卡片，联系库管！',
        '103254' => '订单已完成(已出库)不允许修改！',
        '103255' => '请选择正确仓库地址，卡片包含多个仓库！',
        '103256' => '请选择正确仓库地址！',
        '103257' => '数据重复维护！',
                
        
        '104001' => '添加充值申请单失败！',
        '104002' => '编辑充值申请单失败！',
        '104003' => '此申请单已通过审核，不能修改！',
        '104004' => '此申请单已通过审核，不能删除！',
        '104005' => '删除充值申请单失败！',
        '104007' => '要确认的充值申请单不存在！',
        '104008' => '请选择要进行的操作！',
        '104009' => '此申请单不存在！',
        '104010' => '此申请单状态错误！',
        '104011' => '此申请单已删除，无法操作！',
        '104012' => '此申请单已确认，请勿重复操作！',
        '104013' => '此申请单已驳回，请勿重复操作！',
        
        '104101' => '提现申请单申请失败！',
        '104102' => '您不是客户，无此操作权限！',
        '104103' => '您无此操作权限！',
        '104104' => '此提现申请单不存在！',
        '104105' => '操作失败，请确认要进行的操作！',
        '104106' => '操作失败！',
        '104107' => '账户余额不足，无法申请提现！',
        '104108' => '修改提现申请单失败！',
        '104109' => '您无此操作权限！',
        
        '105001' => '用户角色错误',
        '105002' => '流程不存在',
        '105003' => '节点不存在',
        '105004' => '线程不存在',
        '105005' => '进程不存在',
        '105006' => '状态错误',
        '105007' => '节点序号错误',
        '105008' => '此用户的角色没有权限操作',
        '105009' => '请先出库！',
        '105010' => '请先维护订单！',
        '105011' => '客户账户余额不足，请充值！',
        '105012' => '卡片已出库，不允许驳回',
        '105013' => '不允许作废',
        '105014' => '续费计划状态错误！',

        '106001' => '文件上传出错！',
        '106002' => '非法Excel文件！',
        '106003' => '文件暂不支持大于6M！',
        '106004' => '文件上传失败',
        '106005' => '订单信息有误！',
        '106006' => 'Excel卡数量与订单卡数有差异！',
        '106007' => '存在重复iccid',
        '106008' => '库存卡片不足！',
        '106009' => '包含已出库卡片',
        '106010' => '当前支持Excel导入卡片数量为6万张',
        '106011' => '卡片查询失败!',
        '106012' => '开卡失败,失败原因:包含不归属您或已转出的卡片!',
        '106013' => '批量开卡失败!',
        '106014' => '批量回收失败!',
        '106015' => '请传入要开卡的下级客户ID',
        '106016' => '此卡片不存在！',
        '106017' => '出现异常，请联系服务提供者处理。',
        '106018' => '更新卡片活动状态操作失败！',
        '106019' => '操作失败，部分卡片有问题或者包含不需要回收的卡片！',
        '106020' => '导出失败！',
        '106021' => '卡号或ICCID错误！',
        '106022' => '新建停复机申请失败！',
        '106023' => '操作失败，卡片不属于所选落地和客户！',
        '106024' => '此阶段不支持卡片复机操作！',
        '106025' => '数据大于5万条暂不支持导出！',
        '106026' => '操作失败，卡片状态有问题！',

        '107001' => '订单金额不正确',
        '107002' => '开卡订单不存在',
        '107003' => '请选择已结束的订单',

        '107051' => '退货的卡片有不在开卡订单中的卡片',
        '107052' => '退货的卡片有未出库的卡片',
        '107053' => '退货的卡片数量有误',
        '107054' => '退货单状态错误',
        '107055' => '包含已经退卡的卡片！',
        
        '107101' => '此续费订单不存在！',
        '107102' => '续费金额错误！',
        '107103' => '操作失败，包含重复的卡片！',
        '107104' => '操作失败，有不属于您的卡片！',
        '107105' => '操作失败，有不同订单的卡片！',
        '107106' => '操作失败，卡片中存在白卡！',
        '107107' => '操作失败，运营商选择错误！',
        '107108' => '操作失败，您还有未支付的订单，请前往支付！',
        '107109' => '操作失败，卡片套餐不一致！',
        '107110' => '操作失败，运营商类型错误！',
        '107111' => '操作失败，卡片套餐与所选资费计划套餐不一致！',
        '107112' => '操作失败，所选资费计划套餐小于当前卡片套餐！',
        '107113' => '创建订单失败！',
        
        '107151' => '创建资费计划失败！',
        '107152' => '编辑资费计划失败！',
        '107153' => '操作失败，重复的资费计划！',
        '107154' => '此计划不存在！',
        '107155' => '操作失败，您没有此操作权限！',
        '107156' => '更新成功！',
        
        
        '108001' => '短信发送失败!',
        '108002' => '操作失败，包含不正确或重复的号码!',
        '108003' => '操作失败，包含没有配置网关的卡片!',
        '108004' => '操作失败，包含不属于自己的卡片!',
        '108005' => '操作失败，最多操作5000张卡片!',
        '108151' => '创建指令模板失败!',
        '108152' => '编辑指令模板失败!',
        '108153' => '删除指令模板失败!',
        '108154' => '没有此ID的指令模板！',
        
        '109001' => '新建工单失败！',
        '109002' => '此工单不存在！',
        '109003' => '工单认领失败！',
        '109004' => '工单分配失败！',
        '109005' => '当前没有待处理的工单！',
        '109006' => '操作失败，您不是售后人员！',
        '109007' => '交接失败！',
        '109008' => '操作失败，工单已关闭！',
        '109009' => '工单错误！',
        '109010' => '操作失败，您没有操作此工单权限！',
        '109011' => '撤销交接失败！',
        '109012' => '操作失败，交接的操作状态有误！',
        '109013' => '操作失败！',
        '109014' => '您不是客户，无此操作权限！',
        '109015' => '该工单已被受理或关闭，请勿重复操作！',
        '109016' => '您不是售后总监，无此操作权限！',
        '109017' => '添加失败！',
        '109018' => '关闭工单失败！',
        '109019' => '删除工单失败！',
        '109020' => '本人的工单不可以在交接给本人！',
        '109021' => '此工单正在交接中，不允许在交接！',
        
        '110001' => '编辑失败！',
        '101009' => '删除失败！',
        '110003' => '此请求参数不存在！',
        '110004' => '此接口不存在！',
        '110051' => '当前登录用户不是客户！',

        '111001' => '获取续费月份列表失败！',
        '111002' => '设置客户续费月份失败！',
        '111003' => '续费客户不存在！',
        '111004' => '客户不是一级客户！',
        '111005' => '客户已设置续费月份，不允许重复设置！',
        '111006' => '客户续费月份查询失败！',
        '111007' => '设置月份不符合规则！',
        '111008' => '修改月份失败！',

        '111051' => '只能给下级客户设置分润！',
        '111052' => '客户级别错误！',
        '111053' => '当前用户不存在分润！',
        '111054' => '当前套餐已经存在分润！',
        '111055' => '审核中不能新增或者修改！',
        '111056' => '分润不存在！',
        '111057' => '分润状态不是未审核，不能提交审核！',
        '111058' => '您没有这个套餐的分润，无法给下级客户设置！',
        '111059' => '渠道价不能小于您的销售价！',
        '111060' => '销售价必须大于渠道价！',
        
        '300001' => '登录用户不存在',
        '300002' => '参数错误',
        '300003' => '请求参数缺失！',
        '300004' => '父ID为必填项',
        '300005' => '操作数据库失败！',
        '300006' => '没有权限',
        

    ];
}