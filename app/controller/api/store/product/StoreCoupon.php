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


use app\common\repositories\store\coupon\StoreCouponProductRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use crmeb\basic\BaseController;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class StoreCoupon
 * @package app\controller\api\store\product
 * @author xaboy
 * @day 2020/6/1
 */
class StoreCoupon extends BaseController
{
    /**
     * @var
     */
    protected $uid;

    /**
     * StoreCoupon constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        if ($this->request->isLogin()) $this->uid = $this->request->uid();
    }

    /**
     * 获取用户优惠券列表
     *
     * 本方法用于获取当前用户所拥有的优惠券列表。通过接收前端请求中的页码和每页数量，
     * 以及可能的状态标签参数，来筛选并返回符合条件的优惠券列表。
     *
     * @param StoreCouponUserRepository $repository 优惠券用户仓库，用于查询优惠券用户信息。
     * @return json 返回一个包含优惠券列表信息的JSON对象，成功时包含数据列表和成功信息，
     *               失败时包含错误代码和消息。
     */
    public function lst(StoreCouponUserRepository $repository)
    {
        // 获取请求中的页码和每页数量
        [$page, $limit] = $this->getPage();

        // 从请求中获取状态标签参数
        $where = $this->request->params(['statusTag']);

        // 设置查询条件：用户ID
        $where['uid'] = $this->uid;

        // 返回查询结果，包含分页信息
        return app('json')->success($repository->userList($where, $page, $limit));
    }

    /**
     * 根据优惠券ID获取有效的优惠券信息
     *
     * 本函数用于处理请求，根据传入的优惠券ID数组，查询并返回有效的优惠券信息。
     * 它首先从请求中获取优惠券ID的列表，然后通过查询产品与优惠券的关系，筛选出有效的优惠券。
     * 最后，将有效的优惠券信息以JSON格式返回给前端。
     *
     * @param StoreCouponRepository $repository 优惠券仓库接口，用于查询优惠券信息
     * @param StoreCouponProductRepository $couponProductRepository 优惠券产品仓库接口，用于查询产品和优惠券的关系
     * @return \think\response\Json 返回一个JSON对象，包含有效的优惠券信息或空数组
     */
    public function coupon(StoreCouponRepository $repository, StoreCouponProductRepository $couponProductRepository)
    {
        // 从请求中获取优惠券ID的字符串，然后过滤掉空值，得到一个数组
        $ids = array_filter(explode(',', $this->request->param('ids/s')));

        // 如果获取的优惠券ID数组为空，则直接返回空的JSON成功响应
        if (!count($ids))
            return app('json')->success([]);

        // 根据优惠券ID数组，查询这些优惠券所关联的产品ID
        $productCouponIds = $couponProductRepository->productByCouponId($ids);

        // 如果查询到关联产品ID，就进一步查询这些优惠券是否有效，并根据用户ID过滤，最后转换为数组
        // 如果没有查询到关联产品ID，则直接返回空数组
        $productCoupon = count($productCouponIds) ? $repository->validProductCoupon($productCouponIds, $this->uid)->toArray() : [];

        // 返回包含有效优惠券信息的JSON成功响应
        return app('json')->success($productCoupon);
    }

    /**
     * 获取商家优惠券信息
     *
     * 本函数用于根据商家ID和用户ID，获取指定商家的优惠券信息。如果请求参数中包含'all'参数，
     * 则表示获取所有优惠券，否则只获取可使用的优惠券。
     *
     * @param int $id 商家ID
     * @param StoreCouponRepository $repository 优惠券仓库对象，用于查询优惠券信息
     * @return json 返回包含优惠券信息的JSON对象，如果成功，则包含优惠券详情；如果失败，则包含错误信息。
     */
    public function merCoupon($id, StoreCouponRepository $repository)
    {
        // 获取请求参数'all'的值，用于判断是否查询所有优惠券
        $all = (int)$this->request->param('all');

        // 根据商家ID、用户ID和查询所有优惠券的标志，查询有效的商家优惠券信息
        // 如果'all'为1，则查询所有优惠券，否则只查询可使用的优惠券
        $coupon = $repository->validMerCoupon($id, $this->uid, $all === 1 ? null : 0)->toArray();

        // 返回查询结果，使用JSON格式响应
        return app('json')->success($coupon);
    }

    /**
     * 用户领取优惠券功能
     *
     * 此方法用于处理用户领取指定优惠券的操作。它首先检查优惠券是否存在，
     * 如果存在，则将该优惠券标记为被当前用户领取。如果优惠券不存在，
     * 则向用户返回错误信息；如果领取成功，则返回成功信息。
     *
     * @param int $id 优惠券ID
     * @param StoreCouponRepository $repository 优惠券仓库对象，用于操作优惠券数据
     * @return mixed 返回JSON格式的响应，包含成功或失败的信息
     */
    public function receiveCoupon($id, StoreCouponRepository $repository)
    {
        // 检查优惠券是否存在
        if (!$repository->exists($id)) {
            // 如果优惠券不存在，返回失败信息
            return app('json')->fail('优惠券不存在');
        }

        // 标记优惠券为被当前用户领取
        $repository->receiveCoupon($id, $this->uid);

        // 领取成功，返回成功信息
        return app('json')->success('领取成功');
    }

    /**
     * 可领取的优惠券列表
     * @author Qinii
     * @day 3/14/22
     */
    public function getList(StoreCouponRepository $couponRepository)
    {
        $where = $this->request->params(['type','mer_id', 'product','region_id','is_pc',['send_type',0]]);
        [$page, $limit] = $this->getPage();
        $data = $couponRepository->apiList($where, $page, $limit, $this->uid);
        return app('json')->success($data);
    }

    /**
     *  新人注册赠送优惠券
     * @param StoreCouponRepository $couponRepository
     * @return \think\response\Json
     * @author Qinii
     */
    public function newPeople(StoreCouponRepository $couponRepository)
    {
        $coupons = $couponRepository->newPeopleCoupon();
        foreach ($coupons as $coupon){
            if($coupon['coupon_type']){
                $coupon['use_end_time'] = explode(' ', $coupon['use_end_time'])[0] ?? '';
                $coupon['use_start_time'] = explode(' ', $coupon['use_start_time'])[0] ?? '';
            }else{
                $coupon['use_start_time'] = date('Y-m-d');
                $coupon['use_end_time'] = date('Y-m-d', strtotime('+ ' . $coupon['coupon_time'] . ' day'));
            }
            if($coupon['use_end_time']){
                $coupon['use_end_time'] = date('Y.m.d',strtotime($coupon['use_end_time']));
            }
            if($coupon['use_start_time']){
                $coupon['use_start_time'] = date('Y.m.d',strtotime($coupon['use_start_time']));
            }
        }
        $data = systemConfig(['newcomer_status','register_money_status','register_integral_status', 'register_give_integral','register_give_money','register_popup_pic','register_popup_url']);
        $data['coupon'] = $coupons;
        return app('json')->success($data);
    }
}
