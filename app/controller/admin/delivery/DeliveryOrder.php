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


namespace app\controller\admin\delivery;

use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 同城配送订单
 */
class DeliveryOrder extends BaseController
{
    protected $repository;

    public function __construct(App $app, DeliveryOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     *  列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'station_name', 'status', 'mer_id', 'date', 'order_sn', 'station_type']);
        $data = $this->repository->sysList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 平台配送订单管理列表。
     */
    public function manageList()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success(
            $this->repository->manageList($this->getManageWhere(), $page, $limit, true)
        );
    }

    /**
     * 平台配送订单管理头部统计。
     */
    public function manageTitle()
    {
        return app('json')->success($this->repository->manageTitle($this->getManageWhere(), true));
    }

    /**
     * 平台配送订单批量派单。
     */
    public function batchDispatch()
    {
        $orderIds = $this->request->param('order_ids', []);
        if (!is_array($orderIds)) {
            return app('json')->fail('订单参数格式错误');
        }
        $serviceId = (int)$this->request->param('service_id', 0);
        $data = $this->repository->batchDispatch($orderIds, $serviceId, true);
        return app('json')->success('批量派单完成', $data);
    }

    /**
     * 平台配送订单批量确认送达。
     */
    public function batchConfirm()
    {
        $orderIds = $this->request->param('order_ids', []);
        if (!is_array($orderIds)) {
            return app('json')->fail('订单参数格式错误');
        }
        $data = $this->repository->batchConfirm($orderIds, true);
        return app('json')->success('批量确认完成', $data);
    }

    /**
     *  详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detail($id)
    {
        $data = $this->repository->detail($id, null);
        return app('json')->success($data);
    }

    /**
     * 平台配送订单派单
     * @param int $id 商城订单ID
     * @return \think\response\Json
     */
    public function dispatch(int $id)
    {
        $serviceId = (int)$this->request->param('service_id', 0);
        if (!$serviceId) {
            return app('json')->fail('请选择配送人员');
        }
        $this->repository->sysDispatch($id, ['service_id' => $serviceId]);
        return app('json')->success('派单成功');
    }

    /**
     * 平台配送订单改派
     * @param int $id 商城订单ID
     * @return \think\response\Json
     */
    public function updateDispatch(int $id)
    {
        $serviceId = (int)$this->request->param('service_id', 0);
        if (!$serviceId) {
            return app('json')->fail('请选择配送人员');
        }
        $this->repository->sysUpdateDispatch($id, ['service_id' => $serviceId]);
        return app('json')->success('改派成功');
    }

    /**
     * 平台配送订单确认送达
     * @param int $id 商城订单ID
     * @return \think\response\Json
     */
    public function confirm(int $id)
    {
        $this->repository->sysConfirm($id);
        return app('json')->success('确认成功');
    }

    /**
     *  标题
     * @return \think\response\Json
     * @author Qinii
     */
    public function title()
    {
        $data = $this->repository->getTitle();
        return app('json')->success($data);
    }

    /**
     * 获取平台配送趋势统计：支持 date 统一日期筛选及旧版起止日期参数
     * @return \think\response\Json 平台配送趋势数据
     */
    public function trend()
    {
        $data = $this->request->params([
            ['date', ''],
            ['start_date', ''],
            ['end_date', ''],
            ['user_search_type', 'all'],
            ['user_search', ''],
            ['name', ''],
            ['uid', ''],
            ['phone', ''],
            ['keyword', ''],
        ]);
        return app('json')->success($this->repository->getPlatformTrend(
            (string)$data['start_date'],
            (string)$data['end_date'],
            (string)$data['user_search_type'],
            (string)$data['user_search'],
            (string)$data['date'],
            trim((string)$data['name']),
            trim((string)$data['uid']),
            trim((string)$data['phone']),
            trim((string)$data['keyword'])
        ));
    }

    protected function getManageWhere(): array
    {
        $where = $this->request->params([
            'keywords', 'date', 'store_name', 'activity_type', 'status', 'mer_id',
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
