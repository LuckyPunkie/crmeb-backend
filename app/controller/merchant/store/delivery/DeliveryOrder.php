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

namespace app\controller\merchant\store\delivery;

use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use think\App;
use crmeb\basic\BaseController;
use think\exception\ValidateException;

class DeliveryOrder extends BaseController
{
    protected $repository;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param DeliveryOrderRepository $repository 配送订单仓库实例
     */
    public function __construct(App $app, DeliveryOrderRepository $repository)
    {
        // 调用父类构造函数
        parent::__construct($app);
        // 初始化配送订单仓库实例
        $this->repository = $repository;
    }

    /**
     * 获取配送订单列表
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'station_id', 'status', 'date', 'order_sn', 'station_type']);
        // 添加商家ID条件
        $where['mer_id'] = $this->request->merId();
        // 获取配送订单列表
        $data = $this->repository->merList($where, $page, $limit);
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 商户配送订单管理列表。
     */
    public function manageList()
    {
        [$page, $limit] = $this->getPage();
        $data = $this->repository->manageList(
            $this->getManageWhere(),
            $page,
            $limit,
            false,
            $this->request->merId()
        );
        return app('json')->success($data);
    }

    /**
     * 商户配送订单管理头部统计。
     */
    public function manageTitle()
    {
        $data = $this->repository->manageTitle(
            $this->getManageWhere(),
            false,
            $this->request->merId()
        );
        return app('json')->success($data);
    }

    /**
     * 商户配送订单批量派单。
     */
    public function batchDispatch()
    {
        $orderIds = $this->request->param('order_ids', []);
        if (!is_array($orderIds)) {
            return app('json')->fail('订单参数格式错误');
        }
        $serviceId = (int)$this->request->param('service_id', 0);
        $data = $this->repository->batchDispatch(
            $orderIds,
            $serviceId,
            false,
            $this->request->merId()
        );
        return app('json')->success('批量派单完成', $data);
    }

    /**
     * 商户配送订单批量确认送达。
     */
    public function batchConfirm()
    {
        $orderIds = $this->request->param('order_ids', []);
        if (!is_array($orderIds)) {
            return app('json')->fail('订单参数格式错误');
        }
        $data = $this->repository->batchConfirm(
            $orderIds,
            false,
            $this->request->merId()
        );
        return app('json')->success('批量确认完成', $data);
    }

    /**
     * 获取配送订单详情
     *
     * @param int $id 配送订单ID
     * @return \think\response\Json
     */
    public function detail($id)
    {
        $data = $this->repository->detail($id, $this->request->merId());
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 取消配送订单
     *
     * @param int $id 配送订单ID
     * @return \think\response\Json
     */
    public function cancelForm($id)
    {
        // 转换表单数据为数组并取消配送订单
        return app('json')->success(formToData($this->repository->cancelForm($id, $this->request->merId())));
    }


    /**
     * 取消操作
     * @param int $id 订单ID
     * @return \think\response\Json 返回JSON格式的结果
     */
    public function cancel($id)
    {
        $reason = $this->request->params(['reason', 'cancel_reason']);
        // 判断取消理由是否为空
        if (empty($reason['reason']))
            return app('json')->fail('取消理由不能为空');
        // 调用仓库的取消方法
        $this->repository->cancel($id, $this->request->merId(), $reason);
        // 返回成功结果
        return app('json')->success('取消成功');
    }

    /**
     * 删除操作
     * @param int $id 订单ID
     * @return \think\response\Json 返回JSON格式的结果
     */
    public function delete($id)
    {
        // 调用仓库的删除方法
        $this->repository->destory($id, $this->request->merId());
        return app('json')->success('删除成功');
    }

    /**
     * 切换状态操作
     * @param int $id 订单ID
     * @return \think\response\Json 返回JSON格式的结果
     */
    public function switchWithStatus($id)
    {
        // 获取状态值
        $status = $this->request->param('status') == 1 ? 1 : 0;
        $this->repository->update($id, ['status' => $status]);
        return app('json')->success('修改成功');
    }

    protected function getManageWhere(): array
    {
        $where = $this->request->params([
            'keywords', 'date', 'store_name', 'activity_type', 'status',
            ['user_search_type', 'all'], ['user_search', ''],
            ['nickname', ''], ['uid', ''], ['phone', ''],
        ]);
        $payType = $this->request->param('pay_type', '');

        if ($payType !== '') {
            if (!ctype_digit((string)$payType)) {
                throw new ValidateException('支付方式参数错误');
            }
            $payType = (int)$payType;
            if (!array_key_exists($payType, StoreOrderRepository::PAY_TYPE_FILTEER)) {
                throw new ValidateException('支付方式参数错误');
            }
            $where['pay_type'] = StoreOrderRepository::PAY_TYPE_FILTEER[$payType];
        }
        $this->setUserSearchWhere($where);
        return $where;
    }

    protected function setUserSearchWhere(array &$where): void
    {
        $searchType = (string)$where['user_search_type'];
        $search = trim((string)$where['user_search']);
        if ($search === '') {
            foreach (['nickname', 'uid', 'phone'] as $legacySearchType) {
                if (($where[$legacySearchType] ?? '') !== '') {
                    $searchType = $legacySearchType;
                    $search = trim((string)$where[$legacySearchType]);
                    break;
                }
            }
        }
        unset($where['user_search_type'], $where['user_search']);
        unset($where['nickname'], $where['uid'], $where['phone']);
        if ($search === '') {
            return;
        }
        $searchFields = [
            'all' => 'username',
            'uid' => 'uid',
            'phone' => 'phone',
            'nickname' => 'nickname',
        ];
        if (!isset($searchFields[$searchType])) {
            throw new ValidateException('用户搜索类型错误');
        }
        $where[$searchFields[$searchType]] = $search;
    }


}
