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
namespace app\controller\api\store\product;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\ProductReservationRepository;

class StorePrdouctReservation extends BaseController
{
    /**
     * @var ProductReservationRepository
     */
    protected $repository;
    protected $userInfo = null;

    /**
     * StoreProduct constructor.
     * @param App $app
     * @param ProductReservationRepository $repository
     */
    public function __construct(App $app, ProductReservationRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    public function showMonth($id)
    {
        $where = $this->request->params([
            ['sku_id', ''],
            ['date', date('Y-m')],
        ]);
        $data = $this->repository->showMonth($id, $where);
        return app('json')->success($data);
    }

    public function showDay($id)
    {
        $where = $this->request->params([
            ['day', date('d')],
            ['date', date('Y-m')],
            ['sku_id', 0]
        ]);
        $data = $this->repository->showDay($id, $where);
        return app('json')->success($data);
    }

    public function checkRange()
    {
        $params = $this->request->params(['address_id', 'mer_id']);
        $uid = $this->userInfo ? $this->userInfo['uid'] : 0;

        if (!$params['address_id']) {
            return app('json')->fail('地址参数错误');
        }
        if (!$params['mer_id']) {
            return app('json')->fail('商户参数错误');
        }

        return app('json')->success($this->repository->checkRange($uid, $params));
    }
}
