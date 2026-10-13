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

namespace app\controller\merchant\store\product;

use app\common\model\store\product\StoreUserCardVerifyLog;
use app\common\repositories\store\product\StoreUserCardRepository;
use app\common\repositories\store\product\StoreUserCardVerifyLogRepository;
use crmeb\basic\BaseController;
use think\App;

class UserCard extends BaseController
{
    protected $repository;

    public function __construct(App $app, StoreUserCardRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function verifyDetail($code)
    {
        $card = $this->repository->detailByVerifyCode((string)$code, $this->request->merId());
        if (!$card) {
            return app('json')->fail('次卡/年卡不存在');
        }

        return app('json')->success($card);
    }

    /**
     * 商户次卡/年卡核销记录列表。
     */
    public function verifyLogList(StoreUserCardVerifyLogRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            ['order_sn', ''],
            ['product_name', ''],
            ['nickname', ''],
            ['date', ''],
            ['is_trader', ''],
        ]);
        $merId = $this->request->merId();
        if ($merId) {
            $where['mer_id'] = $merId;
        }
        return app('json')->success($repository->getList($where, $page, $limit));
    }

    public function verify()
    {
        $data = $this->request->params(['verify_code', ['verify_count', 1], ['remark', ''], ['service_type', 0]]);
        $card = $this->repository->verifyByCode(
            (string)$data['verify_code'],
            $this->request->merId(),
            (int)$data['verify_count'],
            0,
            $this->request->merAdminId(),
            StoreUserCardVerifyLog::VERIFY_TYPE_MERCHANT,
            (string)$data['remark'],
            (int)$data['service_type']
        );

        return app('json')->success('核销成功', $card);
    }
}
