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
namespace app\controller\merchant\store\behalfcustomerorder;

use think\App;
use crmeb\basic\BaseController;
use app\validate\merchant\UserValidate;
use app\common\repositories\user\UserRepository;
use app\common\repositories\user\UserAddressRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;

class User extends BaseController
{
    protected $validate;
    protected $repository;
    protected $userAddressRepository;
    protected $couponUserRepository;

    public function __construct(
        App $app,
        UserValidate $validate,
        UserRepository $repository,
        UserAddressRepository $userAddressRepository,
        StoreCouponUserRepository $couponUserRepository
    ) {
        parent::__construct($app);
        $this->validate = $validate;
        $this->repository = $repository;
        $this->userAddressRepository = $userAddressRepository;
        $this->couponUserRepository = $couponUserRepository;
    }

    public function __destruct()
    {
        unset($this->validate);
        unset($this->repository);
        unset($this->userAddressRepository);
        unset($this->couponUserRepository);
    }

    protected function getValidate()
    {
        return $this->validate;
    }

    protected function getRepository()
    {
        return $this->repository;
    }

    protected function getUserAddressRepository()
    {
        return $this->userAddressRepository;
    }

    protected function getCouponUserRepository()
    {
        return $this->couponUserRepository;
    }
    /**
     * 查询会员
     *
     * @return void
     */
    public function query()
    {
        $params = $this->request->params(['search']);
        if (!isset($params['search']) || empty($params['search'])) {
            return app('json')->fail('搜索条件不能为空');
        }

        // 获取分页参数
        [$page, $limit] = $this->getPage();

        return app('json')->success($this->getRepository()->queryCustomer($params, $page, $limit));
    }
    /**
     * 会员详情信息
     *
     * @return void
     */
    public function info()
    {
        $params = $this->request->params(['uid']);
        if (!isset($params['uid']) || empty($params['uid'])) {
            return app('json')->fail('用户ID不能为空');
        }

        return app('json')->success($this->getRepository()->userInfo($params['uid']));
    }
    /**
     * 创建会员
     *
     * @return void
     */
    public function create()
    {
        $params = $this->request->params(['nickname', 'phone']);

        $validate = $this->getValidate();
        if (!$validate->userCreateCheck($params)) {
            return app('json')->fail($validate->getError());
        }

        $repository = $this->getRepository();
        $user = $repository->getWhere(['phone' => $params['phone']]);
        if ($user) {
            return app('json')->fail('用户已存在');
        }

        return app('json')->success($repository->merchantRegistrs($params));
    }
    /**
     * 会员地址列表
     *
     * @return void
     */
    public function addressList()
    {
        $params = $this->request->params(['uid', 'tourist_unique_key']);
        [$page, $limit] = $this->getPage();
        if($params['uid'] == 0 && empty($params['tourist_unique_key'])) {
            return app('json')->fail('请传入游客唯一标识');
        }

        return app('json')->success($this->getUserAddressRepository()->getList($params['uid'], $page, $limit, $params['tourist_unique_key']));
    }
    /**
     * 创建会员地址
     * uid == 0,代表游客
     *
     * @return void
     */
    public function addressCreate()
    {
        $params = $this->request->params([
            'uid',
            'real_name',
            'phone',
            'province',
            'province_id',
            'city',
            'city_id',
            'district',
            'district_id',
            'street',
            'street_id',
            'detail',
            'tourist_unique_key'
        ]);

        $validate = $this->getValidate();
        if (!$validate->userAddressCreateCheck($params)) {
            return app('json')->fail($validate->getError());
        }

        $userAddressRepository = $this->getUserAddressRepository();
        if ($params['uid'] != 0 && !$userAddressRepository->defaultExists($params['uid'])) {
            $params['is_default'] = 1;
        }
        $params['post_code'] = 0;

        return app('json')->success($userAddressRepository->create($params));
    }
}
