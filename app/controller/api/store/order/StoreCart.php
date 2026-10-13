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

namespace app\controller\api\store\order;

use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\pionts\PointsProductRepository;
use app\common\repositories\store\product\ProductAssistRepository;
use app\common\repositories\store\product\ProductAssistSetRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductGroupRepository;
use app\common\repositories\store\product\ProductPresellRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\StoreDiscountProductRepository;
use app\common\repositories\store\product\StoreDiscountRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use app\common\repositories\user\UserRepository;
use MongoDB\BSON\MaxKey;
use think\App;
use crmeb\basic\BaseController;
use app\validate\api\StoreCartValidate as validate;
use app\common\repositories\store\order\StoreCartRepository as repository;
use think\exception\ValidateException;

class StoreCart extends BaseController
{
    /**
     * @var repository
     */
    protected $repository;

    /**
     * StoreBrand constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表信息
     *
     * 本函数用于从仓库中获取列表数据，并以成功响应的形式返回给调用方。
     * 它首先通过请求对象获取用户信息，然后利用这些信息从仓库中获取相应的列表数据。
     * 最后，使用应用的JSON工具类将数据包装在一个成功的响应中返回。
     *
     * @return \Illuminate\Http\JsonResponse 成功获取数据后的JSON响应
     */
    public function lst()
    {
        // 从仓库中根据当前用户信息获取列表数据
        $data = $this->repository->getList($this->request->userInfo());

        // 构建并返回一个表示成功的JSON响应，包含获取的数据
        return app('json')->success($data);
    }

    /**
     * 添加商品到购物车
     *
     * @param validate $validate 输入验证器，用于验证请求参数的合法性
     * @return json 返回操作结果，如果失败则包含错误信息
     */
    public function create(validate $validate)
    {
        // 根据验证器验证参数，并获取验证后的数据
        $data = $this->checkParams($validate);

        // 检查商品类型是否在允许的范围内
        if(!in_array($data['product_type'],[0,1,2,3,4,20]))
            return app('json')->fail('商品类型错误');

        // 检查购买数量是否大于0
        if ($data['cart_num'] <= 0)
            return app('json')->fail('购买数量有误');

        // 获取当前用户信息
        $user = $this->request->userInfo();

        // 触发购物车添加前的事件，允许其他功能插件在此事件中进行干预
        event('user.cart.before',compact('user','data'));

        // 初始化商品来源和来源ID
        $data['source'] = $data['product_type'];
        $data['source_id'] = $data['product_id'];

        // 根据商品类型执行不同的检查逻辑
        switch ($data['product_type'])
        {
            case 0:  // 普通商品
                $result = app()->make(ProductRepository::class)->cartCheck($data,$this->request->userInfo());
                // 处理商品来源，如果请求参数中包含有效的来源信息，则更新商品来源和来源ID
                [$source, $sourceId, $pid] = explode(':', $this->request->param('source/d', '0'), 3) + ['', '', ''];
                $data['source'] = (in_array($source, [0, 1]) && $pid == $data['product_id']) ? $source : 0;
                if ($data['source'] > 0) $data['source_id'] = intval($sourceId);
                break;
            case 1:  // 秒杀商品
                $result = app()->make(ProductRepository::class)->cartSeckillCheck($data,$this->request->userInfo());
                $data['source_id'] = $result['active_id'];
                break;
            case 2:  // 预售商品
                $result = app()->make(ProductPresellRepository::class)->cartCheck($data,$this->request->userInfo());
                $data['product_id'] = $result['product']['product_id'];
                break;
            case 3: // 助力商品
                $result = app()->make(ProductAssistSetRepository::class)->cartCheck($data,$this->request->userInfo());
                $data['product_id'] = $result['product']['product_id'];
                break;
            case 4: // 拼团商品
                $result = app()->make(ProductGroupRepository::class)->cartCheck($data,$this->request->userInfo());
                $data['product_id'] = $result['product']['product_id'];
                $data['source_id'] = $data['group_buying_id'];
                break;
            case 20:// 积分商品
                $result = app()->make(PointsProductRepository::class)->cartCheck($data,$this->request->userInfo());
                $data['product_id'] = $result['product']['product_id'];
                break;
        }

        // 移除不需要保存到购物车的字段
        unset($data['group_buying_id']);

        // 如果购物车中已存在该商品，则更新数量；否则，添加新商品到购物车
        if ($cart = $result['cart']) {
            // 更新购物车
            $cart_id = $cart['cart_id'];
            $cart_num = ['cart_num' => ($cart['cart_num'] + $data['cart_num'])];
            $storeCart = $this->repository->update($cart_id,$cart_num);
        } else {
            // 添加购物车
            $data['uid'] = $this->request->uid();
            $data['mer_id'] = $result['product']['mer_id'];
            $cart = $storeCart = $this->repository->create($data);
        }

        // 触发购物车添加后的事件
        event('user.cart', compact('user','storeCart'));

        // 返回添加成功的购物车ID
        return app('json')->success(['cart_id' => (int)$cart['cart_id']]);
    }

