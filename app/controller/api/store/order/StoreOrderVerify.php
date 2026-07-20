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


namespace app\controller\api\store\order;


use think\exception\ValidateException;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\basic\BaseController;
use think\App;

class StoreOrderVerify extends BaseController
{
    protected $merId;

    protected $user;

    protected $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * 根据订单ID查询订单详情
     *
     * 本函数用于通过提供的订单ID和商家ID来查询特定订单的详细信息。
     * 它首先尝试根据ID获取订单，如果订单不存在，则返回错误信息。
     * 如果订单存在但不属于请求的商家，则同样返回错误信息。
     * 如果订单存在且属于请求的商家，则成功返回订单详情。
     *
     * @param int $merId 商家ID，用于权限检查，确定请求的商家是否有权查看该订单。
     * @param int $id 订单ID，用于查询特定的订单。
     * @param StoreOrderRepository $repository 订单仓库对象，用于执行订单查询操作。
     * @return json 返回订单详情的JSON响应，如果订单不存在或无权查看，则返回错误的JSON响应。
     */
    public function detail(int $merId, $id, StoreOrderRepository $repository)
    {
        $this->merId = $merId;
        // 根据订单ID查询订单详情
        $order = $repository->codeByDetail($id);

        // 检查订单是否存在，如果不存在则返回错误信息
        if (!$order) return app('json')->fail('订单不存在');
        if ($order['is_virtual'] == 4) {
            $auth = $this->checkStaffAuth($order);
            if (!$auth) $this->checkServerAuth($order);
        } else {
            $auth = $this->checkServerAuth($order);
        }
        // 检查当前商家是否有权查看该订单，如果订单商家ID与请求的商家ID不匹配，则返回错误信息
        if (!$auth)
            return app('json')->fail('没有权限查询该订单');

        // 返回订单详情的成功响应
        return app('json')->success($order);
    }

    public function checkStaffAuth($order)
    {
        if ($this->request->isStaffs()){
            if (!in_array($order->mer_id,$this->request->staffsMerIds())) {
                return false;
            }
            return true;
        }
        return false;
    }

    public function checkServerAuth($order)
    {
        if ($this->request->isServer()){
            if ($order->mer_id !== $this->merId) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * 核验订单
     *
     * 本函数用于验证商家订单的合法性并进行核销操作。通过接收商家ID和订单ID，结合请求中的验证数据，
     * 调用存储层的相应方法来执行订单核销流程。此过程对于确保订单的准确性和维护业务正常运行至关重要。
     *
     * @param int $merId 商家ID，用于识别订单所属的商家
     * @param string $id 订单ID，用于唯一标识待核销的订单
     * @param StoreOrderRepository $repository 订单存储层的接口，用于执行实际的订单核销操作
     * @return \think\Response 返回一个表示核销成功的结果对象
     */
    public function verify(int $merId, $id, StoreOrderRepository $repository)
    {
        $this->merId = $merId;
        // 从请求中获取核销数据和验证码
        $data = $this->request->params(['data','verify_code']);
        // 根据订单ID、商家ID、验证码和订单类型查询订单，并连带查询订单产品信息
        $order = $repository->getWhere(['order_id' => $id, 'mer_id' => $merId, 'verify_code' => $data['verify_code']], '*', ['orderProduct']);
        // 如果订单不存在，则抛出验证异常
        if (!$order)  return app('json')->fail('[1]订单不存在');
        // 如果订单未支付，则抛出验证异常
        if (!$order->paid)  return app('json')->fail('订单未支付');
        // 如果订单已全部核销，则抛出验证异常

        if ($order['status'] && $order['is_virtual'] !== 4 )  return app('json')->fail('[1]订单已全部核销，请勿重复操作');

        if ($order['is_virtual'] == 4) {
            $auth = $this->checkStaffAuth($order);
            $staffs_id = $this->request->staffsList()[$order->mer_id]['staffs_id'];
            if (!$auth){
                $this->checkServerAuth($order);
                $service_id = $this->request->serviceInfo()->service_id;
            }
        } else {
            $auth = $this->checkServerAuth($order);
            $service_id = $this->request->serviceInfo()->service_id;
        }
        if (!$auth) app('json')->fail('没有权限操作');
        if ($staffs_id ?? false)
            $repository->reservationVerify($id, $order->mer_id, 0,$staffs_id);
        if ($service_id ?? false)
            $repository->verifyOrder($order, $data,$service_id);
        // 返回表示核销成功的结果
        return app('json')->success('订单核销成功');
    }
}
