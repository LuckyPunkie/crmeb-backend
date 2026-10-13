<?php

namespace app\controller\api\merchant;

use app\common\repositories\merchant\MerchantMemberRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * C端商户会员控制器：负责店铺会员开通、会员信息查询、会员等级列表、开通协议、会员页DIY配置、注销会员、会员优惠券列表与领取等接口，继承自 BaseController
 */
class Member extends BaseController
{
    protected MerchantMemberRepository $repository;

    /**
     * 构造函数，初始化控制器
     * @param App $app 应用实例
     * @param MerchantMemberRepository $repository 商户会员仓储
     */
    public function __construct(App $app, MerchantMemberRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * C端-加入店铺会员：校验用户是否同意会员协议后开通指定店铺会员并发放等级权益
     * @param $merId 店铺ID
     * @return \think\response\Json
     */
    public function become(StoreCouponRepository $couponRepository)
    {
        $params = $this->request->params([['mer_id',0],['agreed',false]]);
        $merId = $params['mer_id'];
        $agreed = $params['agreed'];
        $uid =   $this->request->uid();
        $data = $this->repository->becomeMember($uid, (int)$merId, (bool)$agreed);
        $couponIds = $data['coupon_ids'] ?? [];
        $data['coupon_list'] = $couponRepository->merchantMemberCouponListByIds(
            $couponIds,
            (int)$merId,
            (int)$uid
        );
        unset($data['coupon_ids']);
        return app('json')->success($data);
    }

    /**
     * C端-会员店铺列表：分页获取当前用户已加入会员的店铺。
     */
    public function storeList()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success(
            $this->repository->userStoreList($this->request->uid(), $page, $limit)
        );
    }

    /**
     * C端-会员信息：获取当前用户在指定店铺的会员信息及购物金余额
     * @param $merId 店铺ID
     * @return \think\response\Json
     */
    public function info($merId, StoreCouponRepository $couponRepository)
    {
        $merId = (int)$merId;
        $data = $this->repository->memberInfo($this->request->uid(), $merId);
        $data['coupon_list'] = [];
        if ($data['is_merchant_member']) {
            $couponIds = $couponRepository->merchantMemberCouponIds($merId);
            $data['coupon_list'] = $couponRepository->merchantMemberCouponListByIds(
                $couponIds,
                $merId,
                (int)$this->request->uid()
            );
        }
        return app('json')->success($data);
    }

    /**
     * C端-会员等级列表：获取指定店铺的会员等级列表
     * @param $merId 店铺ID
     * @return \think\response\Json
     */
    public function levels($merId)
    {
        return app('json')->success($this->repository->levelList((int)$merId));
    }

    /**
     * C端-开通会员协议：获取指定店铺的会员开通协议内容
     * @param $merId 店铺ID
     * @return \think\response\Json
     */
    public function openAgreement($merId)
    {
        return app('json')->success([
            'agreement' => $this->repository->memberAgreement((int)$merId),
        ]);
    }

    /**
     * C端-注销店铺会员：注销当前用户在指定店铺的会员资格
     * @param $merId 店铺ID
     * @return \think\response\Json
     */
    public function logoff($merId)
    {
        $this->repository->logoffMember($this->request->uid(), (int)$merId);
        return app('json')->success('注销成功');
    }

    /**
     * C端-会员优惠券列表：分页获取当前用户在指定店铺可领取的会员优惠券列表
     * @param $merId 店铺ID
     * @param StoreCouponRepository $couponRepository 优惠券仓储
     * @return \think\response\Json
     */
    public function coupons($merId, StoreCouponRepository $couponRepository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($couponRepository->merchantMemberCouponList(
            (int)$merId,
            $this->request->uid(),
            $page,
            $limit
        ));
    }

    /**
     * C端-领取会员优惠券：用户领取指定店铺的会员优惠券
     * @param $id 优惠券ID
     * @param StoreCouponRepository $couponRepository 优惠券仓储
     * @return \think\response\Json
     */
    public function receiveCoupon($id, StoreCouponRepository $couponRepository)
    {
        $couponRepository->receiveMerchantMemberCoupon((int)$id, $this->request->uid());
        return app('json')->success('领取成功');
    }
}
