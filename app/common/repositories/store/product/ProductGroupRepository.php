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
namespace app\common\repositories\store\product;

use app\common\model\store\product\ProductLabel;
use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductGroupDao;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\StoreCategoryRepository;
use crmeb\services\SwooleTaskService;
use think\exception\ValidateException;
use think\facade\Db;
use think\Queue;

/**
 * 拼团商品信息
 */
class ProductGroupRepository extends BaseRepository
{
    protected $dao;

    /**
     * ProductGroupRepository constructor.
     * @param ProductGroupDao $dao
     */
    public function __construct(ProductGroupDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建拼团产品
     * @param string $merId 商家ID
     * @param array $data 产品数据
     */
    public function create($merId,$data)
    {
        // 实例化产品仓库，用于后续的产品创建操作
        $product_make = app()->make(ProductRepository::class);

        // 构建产品信息数组，包含产品的基本属性
        $product = [
            'store_name' => $data['store_name'],
            'image' => $data['image'],
            'slider_image' => $data['slider_image'],
            'store_info' => $data['store_info'],
            'unit_name' => $data['unit_name'],
            'temp_id' => $data['temp_id'],
            'product_type' => 4,
            'status'    => 1,
            'sort' => $data['sort'],
            'old_product_id'    => $data['product_id'],
            'guarantee_template_id'=>$data['guarantee_template_id'],
            'sales' => 0,
            'rate'  => 3,
            'integral_rate' => 0,
            'delivery_way' => $data['delivery_way'],
            'delivery_free' => $data['delivery_free'],
        ];

        // 使用数据库事务来确保一系列操作的原子性
        Db::transaction(function()use($data,$product_make,$product,$merId) {
            // 触发事件，可以在事件监听器中执行额外的操作
            event('product.groupCreate.before',compact('data','merId'));

            // 复制产品，这里的产品复制是特定业务逻辑的一部分
            $product_id = $product_make->productCopy($data['product_id'], $product, 4);

            // 调用sltNumber方法，该方法的作用未在代码中显示，推测是计算或处理某些数据
            $slt = $this->sltNumber($data);

            // 构建拼团产品信息数组，并插入数据库
            $result = [
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'status' => 0,
                'is_show' => $data['is_show'] ?? 1,
                'product_id' => $product_id,
                'pay_count' => $data['pay_count'],
                'once_pay_count' => $data['once_pay_count'],
                'mer_id' => $merId,
                'buying_count_num' => $data['buying_count_num'],
                'buying_num' => $slt['buying_num'],
                'ficti_status' => $data['ficti_status'],
                'ficti_num' => $slt['ficti_num'],
                'time' => $data['time'],
                'leader_extension' => $data['leader_extension'],
                'leader_rate' => $data['leader_rate'],
            ];

            $productGroup = $this->dao->create($result);

            // 实例化拼团产品SKU仓库，用于处理SKU相关操作
            $sku_make = app()->make(ProductGroupSkuRepository::class);

            // 更新数据中的产品ID，为后续SKU的创建做准备
            $data['product_id'] = $product_id;

            // 调用sltSku方法，处理SKU数据，并插入数据库
            $res = $this->sltSku($data,$productGroup->product_group_id);
            $sku_make->insertAll($res['sku']);

            // 更新拼团产品的价格
            $this->dao->update($productGroup->product_group_id,['price' => $res['price']]);

            // 更新原始产品的价格
            $product_make->update($product_id,['price' => $res['old_price']]);

            // 创建SPU（Simple Product Unit，简单产品单元），这里是将产品相关信息存储到SPU表中
            $data['mer_id'] = $merId;
            $data['price'] = $res['price'];
            app()->make(SpuRepository::class)->create($data, $product_id, $productGroup->product_group_id, 4);

            // 触发事件，可以在事件监听器中执行额外的操作
            event('product.groupCreate.before',compact('productGroup'));

            // 发送后台通知，提示有新的拼团产品待审核
            SwooleTaskService::admin('notice', [
                'type' => 'new_group',
                'data' => [
                    'title' => '商品审核',
                    'message' => '您有一个新的拼团商品待审核',
                    'id' => $productGroup->product_group_id
                ]
            ]);
        });
    }

    /**
     * 编辑拼团产品信息
     *
     * @param int $id 产品ID
     * @param array $data 产品数据数组，包含所有需要编辑的信息
     *
     * 此函数用于更新已存在的拼团产品信息。它首先根据传入的数据组装产品和活动信息，
     * 然后在数据库事务中更新产品和相关的SKU信息。此外，它还处理了事件监听和通知。
     */
    public function edit(int $id,array $data)
    {
        // 组装产品信息数组
        $product = [
            'image' => $data['image'],
            'store_name' => $data['store_name'],
            'store_info' => $data['store_info'],
            'slider_image' => implode(',', $data['slider_image']),
            'temp_id' => $data['temp_id'],
            'unit_name' => $data['unit_name'],
            'sort' => $data['sort'],
            'guarantee_template_id'=>$data['guarantee_template_id'],
            'delivery_way' => implode(',',$data['delivery_way']),
            'delivery_free' => $data['delivery_free'],
        ];
        // 计算购买数量和虚构数量
        $slt = $this->sltNumber($data);
        // 组装活动信息数组
        $active = [
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => 1,
            'is_show' => $data['is_show'] ?? 1,
            'pay_count' => $data['pay_count'],
            'once_pay_count' => $data['once_pay_count'],
            'buying_count_num' => $data['buying_count_num'],
            'buying_num' => $slt['buying_num'],
            'ficti_status' => $data['ficti_status'],
            'ficti_num' => $slt['ficti_num'],
            'time' => $data['time'],
            'product_status' => 0,
            'action_status' => 0,
            'leader_extension' => $data['leader_extension'],
            'leader_rate' => $data['leader_rate'],
        ];

        // 使用数据库事务来确保数据一致性
        Db::transaction(function()use($id,$active,$product,$data){
            $product_make = app()->make(ProductRepository::class);
            $sku_make = app()->make(ProductGroupSkuRepository::class);
            // 触发产品更新前的事件
            event('product.groupUpdate.before',compact('id','data'));
            // 获取当前产品信息
            $resData = $this->dao->get($id);
            // 处理SKU相关数据
            $data['product_id'] = $resData['product_id'];
            $res = $this->sltSku($data,$id);
            // 更新活动信息
            $active['price'] = $res['price'];
            $this->dao->update($id,$active);
            // 清理旧的SKU数据缓存
            $sku_make->clear($id);
            // 插入新的SKU数据
            $sku_make->insertAll($res['sku']);
            // 更新产品基本信息
            $product['price'] = $res['old_price'];
            $product_make->update($resData['product_id'],$product);
            // 创建产品内容
            $product_make->createContent($resData['product_id'], ['content' => $data['content']]);
            // 更新SPU信息
            $data['price'] = $res['price'];
            $data['mer_id'] = $resData['mer_id'];
            app()->make(SpuRepository::class)->baseUpdate($data,$resData['product_id'],$id,4);
            // 触发产品更新后的事件
            event('product.groupUpdate',compact('id'));
            // 发送审核通知
            SwooleTaskService::admin('notice', [
                'type' => 'new_group',
                'data' => [
                    'title' => '商品审核',
                    'message' => '您有一个新的拼团商品待审核',
                    'id' => $id
                ]
            ]);
        });

    }

    /**
     * 检测是否每个sku的价格
     * @param array $data
     * @param int $presellId
     * @param int $productId
     * @return array
     * @author Qinii
     * @day 1/8/21
     */
    public function sltSku(array $data,int $ActiveId)
    {
        $make = app()->make(ProductAttrValueRepository::class);
        $sku = [];
        $price = 0;
        $old_price = 0;
        foreach ($data['attrValue'] as $item){
            $skuData = $make->getWhere(['unique' => $item['unique']]);
            if(!$skuData) throw new ValidateException('SKU不存在');
            if(bccomp($item['active_price'],$skuData['price'],2) == 1)
                throw new ValidateException('活动价格不得大于原价');
            if(!$item['active_price'] || $item['active_price'] < 0)
                throw new ValidateException('请正确填写金额');
            $sku[] = [
                'product_group_id' => $ActiveId,
                'product_id' => $data['product_id'],
                'unique' => $item['unique'],
                'stock' => $item['stock'],
                'stock_count' => $item['stock'],
                'active_price' => $item['active_price'],
            ];
            $price = ($price == 0 ) ? $item['active_price'] : (($price > $item['active_price']) ? $item['active_price']:$price) ;
            $old_price = ($old_price == 0 ) ? $item['price'] : (($old_price > $item['price']) ? $item['price']:$old_price) ;
        }
        return compact('sku','price','old_price');
    }

    public function sltNumber($data)
    {
        $ficti_status = systemConfig('ficti_status');
        $buying_num = $data['buying_count_num'];
        $ficti_num = 0;
        if($ficti_status && $data['ficti_status']){
            $ficti_num = (int)round($data['buying_count_num'] * (1 - (systemConfig('group_buying_rate') / 100 )));
            if($data['ficti_num'] > $ficti_num)
                throw new ValidateException('最多虚拟人数超出比例范围');
            $ficti_num = $data['ficti_num'];
            $buying_num = $data['buying_count_num'] - $ficti_num;
        }

        return compact('buying_num','ficti_num');
    }

    /**
     * 获取API列表
     *
     * 根据给定的条件和分页信息，从数据库中检索API列表。此方法包括对搜索条件的合并、定义排序方式，
     * 以及加载相关的产品和商户信息。最后，它计算总记录数，并根据给定的页码和限制返回API列表。
     *
     * @param array $where 搜索条件数组
     * @param int $page 当前页码
     * @param int $limit 每页显示的记录数
     * @return array 包含总记录数和API列表的数组
     */
    public function getApiList(array $where, int $page, int $limit)
    {
        // 合并传入的搜索条件和默认的显示条件
        $where = array_merge($where, $this->dao->actionShow());
        // 定义排序条件
        $where['order'] = 'api';

        // 构建查询并加载相关数据
        $query = $this->dao->search($where)->with([
            'product' => function($query){
                $query->with(['merchant','reservation']);
            },
            'merchant' => function($query) {
                // 定义商户相关字段
                $query->field('mer_id,mer_name,is_trader');
            }
        ]);

        // 计算总记录数
        $count = $query->count();

        // 分页查询并隐藏某些字段，然后追加额外信息
        $list = $query->page($page, $limit)
                      ->hidden(['ficti_status','ficti_num','refusal','is_del'])
                      ->select()
                      ->append(['stock','sales']);

        // 返回包含总记录数和API列表的数组
        return compact('count', 'list');
    }

    /**
     * 获取商家列表
     *
     * 根据给定的条件和分页信息，从数据库中检索商家列表。此方法包括对商家数据的检索，
     * 以及与每个商家相关的产品信息的加载。返回的数据包括商家总数和商家列表。
     *
     * @param array $where 搜索条件数组
     * @param int $page 当前页码
     * @param int $limit 每页显示的记录数
     * @return array 包含商家总数和商家列表的数组
     */
    public function getMerchantList(array $where,int $page,int $limit)
    {
        // 设置排序条件
        $where['order'] = 'sort';

        // 构建查询
        $query = $this->dao->search($where)
            ->with([
                'product' => function($query){
                    // 加载每个商家的产品信息，只包含指定的字段
                    $query->field('product_id,store_name,image,price,sales,sort');
                },
            ])
            ->append(['stock_count','stock','sales','count_take','count_user','us_status'], true);

        // 计算总记录数
        $count = $query->count();

        // 分页查询并处理商家标签
        $list = $query->page($page,$limit)->setOption('field', [])->field('ProductGroup.*,U.mer_labels')->select()
            ->each(function($item){
                // 获取商家标签名称
                $item->mer_labels = ProductLabel::whereIn('product_label_id', $item->mer_labels)->column('product_label_id,label_name as name');
            });

        // 返回商家总数和列表
        return compact('count','list');
    }

    /**
     * 获取管理员列表
     *
     * 此方法用于根据条件获取管理员列表，包括分页和每页的数量限制。
     * 它还同时获取每个管理员相关的商品和商家信息，并对数据进行了一些处理，如计算库存、销量等。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页显示的数量
     * @return array 返回包含管理员数量和管理员列表的数组
     */
    public function getAdminList(array $where,int $page,int $limit)
    {
        // 设置查询排序条件为'star'
        $where['order'] = 'star';

        // 构建查询，包括关联查询和附加信息
        $query = $this->dao->search($where)
            ->with([
                'product' => function($query){
                    // 选择商品相关字段
                    $query->field('product_id,store_name,image,price,sales,rank');
                },
                'merchant' => function($query){
                    // 选择商家相关字段
                    $query->field('mer_id,mer_name,is_trader');
                }
            ])
            ->append(['stock_count','stock','sales','count_take','count_user','star','us_status'], true);

        // 计算满足条件的管理员总数
        $count = $query->count();

        // 进行分页查询，并处理返回的管理员列表
        $list = $query->page($page,$limit)
            ->setOption('field', [])->field('ProductGroup.*,U.sys_labels')->select()
            ->each(function($item){
                // 处理管理员的标签信息，将字符串转换为数组
                if (!$item->sys_labels) {
                    $item->sys_labels = [];
                } else {
                    $item->sys_labels = explode(',',rtrim(ltrim($item->sys_labels,','),','));
                }
            });

        // 返回管理员总数和列表信息
        return compact('count','list');
    }

    /**
     * merchant 编辑时详情
     * @param int $id
     * @return array|\think\Model|null
     * @author Qinii
     * @day 1/8/21
     */
    public function detail(?int $merId,int $id)
    {
        $where[$this->dao->getPk()] = $id;
        $where['is_del'] = 0;
        $data = $this->dao->getWhere($where,'*',[
            'product' => ['attr','oldAttrValue','content'],
            'merchant'=> function($query){
                $query->field('mer_id,mer_avatar,mer_name,is_trader');
            }],
        );
        if(!$data) throw new ValidateException('数据不存在');
        if(!$data['product']) throw new ValidateException('该商品已不存在');

        $data['product']['delivery_way']  = empty($data['product']['delivery_way']) ? [] : explode(',',$data['product']['delivery_way']);

        $spu_where = ['activity_id' => $id, 'product_type' => 4, 'product_id' => $data['product']['product_id']];
        $spu = app()->make(SpuRepository::class)->getSearch($spu_where)->find();
        $data['star'] = $spu['star'] ?? '';
        $data['mer_labels'] = $spu['mer_labels'] ?? '';

        $sku_make = app()->make(ProductGroupSkuRepository::class);
        $attrValue = [];
        foreach ($data['product']['oldAttrValue'] as $key => $item) {
            $sku = explode(',', $item['sku']);
            $item['old_stock'] = $item['stock'];
            $_sku = $sku_make->getWhere([$this->dao->getPk() => $id,'unique' => $item['unique']]);
            if($_sku) $_sku->append(['sales']);
            $item['_sku'] = $_sku;
            if(!$merId && !$item['_sku']) continue;

            foreach ($sku as $k => $v) {
                $item['value' . $k] = $v;
            }

            $attrValue[$key] = $item;
        }
        unset($data['product']['oldAttrValue']);
        $data['product']['attrValue'] = $attrValue;
        foreach ($data['product']['attr'] as $k => $v) {
            $data['product']['attr'][$k] = [
                'value'  => $v['attr_name'],
                'detail' => $v['attr_values']
            ];
        }
        $data->append(['stock','sales','count_take','count_user','us_status','stock_count']);
        $data['type'] = $data['product']['type'];
        return $data;
    }

    /**
     * 获取API详情
     * 该方法用于根据给定的产品ID和用户信息，检索产品的详细信息，包括产品本身的信息以及与团购活动相关的信息。
     * 如果产品不存在或已下架，将抛出一个异常。
     *
     * @param int $id 产品ID，用于查询特定产品
     * @param object $userInfo 用户信息，用于获取与用户相关的特定产品展示信息
     * @return array 返回包含产品详细信息的数组，包括产品本身的信息和团购活动的信息
     * @throws ValidateException 如果产品已下架或不在活动时间内，抛出异常
     */
    public function apiDetail($id,$userInfo)
    {
        // 获取查询条件，通常是用于筛选活动展示产品的条件
        $where = $this->dao->actionShow();
        // 根据产品ID进行查询条件的补充
        $where[$this->dao->getPk()] = $id ?: 0;
        // 根据查询条件搜索产品，并且加载与团购活动相关的发起人信息，隐藏某些字段
        $data = $this->dao->search($where)->with([
            'groupBuying.initiator' => function($query){
                // 筛选状态为0、未删除、未隐藏的团购活动发起人，按创建时间升序排列
                $query->where('status',0)->where('is_del',0)->where('is_hidde',0)
                    ->field('group_buying_id,status,product_group_id,buying_count_num,yet_buying_num,end_time')
                    ->order('create_time ASC');
            }
        ])->hidden(['ficti_status','ficti_num','refusal','is_del'])->find();
        // 如果产品信息不存在，则将产品状态改为已下架，并抛出异常提示产品已下架或不在活动时间内
        if(!$data) {
            app()->make(SpuRepository::class)->changeStatus($id,4);
            throw new ValidateException('商品已下架或不在活动时间内');
        }

        // 创建产品仓库实例，用于后续获取产品的详细信息和展示信息
        $make = app()->make(ProductRepository::class);
        // 获取已成功购买该产品的用户数量
        $data['successUser'] = app()->make(ProductGroupUserRepository::class)->successUser($id);
        // 根据产品ID和用户信息，获取产品详细信息和特定展示信息
        $product = $make->apiProductDetail(['product_id' => $data['product_id']], 4, $id,$userInfo);
        // 获取产品的展示信息，用于合并到产品详细信息中
        $show = $make->getProductShow($data['product_id'],$product,$id,$userInfo->uid ?? 0);
        // 合并产品信息和展示信息
        $data['product'] = array_merge($product,$show);

        // 返回包含产品详细信息和附加信息的数组
        return $data->append(['sales','stock'],true);
    }

    /**
     * 更新产品信息。
     *
     * 该方法用于根据给定的ID和数据数组更新产品的特定属性。它首先通过ID检索产品信息，
     * 然后更新产品的排名，接着删除数据数组中的'star'字段（如果存在），最后更新产品的其他属性。
     *
     * @param int $id 产品的唯一标识ID。
     * @param array $data 包含产品更新数据的数组。
     */
    public function updateProduct(int $id,array $data)
    {
        // 通过ID获取产品信息
        $res = $this->dao->get($id);
        // 更新产品的排名
        app()->make(SpuRepository::class)->changRank($id,$res['product_id'],4,$data);
        // 删除数据数组中的'star'字段，因为它不应该被用于更新产品信息
        unset($data['star']);
        // 更新产品的其他属性
        app()->make(ProductRepository::class)->adminUpdate($res['product_id'],$data);
    }


    /**
     * 检查购物车中的商品是否符合购买条件。
     *
     * 该方法主要用于在用户将商品添加到购物车之前，验证该商品是否满足购买条件。
     * 这包括但不限于：检查商品是否参加拼团、商品是否下架、购买数量是否超过限制、库存是否充足等。
     *
     * @param array $data 商品相关数据，包括商品ID、拼团ID、购买数量等。
     * @param object $userInfo 用户信息。
     * @throws ValidateException 如果商品不符合购买条件，则抛出验证异常。
     * @return array 返回包含商品、SKU和购物车信息的数组。
     */
    public function cartCheck(array $data,$userInfo)
    {
        // 检查是否为新团商品，如果不是，则抛出异常。
        /**
         * 1.是否有团ID
         *     1.1 有团，验证团是否满，状态是否可加入
         * 2.购买数量是否超过限制
         * 3.商品的限购库存
         * 4.原商品的库存
         * 5.限购数是否超出
         */
        if(!$data['is_new']) throw new ValidateException('拼团商品不可加入购物车');

        // 查询商品的购买限制等信息。
        $where = $this->dao->actionShow();
        $where['product_group_id'] = $data['product_id'];
        $res = $this->dao->search($where)->find();
        // 如果商品信息不存在，则抛出异常。
        if(!$res) throw new ValidateException('商品已下架');

        // 检查购买数量是否超过单次购买限制。
        if($data['cart_num'] > $res['once_pay_count']) throw new ValidateException('购买数量超过单次限制');

        // 如果有拼团ID，检查拼团状态是否允许加入。
        if($data['group_buying_id']){
            $buging_make = app()->make(ProductGroupBuyingRepository::class);
            $group_status = $buging_make->checkGroupStatus($data['group_buying_id'],$userInfo);
            if(!$group_status) throw new ValidateException('不可加入此团');
        }

        // 查询商品的SKU信息。
        $make = app()->make(ProductAttrValueRepository::class);
        //$old_sku = $make->getWhere(['unique' => $data['product_attr_unique']]);
        //if($old_sku['stock'] < $res['cart_num']) throw new ValidateException('原商品库存不足');

        $sku_make = app()->make(ProductGroupSkuRepository::class);
        $sku = $sku_make->getWhere(['product_group_id' => $data['product_id'],'unique' => $data['product_attr_unique']]);
        // 检查商品限购数量是否充足。
        if($sku['stock'] < $data['cart_num']) throw new ValidateException('商品限购数量不足');

        // 如果商品有限购数量，检查购买数量是否超过限购数量。
        if($res['pay_count'] !== 0 ) {
            if($data['cart_num'] > $res['pay_count']) throw new ValidateException('购买数量超过活动限制');
            $order_make = app()->make(StoreOrderRepository::class);
            $where = ['product_id' => $res['product_id'], 'product_type' => 4];
            // 计算用户已购买的数量，检查是否超过限购数量。
            $count = (int)$order_make->getTattendCount($where, $userInfo->uid)->sum('product_num');
            if(($count + $data['cart_num']) > $res['pay_count']) throw new ValidateException('购买数量超过活动限制');
        }

        // 准备返回的商品、SKU和购物车信息。
        $product = $res['product'];
        $cart = null;

        // 返回验证后的商品信息。
        return compact('product','sku','cart');
    }

    /**
     * 获取分类列表
     *
     * 通过调用数据访问对象（DAO）获取分类路径数组，进一步处理该数组以获得唯一的分类ID列表。
     * 最后，使用这些分类ID来查询具体的分类名称和ID，为前端展示或进一步处理提供数据。
     *
     * @return array 返回包含分类ID和分类名称的数组
     */
    public function getCategory()
    {
        // 通过DAO获取分类路径数组
        $pathArr = $this->dao->category();
        $path = [];
        // 遍历路径数组，提取每个路径中的分类ID
        foreach ($pathArr as $item){
            $path[] = explode('/',$item)[1];
        }
        // 去除数组中的重复元素，确保唯一性
        $path = array_unique($path);
        // 使用应用容器创建StoreCategoryRepository实例，并根据分类ID列表查询分类名称和ID
        $cat = app()->make(StoreCategoryRepository::class)->getSearch(['ids' => $path])->field('store_category_id,cate_name')->select();
        return $cat;
    }

    /**
     * 更新SPU的排序信息。
     *
     * 该方法用于根据给定的ID和可选的商家ID来更新SPU的排序信息。它首先根据ID和可能的商家ID查询数据，
     * 如果数据不存在，则抛出一个验证异常。然后，它更新产品的信息，并最后调用另一个方法来更新SPU的排序。
     *
     * @param int $id 主键ID，用于定位特定的SPU。
     * @param int $merId 商家ID，可选，用于过滤属于特定商家的SPU。
     * @param array $data 包含要更新的SPU信息的数据数组。
     * @return mixed 返回更新排序后的结果。
     * @throws ValidateException 如果根据给定的ID找不到数据，则抛出此异常。
     */
    public function updateSort(int $id,?int $merId,array $data)
    {
        // 根据主键ID准备查询条件
        $where[$this->dao->getPk()] = $id;
        // 如果提供了商家ID，则添加到查询条件中
        if($merId) $where['mer_id'] = $merId;
        // 根据查询条件获取数据
        $ret = $this->dao->getWhere($where);
        // 如果查询结果为空，则抛出异常
        if(!$ret) throw new  ValidateException('数据不存在');
        // 更新产品的信息
        app()->make(ProductRepository::class)->update($ret['product_id'],$data);
        // 制造SPU仓库实例，用于更新SPU的排序
        $make = app()->make(SpuRepository::class);
        // 更新SPU的排序，并返回结果
        return $make->updateSort($ret['product_id'],$ret[$this->dao->getPk()],4,$data);
    }

    /**
     * 切换商品状态
     *
     * 本函数用于更改指定商品的状态，并触发相应的事件，同时发送通知给相关商户。
     * 主要用于处理商品的上架、下架、审核通过或不通过等状态变更。
     *
     * @param int $id 商品ID
     * @param array $data 包含商品状态的数据
     * @throws ValidateException 如果商品不存在，则抛出验证异常
     */
    public function switchStatus($id, $data)
    {
        // 将$data中的'status'值复制到'product_status'字段，以符合数据表结构
        $data['product_status'] = $data['status'];

        // 通过ID获取商品信息
        $ret = $this->dao->get($id);

        // 如果商品不存在，则抛出异常
        if (!$ret)
           throw new ValidateException('数据不存在');

        // 在状态切换前触发事件，允许其他组件或功能介入
        event('product.groupStatus.before', compact('id', 'data'));

        // 更新商品状态到数据库
        $this->dao->update($id, $data);

        // 状态切换后触发事件，允许其他组件或功能做出相应
        event('product.groupStatus', compact('id', 'data'));

        // 根据新的商品状态，确定通知类型和消息内容
        $type = ProductRepository::NOTIC_MSG[$data['status']][4];
        $message = '您有1个拼团'. ProductRepository::NOTIC_MSG[$data['status']]['msg'];

        // 发送通知给商户，关于商品状态的变更
        SwooleTaskService::merchant('notice', [
            'type' => $type,
            'data' => [
                'title' => $data['status'] == -2 ? '下架提醒' : '审核结果',
                'message' => $message,
                'id' => $id
            ]
        ], $ret->mer_id);

        // 更新SPU的状态，这里的4代表状态的具体值，需要根据实际情况进行解读
        app()->make(SpuRepository::class)->changeStatus($id,4);
    }

}