    /**
     * 修改购物车中商品的数量。
     *
     * 此方法用于处理用户在购物车中更改商品数量的请求。它首先验证请求的数量是否有效，
     * 然后检查商品的库存和购买限制，最后更新购物车中的商品数量。
     *
     * @param int $id 购物车项的ID。
     * @return \think\Response 返回一个JSON响应，包含操作的结果信息。
     */
    public function change($id)
    {
        // 从请求中获取修改后的商品数量和唯一属性ID
        $where = $this->request->params(['cart_num']);
        $product_attr_unique = $this->request->param('product_attr_unique/s');

        // 检查修改后的数量是否小于0，如果是，则返回错误信息
        if (intval($where['cart_num']) < 1)
            return app('json')->fail('数量不能小于1');

        // 尝试根据ID和用户ID获取购物车项，如果不存在，则返回错误信息
        if (!$cart = $this->repository->getOne($id, $this->request->uid()))
            return app('json')->fail('购物车信息不存在');

        // 如果商品有单次购买限制，检查此次购买数量是否超过限制
        if ($cart->product->pay_limit == 1) {
            $cart_num = app()->make(ProductRepository::class)->productOnceCountCart($cart['product_id'], $cart['product_attr_unique'], $this->request->uid());
            if (($cart_num - $cart['cart_num'] + $where['cart_num']) > $cart->product->once_max_count)
                return app('json')->fail('单次购买限制 ' . $cart->product->once_max_count . ' 件');
        }

        // 根据唯一属性ID检查SKU是否存在，如果不存在，则返回错误信息
        if (!$res = app()->make(ProductAttrValueRepository::class)->getOptionByUnique($product_attr_unique ?? $cart['product_attr_unique']))
            return app('json')->fail('SKU不存在');

        // 检查库存是否足够，如果不足，则返回错误信息
        if ($res['stock'] < $where['cart_num'])
            return app('json')->fail('库存不足');

        // 如果提供了唯一属性ID，则更新购物车项的唯一属性ID
        if($product_attr_unique){
            $where['product_attr_unique'] = $product_attr_unique;
        }

        // 检测购物车是否存在和该商品相同sku的购物车记录，如果有则合并加数量，并删除多余的
        if(!empty($product_attr_unique)) {
            $isExist = $this->repository->getCartByProductSku($product_attr_unique, $this->request->uid(), '', $id);
            if($isExist){
                $where['cart_num'] = bcadd($cart['cart_num'], $isExist['cart_num']);
                $this->repository->delete($isExist['cart_id']);
            }
        }

        // 更新购物车项的数量
        $this->repository->update($id, $where);

        // 返回成功信息
        return app('json')->success('修改成功');
    }

    /**
     * 批量删除购物车中的商品
     *
     * 本函数用于处理用户请求，批量删除用户购物车中的指定商品项。
     * 它首先从请求中获取待删除的购物车项的ID列表，然后调用仓库接口进行批量删除操作。
     * 如果ID列表为空，或者删除操作失败，函数将返回相应的错误信息。
     * 成功删除所有指定购物车项后，函数会返回一个成功提示。
     *
     * @return \think\response\Json 删除操作的结果，成功时包含成功消息，失败时包含错误消息。
     */
    public function batchDelete()
    {
        // 从请求中获取待删除的购物车项的ID列表
        $ids = $this->request->param('cart_id/a');

        // 检查ID列表是否为空，如果为空则返回参数错误的响应
        if(!count($ids)) return app('json')->fail('参数错误');

        // 调用仓库接口，批量删除指定的购物车项
        $this->repository->batchDelete($ids, $this->request->uid());

        // 返回删除成功的响应
        return app('json')->success('删除成功');
    }

    /**
     * 获取购物车商品数量
     *
     * 本函数用于获取当前用户购物车中的商品总数。它通过调用仓库层的相应方法来实现，
     * 并使用JSON工具类对结果进行包装，以便于前端获取和显示。
     *
     * @return \Illuminate\Http\JsonResponse 返回一个包含购物车商品数量的JSON响应
     */
    public function cartCount()
    {
        // 调用应用程序中的JSON工具类，以成功状态返回用户购物车商品数量
        return app('json')->success($this->repository->getCartCount($this->request->uid()));
    }

