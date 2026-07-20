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


namespace app\controller\merchant\store\coupon;


use app\common\repositories\store\coupon\StoreCouponSendRepository;
use crmeb\basic\BaseController;
use think\App;

class CouponSend extends BaseController
{
    protected $repository;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param StoreCouponSendRepository $repository 优惠券发放记录仓库实例
     */
    public function __construct(App $app, StoreCouponSendRepository $repository)
    {
        // 调用父类构造函数
        parent::__construct($app);
        // 初始化仓库实例
        $this->repository = $repository;
    }

    /**
     * 获取优惠券发放记录列表
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询条件
        $where = $this->request->params(['date', 'coupon_type', 'coupon_name', 'status']);
        // 添加商家ID查询条件
        $where['mer_id'] = $this->request->merId();
        // 调用仓库实例的获取列表方法并返回结果
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

}
