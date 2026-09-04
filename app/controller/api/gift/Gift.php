<?php

namespace app\controller\api\gift;

use app\common\repositories\gift\GiftRepository;
use crmeb\basic\BaseController;
use think\App;

class Gift extends BaseController
{
    /** @var GiftRepository */
    protected $repository;

    public function __construct(App $app, GiftRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function list()
    {
        return app('json')->success($this->repository->getOnShelfList());
    }

    public function createOrder()
    {
        $uid = $this->request->uid();
        [$giftId, $receiverId, $sceneType, $payMethod] = $this->request->params([
            ['gift_id', 0],
            ['receiver_id', 0],
            ['scene_type', GiftRepository::SCENE_CHAT],
            ['pay_method', 'balance'],
        ], true);

        $order = $this->repository->createOrder(
            $uid,
            (int)$receiverId,
            (int)$giftId,
            (string)$sceneType,
            (string)$payMethod
        );
        return app('json')->success($order);
    }

    public function pay()
    {
        $uid = $this->request->uid();
        [$orderNo, $payType, $returnUrl] = $this->request->params([
            ['order_no', ''],
            ['pay_type', 'balance'],
            ['return_url', ''],
        ], true);

        if (!$orderNo) {
            return app('json')->fail('订单号不能为空');
        }

        $isApp = (bool)$this->request->param('is_app', false);
        $result = $this->repository->pay((string)$orderNo, $uid, (string)$payType, (string)$returnUrl, $isApp);
        return app('json')->success($result);
    }

    public function orderStatus()
    {
        $uid = $this->request->uid();
        $orderNo = (string)$this->request->param('order_no', '');
        return app('json')->success($this->repository->getOrderStatus($orderNo, $uid));
    }

    public function receivedList()
    {
        [$page, $limit] = $this->getPage();
        $uid = $this->request->uid();
        return app('json')->success($this->repository->getReceivedList($uid, $page, $limit));
    }

    public function sentList()
    {
        [$page, $limit] = $this->getPage();
        $uid = $this->request->uid();
        return app('json')->success($this->repository->getSentList($uid, $page, $limit));
    }
}
