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


namespace app\common\middleware;

use app\common\repositories\delivery\DeliveryServiceRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\store\staff\StaffsRepository;
use think\exception\HttpResponseException;
use think\Response;
use app\Request;

class MerchantServerMiddleware extends BaseMiddleware
{
    protected $merId;

    public function before(Request $request)
    {
        $userInfo = $this->request->userInfo();
        $this->merId = $this->request->route('merId');

        /**
         *  [reqire => true,auth => []], [reqire => true]
         */
        $serverParams = $this->getArg(0);//0 客服
        $staffsParams = $this->getArg(1);//1 员工
        $deliveryParams = $this->getArg(2);//2 配送员

        if (empty($serverParams) && empty($staffsParams) && empty($deliveryParams))
            throw new HttpResponseException(app('json')->fail('缺少角色标识'));

        if (!empty($serverParams)) {
            $service = $this->getServers($userInfo, $serverParams);
            if ($serverParams['reqire']){
                if (!$service) {
                    throw new HttpResponseException(app('json')->fail('[1]您没有权限操作'));
                }

                $field = $serverParams['auth'] ? 'is_goods' : 'customer';
                if (!$service->{$field}) {
                    throw new HttpResponseException(app('json')->fail('[2]您没有权限操作'));
                }
            }
            $request->macro('isServer', function () use (&$service) {
                return $service ? true : false;
            });
            $request->macro('serviceInfo', function () use (&$service) {
                return $service;
            });
        }

        if (!empty($staffsParams)) {
            $staffs = $this->getStaffs($userInfo);
            if ($staffsParams['reqire'] && !$staffs) {
                throw new HttpResponseException(app('json')->fail('[3]您没有权限操作'));
            }
            $request->macro('isStaffs', function () use (&$staffs) {
                return $staffs ? true : false;
            });
            $request->macro('staffsList', function () use (&$staffs) {
                return $staffs;
            });
            $request->macro('staffsIds', function () use (&$staffs) {
                $staffs_id = array_column($staffs,'staffs_id');
                return $staffs_id;
            });
            $request->macro('staffsMerIds', function () use (&$staffs) {
                return array_keys($staffs);
            });
        }

        if (!empty($deliveryParams)) {
            $delivery = $this->getDelivery($userInfo);
            if ($deliveryParams['reqire'] && !$delivery) {
                throw new HttpResponseException(app('json')->fail('[3]您没有权限操作'));
            }
            $request->macro('isDelivery', function () use (&$delivery) {
                return $delivery ? true : false;
            });
            $request->macro('deliveryList', function () use (&$delivery) {
                return $delivery;
            });
            $request->macro('deliveryIds', function () use (&$delivery) {
                $delivery_id = array_column($delivery,'service_id');
                return $delivery_id;
            });
            $request->macro('deliveryMerIds', function () use (&$delivery) {
                return array_keys($delivery);
            });
        }
    }

    protected function getDelivery($userInfo)
    {
        $serviceRepository = app()->make(DeliveryServiceRepository::class);
        $delivery = $serviceRepository->getSearch(['uid' => $userInfo->uid, 'status' => 1])
            ->column('service_id,mer_id,uid,avatar,name,phone,status','mer_id');
        return $delivery;
    }

    protected function getStaffs($userInfo)
    {
        $staffsRepository = app()->make(StaffsRepository::class);
        $staffs = $staffsRepository->getSearch(['uid' => $userInfo->uid, 'status' => 1])
            ->column('staffs_id,mer_id,uid,photo,name,phone,status','mer_id');
        return $staffs;
    }

    protected function getServers($userInfo, $params)
    {
        $merId = $this->request->route('merId',0);
        $storeServiceRepository = app()->make(StoreServiceRepository::class);
        $service = $storeServiceRepository->getService($userInfo->uid, $merId);
        if (!$service && $userInfo->main_uid) {
            $service = $storeServiceRepository->getService($userInfo->main_uid, $merId);
        }
        return $service;
    }

    public function after(Response $response)
    {
        // TODO: Implement after() method.
    }
}
