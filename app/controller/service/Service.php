<?php

namespace app\controller\service;

use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\service\StoreServiceLogRepository;
use app\common\repositories\store\service\StoreServiceUserRepository;
use app\common\repositories\system\ExtendRepository;
use crmeb\basic\BaseController;
use crmeb\services\UploadService;
use think\App;

class Service extends BaseController
{
    protected $repository;

    public function __construct(App $app, StoreServiceUserRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 客服列表
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function serviceUserList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword']);
        $admin = $this->request->adminInfo();
        $where['service_id'] = $admin->service_id;
        return app('json')->success($this->repository->serviceUserList($where, $admin->mer_id, $page, $limit));
    }

    /**
     * 用户备注
     * @param $uid
     * @param StoreServiceUserRepository $serviceUserRepository
     * @param ExtendRepository $extendRepository
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function mark($uid, StoreServiceUserRepository $serviceUserRepository, ExtendRepository $extendRepository)
    {
        $data = $this->request->params(['mark']);
        $service = $this->request->adminInfo();
        if ($service->mer_id && !$serviceUserRepository->existsWhere(['uid' => (int)$uid, 'mer_id' => $service->mer_id])) {
            return app('json')->fail('用户不存在');
        }
        $extendRepository->updateInfo(ExtendRepository::TYPE_SERVICE_USER_MARK, (int)$uid, $service->mer_id, (string)$data['mark']);
        return app('json')->success('备注成功');
    }

    /**
     * 聊天历史
     * @param $uid
     * @param StoreServiceLogRepository $logRepository
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function history($uid, StoreServiceLogRepository $logRepository)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['last_id']);
        $service = $this->request->adminInfo();
        return app('json')->success($logRepository->serviceList($service->mer_id, $service->service_id, (int)$uid, $page, $limit, $where['last_id']));
    }

    /**
     * 文件上传
     * @param $field
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function upload($field)
    {
        $file = $this->request->file($field);
        if (!$file) return app('json')->fail('请上传图片');
        $upload = UploadService::create();
        $data = $upload->to('attach')->validate()->move($field);
        if ($data === false) {
            return app('json')->fail($upload->getError());
        }
        return app('json')->success(['src' => tidy_url($upload->getFileInfo()->filePath)]);
    }

    /**
     * 获取订单详情
     * @param $id
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function getOrderInfo($id)
    {
        return app('json')->success(app()->make(StoreOrderRepository::class)->getOne($id, null));
    }

    /**
     * 获取订单状态
     * @param $id
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function orderStatus($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','user_type']);
        $where['id'] = $id;
        return app('json')->success(app()->make(StoreOrderRepository::class)->getOrderStatus($where, $page, $limit));
    }

    /**
     * 获取退款订单
     * @param $id
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function getRefundOder($id)
    {
        $data = app()->make(StoreRefundOrderRepository::class)->getOne($id);
        if (!$data) return app('json')->fail("数据不存在");
        return app('json')->success($data);
    }

    /**
     * 订单物流
     * @param $id
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function orderExpress($id)
    {
        $make = app()->make(StoreOrderRepository::class);
        return app('json')->success($make->express($id, null));
    }

    /**
     * 退款订单物流
     * @param $id
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function refundOrderExpress($id)
    {
        $make = app()->make(StoreRefundOrderRepository::class);
        return app('json')->success($make->express($id));
    }

    /**
     * 商品详情
     * @param $id
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function product($id)
    {
        $data = app()->make(ProductRepository::class)->getWhere(['product_id' => $id],'*',['content']);
        return app('json')->success($data);
    }

}
