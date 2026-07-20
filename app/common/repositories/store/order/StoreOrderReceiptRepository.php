<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\common\repositories\store\order;

use app\common\repositories\BaseRepository;
use app\common\dao\store\order\StoreOrderReceiptDao;
use app\common\model\store\order\StoreOrder;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\exception\ValidateException;
use think\facade\Route;

/**
 * @mixin StoreOrderReceiptDao
 */
class   StoreOrderReceiptRepository extends BaseRepository
{
    public function __construct(StoreOrderReceiptDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     *  生成信息
     * @param array $receiptData
     * @param StoreOrder $orderData
     * @param null $orderPrice
     * @author Qinii
     * @day 2020-10-16
     */
    public function add(array $receiptData,StoreOrder $orderData, $orderPrice = null)
    {
        if($this->dao->getWhereCount(['order_id' => $orderData->order_id]))
            throw new ValidateException('该订单已存在发票信息');

        if (!$receiptData['receipt_type'] ||
            !$receiptData['receipt_title_type'] ||
            !$receiptData['receipt_title']
        ) throw new ValidateException('发票信息不全');

        if($receiptData['receipt_type'] == 1){
            $receipt_info = [
                'receipt_type' => $receiptData['receipt_type'],
                'receipt_title_type' => $receiptData['receipt_title_type'],
                'receipt_title' => $receiptData['receipt_title'],
                'duty_paragraph' => $receiptData['duty_paragraph']
            ];
            $delivery_info = [
                'email' => $receiptData['email']
            ];
        }
        if($receiptData['receipt_type'] == 2){
            if (
                !$receiptData['duty_paragraph'] ||
                !$receiptData['bank_name'] ||
                !$receiptData['bank_code'] ||
                !$receiptData['address']  ||
                !$receiptData['tel']
            ) throw new ValidateException('发票信息不全');
            $receipt_info = [
                'receipt_type' => $receiptData['receipt_type'],
                'receipt_title_type' => $receiptData['receipt_title_type'],
                'receipt_title' => $receiptData['receipt_title'],
                'duty_paragraph' => $receiptData['duty_paragraph'],
                'bank_name' => $receiptData['bank_name'],
                'bank_code' => $receiptData['bank_code'],
                'address' => $receiptData['address'],
                'tel' => $receiptData['tel'],
            ];
            $delivery_info = [
                'user_name' => $orderData['real_name'],
                'user_phone' => $orderData['user_phone'],
                'user_address' => $orderData['user_address'],
            ];
        }
        $data = [
            'order_id' => $orderData->order_id,
            'uid' => $orderData->uid,
            'mark' => $receiptData['mark'] ?? '',
            'order_price' => $orderPrice ?? $orderData['pay_price'],
            'receipt_info' => json_encode($receipt_info),
            'delivery_info'=> json_encode($delivery_info),
            'status_time' => date('Y-m-d H:i:s',time()),
            'mer_id' => $orderData->mer_id
        ];
        $this->dao->create($data);
    }

    /**
     *  列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2020-10-17
     */
    public function getList(array $where,int $page,int $limit)
    {
        $query = $this->dao->search($where)->with([
            'storeOrder' => function ($query) {
                $query->field('order_id,order_sn,real_name,user_phone,user_address,status,paid,is_del,pay_price,paid,group_order_id,mark');
            },
            'user' => function ($query) {
                $query->field('uid,nickname,phone');
            },
            'merchant'  => function ($query) {
                $query->field('mer_id,mer_name');
            },]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('count', 'list');
    }

    /**
     *  开票
     * @param string $ids
     * @author Qinii
     * @day 2020-10-17
     */
    public function setRecipt(string $ids,int $merId)
    {
        $data = $this->dao->getSearch(['order_receipt_ids' => $ids,'mer_id' => $merId])->order('create_time Desc')->select();
        $arr = $this->check($ids);
        $receipt_price = 0;
        foreach ($data as $item){
            if($item['status'] == 1) throw new ValidateException('存在已开票订单ID：'.$item['order_receipt_id']);
            $receipt_price = bcadd($receipt_price,$item['order_price'],2);
            $delivery_info = $item['delivery_info'];
        }
        $receipt_info = json_decode($arr[0]);
        if($receipt_info->receipt_type == 1 ){
            $title = $receipt_info->receipt_title_type == 1 ? '个人电子普通发票' : '企业电子普通发票';
        }else{
            $title = '企业专用纸质发票';
        }
        return $res = [
            "title" => $title,
            "receipt_sn" => $this->receiptSn(),
            "receipt_price" =>  $receipt_price,
            'receipt_info' => $receipt_info,
            'delivery_info' => $delivery_info,
            'status' => 0,
        ];
    }

    /**
     * 检查传入的发票ID是否全部属于指定的商家
     *
     * 此函数用于验证一个或多个发票ID是否属于指定的商家ID。它通过分解发票ID字符串，然后逐一检查每个发票ID是否与指定的商家ID相关联。
     * 如果任何一个发票ID不属于指定的商家ID，则抛出一个验证异常，表示数据有误。
     * 这个函数确保了只有属于商家的发票才能被商家操作，增强了系统的数据安全性和业务逻辑的正确性。
     *
     * @param string $ids 发票ID的字符串，多个ID用逗号分隔。
     * @param int $merId 商家ID，用于检查发票是否属于这个商家。
     * @return bool 如果所有发票ID都属于指定商家，则返回true；否则抛出ValidateException异常。
     * @throws ValidateException 如果任何一个发票ID不属于指定的商家，则抛出此异常。
     */
    public function merExists(string $ids,int $merId)
    {
        // 将发票ID字符串分解为数组
        $ids = explode(',',$ids);

        // 遍历发票ID数组，检查每个ID是否与指定的商家ID相关联
        foreach ($ids as $id) {
            // 如果某个发票ID与指定的商家ID不相关联，则抛出验证异常
            if(!$this->dao->getSearch(['order_receipt_id' => $id,'mer_id' => $merId])->count())
                throw new ValidateException('数据有误,存在不属于您的发票ID');
        }
        // 如果所有发票ID都通过验证，返回true
        return true;
    }

    /**
     *  保存合并的发票信息
     * @param array $data
     * @author Qinii
     * @day 2020-12-02
     */
    public function save(array $data)
    {
        $this->check($data['ids']);
        $res = [
            "receipt_sn" => $data['receipt_sn'],
            "receipt_price" =>  $data['receipt_price'],
            'status'    => $data['receipt_no'] ? 1 : 2,
            'status_time' => date('Y-m-d H:i:s',time()),
            'receipt_no' => $data['receipt_no'],
            'mer_mark' => $data['mer_mark'],
            'pic'=> trim(systemConfig('stie_url'),'/').$data['pic'],
        ];
       $this->dao->updates(explode(',',$data['ids']),$res);
    }

    /**
     * 检查订单是否满足开票条件
     *
     * 本函数用于验证一组订单是否可以合并开具发票，以及这些订单是否已支付。
     * 它首先通过订单ID查询相关订单信息，然后检查这些订单是否都已支付。
     * 如果所有订单都已支付，它还会检查这些订单的开票信息是否一致。
     * 如果开票信息不一致，或者有任何订单未支付，将抛出异常。
     * 最后，如果所有条件都满足，函数将返回唯一的开票信息数组。
     *
     * @param string $ids 订单ID的字符串，多个ID用逗号分隔
     * @return array 通过验证的唯一开票信息数组
     * @throws ValidateException 如果订单未支付或开票信息不一致，抛出此异常
     */
    public function check(string $ids)
    {
        // 根据订单ID查询订单及其支付状态
        $query = $this->dao->getSearch(['order_receipt_ids' => $ids])->with(['storeOrder' => function($query){
            $query->field('order_id,paid');
        }]);
        $result = $query->select();

        // 遍历查询结果，检查所有订单是否已支付
        foreach ($result as $item){
            if(!$item->storeOrder['paid']) throw new ValidateException('订单未支付不可开发票');
        }

        // 提取所有订单的开票信息
        $data = $query->column('receipt_info');

        // 去除重复的开票信息
        $arr = array_unique($data);

        // 如果存在重复的开票信息，抛出异常
        if(count($arr) > 1) throw new ValidateException('开票信息不相同，无法合并');

        // 返回唯一的开票信息数组
        return $arr;
    }

    /**
     *  生成发票号
     * @return string
     * @author Qinii
     * @day 2020-10-17
     */
    public function receiptSn()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
        $orderId = 'PT' . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));
        return $orderId;
    }

    /**
     * 标记订单备注
     *
     * 本函数用于生成一个表单，以允许用户修改订单的商家备注。此表单通过Elm组件构建，
     * 并配置了必要的表单字段及验证规则。表单提交的目标URL是通过路由生成的，确保了URL的正确性和安全性。
     *
     * @param int $id 订单ID，用于获取当前订单的备注信息，并在表单中进行展示。
     * @return string 返回生成的表单HTML代码，供前端展示并进行交互。
     */
    public function markForm($id)
    {
        // 通过订单ID获取订单备注信息
        $data = $this->dao->get($id);

        // 构建表单提交的URL
        $url = Route::buildUrl('merchantOrderReceiptMark', ['id' => $id])->build();

        // 创建表单对象，并设置表单提交的URL
        $form = Elm::createForm($url);

        // 配置表单的验证规则，包括一个文本字段用于输入备注信息
        $form->setRule([
            // 'mer_mark'字段用于输入商家备注，设置字段的占位符和必填属性
            Elm::text('mer_mark', '备注：', $data['mer_mark'])->placeholder('请输入备注')->required('请输入备注'),
        ]);

        // 设置表单的标题
        return $form->setTitle('修改备注');
    }
}