    /**
     * 检查购物车中商品的合法性
     * 该方法用于在添加或更新购物车商品前，验证商品及相关属性的有效性，包括商品存在性、库存、属性唯一性等。
     * @param array $data 包含商品ID、属性唯一标识和购买数量的数据数组
     * @return array 返回经过验证和处理后的数据数组，包含是否为新商品、用户ID、商家ID等信息
     * @throws ValidateException 如果验证过程中发现任何问题，将抛出此异常
     */
    public function check($data)
    {
        // 根据商品ID获取商品信息
        $product = app()->make(ProductRepository::class)->get($data['product_id']);
        // 如果商品不存在，则抛出异常
        if (!$product) {
            throw new ValidateException('商品不存在');
        }
        // 如果购买数量小于0，则抛出异常
        if ($data['cart_num'] < 0) {
            throw new ValidateException('数量必须大于0');
        }
        // 根据商品属性唯一标识获取属性值信息
        if (!$res = app()->make(ProductAttrValueRepository::class)->getOptionByUnique($data['product_attr_unique'])) {
            throw new ValidateException('SKU不存在');
        }
        // 如果获取的属性值所属的商品ID与输入的商品ID不一致，则抛出异常
        if ($res['product_id'] != $data['product_id']) {
            throw new ValidateException('数据不一致');
        }
        // 如果购买数量超过属性值的库存，则抛出异常
        if ($res['stock'] < $data['cart_num']) {
            throw new ValidateException('库存不足');
        }
        // 设置数据数组中的字段，标记为新商品，设置用户ID和商家ID
        $data['is_new'] = 1;
        $data['uid'] = $this->request->uid();
        $data['mer_id'] = $product['mer_id'];
        // 返回处理后的数据数组
        return $data;
    }

    /**
     * 再次提交数据进行验证和创建
     * 本函数的目的是接收并验证一组数据，然后将这些数据提交给仓库创建购物车项。
     * 它首先通过验证器对每个数据项进行验证，然后将验证通过的数据项创建为购物车项。
     *
     * @param validate $validate 验证器对象，用于数据验证
     * @return json 返回一个包含创建的购物车项ID的JSON对象
     */
    public function again(validate $validate)
    {
        // 从请求中获取名为data的参数，如果不存在则默认为空数组
        $param = $this->request->param('data/a',[]);

        // 遍历参数数组中的每个数据项，进行验证和处理
        foreach ($param as $data){
            // 使用验证器对当前数据项进行验证
            $validate->check($data);
            // 调用本对象的check方法进行进一步处理，将处理结果存入$item数组
            $item[] = $this->check($data);
        }

        // 遍历$item数组，将每个数据项提交给仓库创建购物车项，并将购物车ID存入$ids数组
        foreach ($item as $it){
            // 在仓库中创建购物车项，并获取创建的购物车ID
            $it__id = $this->repository->create($it);
            // 将购物车ID转换为整数类型，并存入$ids数组
            $ids[] = (int)$it__id['cart_id'];
        }

        // 返回一个成功的JSON响应，包含所有创建的购物车项的ID
        return app('json')->success(['cart_id' => $ids]);
    }

    /**
     * 检查请求参数的合法性，并处理传播者ID的相关逻辑。
     *
     * 此方法主要用于在添加或更新购物车项时，验证请求参数是否符合预期规则，
     * 并对传播者ID进行特殊处理，确保其有效性。
     *
     * @param validate $validate 验证器对象，用于参数验证。
     * @return array 返回验证后的参数数据。
     */
    public function checkParams(validate $validate)
    {
        // 从请求中提取指定参数，包括产品ID、产品属性唯一标识、购物车数量、是否为新品、产品类型、团购ID和传播者ID。
        $data = $this->request->params(['product_id','product_attr_unique','cart_num','is_new',['product_type',0],['group_buying_id',0],['spread_id',0],['reservation_id',0],['reservation_date','']]);

        // 使用验证器对提取的参数数据进行验证。
        $validate->check($data);

        // 如果传播者ID存在
        if ($data['spread_id']) {
            // 如果传播者ID不等于当前用户ID，则检查该传播者ID对应的用户是否存在。
            if ($data['spread_id'] !== $this->request->userInfo()->uid){
                // 如果传播者用户不存在，则将传播者ID设为0。
                $user = app()->make(UserRepository::class)->get($data['spread_id']);
                if (!$user) $data['spread_id'] = 0;
            } else {
                // 如果传播者ID等于当前用户ID，为了防止自传播，将传播者ID设为0。
                $data['spread_id'] = 0;
            }
        }

        // 返回处理后的参数数据。
        return $data;
    }

    /**
     * 套餐购买
     * @return \think\response\Json
     * @author Qinii
     * @day 1/7/22
     */
    public function batchCreate()
    {
        $data = $this->request->params(['data','discount_id','is_new']);
        $productRepostory = app()->make(ProductRepository::class);
        if (!$data['discount_id'])
            return app('json')->fail('优惠套餐ID不能为空');
        if (!$data['is_new'])
            return app('json')->fail('套餐不能加入购物车');

        $cartData = app()->make(StoreDiscountRepository::class)->check($data['discount_id'], $data['data'], $this->request->userInfo());
        $cart_id = [];
        if ($cartData){
            foreach ($cartData as $datum) {
                $datum['is_new'] = $data['is_new'];
                $cart = $this->repository->create($datum);
                $cart_id[] = (int)$cart['cart_id'];
            }
        }
        return app('json')->success(compact('cart_id'));
    }
    /**
     * 购物车清空
     *
     * @return void
     */
    public function clear()
    {
        $uid = $this->request->uid();
        $cartIds = $this->repository->getCartIds($uid);
        // 批量删除指定的购物车项
        $this->repository->batchDelete($cartIds, $uid);

        return app('json')->success('清空成功');
    }
}
