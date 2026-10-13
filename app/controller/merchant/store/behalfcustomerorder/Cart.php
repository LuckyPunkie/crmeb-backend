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
use app\validate\merchant\CartValidate;
use app\common\repositories\user\UserRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;

class Cart extends BaseController
{
    protected $validate;
    protected $repository;
    protected $userRepository;

    public function __construct(App $app, CartValidate $validate, StoreCartRepository $repository, UserRepository $userRepository)
    {
        parent::__construct($app);
        $this->validate = $validate;
        $this->repository = $repository;
        $this->userRepository = $userRepository;
    }

    public function __destruct()
    {
        unset($this->validate);
        unset($this->repository);
        unset($this->userRepository);
    }

    public function getValidate()
    {
        return $this->validate;
    }

    public function getRepository()
    {
        return $this->repository;
    }

    public function getUserRepository()
    {
        return $this->userRepository;
    }
    /**
     * 购物车列表
     *
     * @return void
     */
    public function list()
    {
        $params = $this->request->params(['uid', 'tourist_unique_key']);
        $validate = $this->getValidate();
        if (!$validate->listCheck($params)) {
            return app('json')->fail($validate->getError());
        }
        // 如果存在uid则获取用户信息。如果不存在，则不检测用户信息，视为游客
        $user = null;
        if (!empty($params['uid'])) {
            $user = $this->getUserRepository()->userInfo($params['uid']);
            if (!$user) {
                return app('json')->fail('用户不存在');
            }
        }
        $repository = $this->getRepository();
        $merId = $this->request->merId();
        $cartIds = $repository->getCartIds($params['uid'], $merId, $params['tourist_unique_key']);

        // 获取购物车列表
        $data = $repository->getMerchantList($user, $merId, $cartIds);

        return app('json')->success($data);
    }
    /**
     * 购物车创建
     *
     * @return void
     */
    public function create()
    {
        $params = $this->request->params(['uid', 'cart_num', 'product_id', 'product_attr_unique', 'tourist_unique_key']);

        $validate = $this->getValidate();
        if (!$validate->createCheck($params)) {
            return app('json')->fail($validate->getError());
        }

        // 如果存在uid则获取用户信息。如果不存在，则不检测用户信息，视为游客
        $user = null;
        if (!empty($params['uid'])) {
            $user = $this->getUserRepository()->userInfo($params['uid']);
            if (!$user) {
                return app('json')->fail('用户不存在');
            }
        }
        $params['is_new'] = 0;
        // 触发购物车添加前的事件，允许其他功能插件在此事件中进行干预
        event('user.cart.before', compact('user', 'params'));

        $repository = $this->getRepository();
        // 检测商品是否存在。如果购物车中已存在该商品，则更新数量；否则添加到购物车。
        $check = app()->make(ProductRepository::class)->merchantCartCheck($params, $user, $params['tourist_unique_key']);
        if ($cart = $check['cart']) {
            $cart_id = $cart['cart_id'];
            $cart_num = ['cart_num' => ($cart['cart_num'] + $params['cart_num'])];
            $storeCart = $repository->update($cart_id, $cart_num);
        } else {
            $params['mer_id'] = $this->request->merId();
            $cart = $storeCart = $repository->create($params);
        }

        // 触发购物车处理后的事件
        event('user.cart', compact('user', 'storeCart'));

        $result['cart_id'] = (int)$cart['cart_id'];
        return app('json')->success('添加成功', $result);
    }
    /**
     * 购物车修改
     *
     * @return void
     */
    public function change($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $params = $this->request->params(['uid', 'cart_num', 'product_attr_unique']);
        $validate = $this->getValidate();
        if (!$validate->changeCheck($params)) {
            return app('json')->fail($validate->getError());
        }

        $cart = $this->repository->getOne($id, $params['uid']);
        if (!$cart) {
            return app('json')->fail('购物车信息不存在');
        }
        $params['cart_num'] = $params['cart_num'] ?? $cart['cart_num'];
        $params['product_attr_unique'] = !empty($params['product_attr_unique']) ? $params['product_attr_unique'] : $cart['product_attr_unique'];

        // 如果商品有单次购买限制，检查此次购买数量是否超过限制
        if ($cart->product->once_count) {
            if (!app()->make(ProductRepository::class)->isOverLimit($cart, $params)) {
                return app('json')->fail('单次购买限制 ' . $cart->product->once_count . ' 件');
            }
        }

        // 根据唯一属性ID检查SKU是否存在，如果不存在，则返回错误信息
        if (!$res = app()->make(ProductAttrValueRepository::class)->getOptionByUnique($params['product_attr_unique'], $cart['product_id'])) {
            return app('json')->fail('SKU不存在');
        }

        // 检查库存是否足够，如果不足，则返回错误信息
        if ($res['stock'] < $params['cart_num']) {
            return app('json')->fail('库存不足');
        }
        // 检测购物车是否存在和该商品相同sku的购物车记录，如果有则合并加数量，并删除多余的
        if(!empty($params['product_attr_unique'])){
            $isExist = $this->repository->getCartByProductSku($params['product_attr_unique'], $params['uid'], $cart['tourist_unique_key'], $id);
            if($isExist){
                $params['cart_num'] = bcadd($cart['cart_num'], $isExist['cart_num']);
                $this->repository->delete($isExist['cart_id']);
            }
        }

        $this->repository->update($id, $params);

        return app('json')->success('修改成功');
    }
    /**
     * 购物车数量
     *
     * @return void
     */
    public function count()
    {
        $params = $this->request->params(['uid', 'tourist_unique_key']);
        $validate = $this->getValidate();
        if (!$validate->listCheck($params)) {
            return app('json')->fail($validate->getError());
        }

        $repository = $this->getRepository();
        $merId = $this->request->merId();
        $cartIds = $repository->getCartIds($params['uid'], $merId, $params['tourist_unique_key']);

        $data = $repository->getMerchantCartCount($params['uid'], $cartIds);

        return app('json')->success($data);
    }
    /**
     * 购物车删除
     *
     * @return void
     */
    public function delete($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }

        $repository = $this->getRepository();
        $cart = $repository->get($id);
        if (!$cart) {
            return app('json')->fail('购物车信息不存在');
        }

        $repository->delete($id);

        return app('json')->success('删除成功');
    }
    /**
     * 购物车清空
     *
     * @return void
     */
    public function clear()
    {
        $params = $this->request->params(['uid', 'tourist_unique_key']);
        $validate = $this->getValidate();
        if (!$validate->listCheck($params)) {
            return app('json')->fail($validate->getError());
        }

        $repository = $this->getRepository();
        $merId = $this->request->merId();
        $cartIds = $repository->getCartIds($params['uid'], $merId, $params['tourist_unique_key']);

        // 批量删除指定的购物车项
        $repository->batchDelete($cartIds, $params['uid']);

        return app('json')->success('清空成功');
    }
    /**
     * 单个购物车更新价格
     *
     * @return void
     */
    public function updatePrice($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }
        // 获取参数
        $params = $this->request->params(['old_price', 'type', 'reduce_price', 'discount_rate', 'new_price']);
        $validate = $this->getValidate();
        if (!$validate->updatePriceCheck($params)) {
            return app('json')->fail($validate->getError());
        }
        // 获取购物车信息
        $repository = $this->getRepository();
        $cart = $repository->get($id);
        if (!$cart) {
            return app('json')->fail('购物车信息不存在');
        }

        // 更新价格
        $res = $repository->updatePrice($id, $params);
        if (!$res) {
            return app('json')->fail('修改失败');
        }

        return app('json')->success('修改成功');
    }
    /**
     * 批量更新购物车价格
     *
     * @return void
     */
    public function batchUpdatePrice()
    {
        $params = $this->request->params(['cart_ids', 'uid', 'old_pay_price', 'change_fee_type', 'reduce_price', 'discount_rate', 'new_pay_price']);

        $validate = $this->getValidate();
        if (!$validate->batchUpdatePriceCheck($params)) {
            return app('json')->fail($validate->getError());
        }

        $params['merId'] = $this->request->merId();
        $repository = $this->getRepository();
        $res = $repository->batchUpdatePrice($params);
        if (!$res) {
            return app('json')->fail('更新失败');
        }

        return app('json')->success('更新成功');
    }
}
