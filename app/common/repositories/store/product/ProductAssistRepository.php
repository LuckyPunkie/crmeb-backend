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

use app\common\dao\store\product\ProductAssistDao;
use app\common\model\store\product\ProductLabel;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\services\SwooleTaskService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 商品助力活动
 */
class ProductAssistRepository extends BaseRepository
{
    public function __construct(ProductAssistDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建助力活动
     *
     * 本函数用于处理创建助力活动的逻辑。它首先通过给定的数据创建一个新产品，然后创建一个助力活动，
     * 并设置相关的SKU信息。最后，它触发相关的事件并发送通知，提醒管理员有新的助力商品待审核。
     *
     * @param int $merId 商家ID，用于标识助力活动所属的商家。
     * @param array $data 包含助力活动和产品信息的数据数组。
     *
     * @return void
     */
    public function create(int $merId,array $data)
    {
        // 实例化产品仓库，用于后续的产品创建操作。
        $product_make = app()->make(ProductRepository::class);

        // 初始化产品信息数组，设置产品的基本属性。
        $product = [
            'image' => $data['image'],
            'store_name' => $data['store_name'],
            'store_info' => $data['store_info'],
            'slider_image' => $data['slider_image'],
            'temp_id' => $data['temp_id'],
            'is_show' => 0,
            'product_type' => 3,
            'status'    => 1,
            'old_product_id'    => $data['product_id'],
            'guarantee_template_id'=>$data['guarantee_template_id'],
            'sales' => 0,
            'rate'  => 3,
            'integral_rate' => 0,
            'delivery_way' => $data['delivery_way'],
            'delivery_free' => $data['delivery_free'],
        ];

        // 使用数据库事务来确保操作的原子性。
        Db::transaction(function()use($data,$product_make,$product,$merId){
            // 触发'product.assistCreate.before'事件，允许在创建助力活动之前执行自定义逻辑。
            event('product.assistCreate.before',compact('data'));

            // 复制产品并创建一个新的产品，返回新的产品ID。
            $product_id = $product_make->productCopy($data['product_id'],$product,3);
            // 初始化助力活动信息数组，设置助力活动的基本属性。
            $assist = [
                'start_time' => $data['start_time'],
                'end_time'   => $data['end_time'],
                'status'     => 0,
                'is_show'    => $data['is_show'] ?? 1,
                'product_id' => $product_id,
                'store_name' => $data['store_name'],
                'store_info' => $data['store_info'],
                'pay_count' => $data['pay_count'],
                'mer_id'     => $merId,
                'assist_count' => $data['assist_count'],
                'assist_user_count' => $data['assist_user_count'],
                'product_status' => 0,
            ];

            // 实例化助力活动SKU仓库，用于后续的SKU信息插入操作。
            $sku_make = app()->make(ProductAssistSkuRepository::class);

            // 创建助力活动。
            $productAssist = $this->dao->create($assist);

            // 根据给定的数据和助力活动ID，计算并准备SKU信息数组。
            $sku = $this->sltSku($data,$productAssist->product_assist_id,$data['product_id']);

            // 批量插入SKU信息。
            $sku_make->insertAll($sku);

            // 更新SPU信息，包括价格和商家ID。
            $data['price'] = $sku[0]['assist_price'];
            $data['mer_id'] = $merId;

            // 实例化SPU仓库，用于创建SPU信息。
            app()->make(SpuRepository::class)->create($data,$product_id,$productAssist->product_assist_id,3);

            // 触发'product.assistCreate'事件，允许在创建助力活动之后执行自定义逻辑。
            event('product.assistCreate',compact('productAssist'));

            // 发送管理员通知，提示有新的助力商品待审核。
            SwooleTaskService::admin('notice', [
                'type' => 'new_assist',
                'data' => [
                    'title' => '商品审核',
                    'message' => '您有一个新的助力商品待审核',
                    'id' => $productAssist->product_assist_id
                ]
            ]);
        });
    }

    /**
     *  检测是否每个sku的价格
     * @param array $data
     * @param int $presellType
     * @return array
     * @author Qinii
     * @day 2020-10-12
     */
    public function sltSku(array $data,int $assistId,int $productId)
    {
        $make = app()->make(ProductAttrValueRepository::class);
        $sku = [];
        if(count($data['attrValue']) > 1) throw new ValidateException('助力商品只能选择一个SKU');
        $item = $data['attrValue'][0];

        if(!isset($item['assist_price']))throw new ValidateException('请输入助力价格');
        $skuData = $make->getWhere(['unique' => $item['unique'],'product_id' => $productId]);
        if(!$skuData) throw new ValidateException('SKU不存在');
        if($skuData['stock'] < $item['stock']) throw new ValidateException('限购数量不得大于库存');
        if(bccomp($item['assist_price'],$skuData['price'],2) == 1) throw new ValidateException('助力价格不得大于原价');
        $sku[] = [
            'product_assist_id' => $assistId,
            'product_id' => $productId,
            'unique' => $item['unique'],
            'stock' => $item['stock'],
            'assist_price' => $item['assist_price'],
            'stock_count' => $item['stock'],
        ];

        return $sku;
    }


    /**
     *  商户后台列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2020-10-12
     */
    public function getMerchantList(array $where,int $page,int $limit)
    {
        $query = $this->dao->search($where)
            ->with(['assistSku','product'])
            ->append(['assist_status','all','pay', 'success','us_status','stock_count','stock'])
            //->order('Product.sort DESC,Product.create_time DESC')
        ;
        $count = $query->count();
        $list = $query->page($page,$limit)->setOption('field', [])->field('ProductAssist.*,U.mer_labels')
            ->select()->each(function($item){
                if (!$item->mer_labels) {
                    $item->mer_labels = [];
                } else {
                    $item->mer_labels = explode(',',rtrim(ltrim($item->mer_labels,','),','));
                }
                $item['product']['store_name'] = $item['store_name'];
                $item['product']['store_info'] = $item['store_info'];
                return $item;
            });
        return compact('count','list');
    }

    /**
     *  平台列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2020-10-19
     */
    public function getAdminList(array $where,int $page,int $limit)
    {
        $query = $this->dao->search($where)
            ->append(['assist_status','all','pay', 'success','us_status','star','stock_count','stock'])
            ->with(['product','assistSku','merchant' => function($query){
                $query->field('mer_id,mer_avatar,mer_name,is_trader');
            }]);
        $count = $query->count();
        $list = $query->page($page,$limit)->field('ProductAssist.*,U.star,U.rank,U.sys_labels')->select()
            ->each(function($item){
                if (!$item->sys_labels) {
                    $item->sys_labels = [];
                } else {
                    $item->sys_labels = explode(',',rtrim(ltrim($item->sys_labels,','),','));
                }
                $item['product']['store_name'] = $item['store_name'];
                $item['product']['store_info'] = $item['store_info'];
            });

        return compact('count','list');
    }

    /**
     *  移动端列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2020-10-19
     */
    public function getApiList(array $where, int $page,int $limit)
    {
        $where = array_merge($where,$this->dao->assistShow());
        $query = $this->dao->search($where)->where('ProductAssist.is_del',0)
            ->append(['assist_status','user_count'])
            ->with([
                'assistSku',
                'merchant' => function($query){
                    $query->field('mer_id,mer_avatar,mer_name,is_trader');
                },
                'product' => function($query){
                    $query->with(['merchant','reservation']);
                },
        ]);
        $count = $query->count();
        $list = $query->page($page,$limit)->select();
        return compact('count','list');
    }

    /**
     *  merchant 详情
     * @param int $merId
     * @param int $id
     * @return array
     * @author Qinii
     * @day 2020-10-13
     */
    public function detail(?int $merId,int $id)
    {
        $where[$this->dao->getPk()] = $id;
        $where['is_del'] = 0;
        if($merId)$where['mer_id'] = $merId;
        $data = $this->dao->getWhere($where,'*',
            [
                'product' => ['content','attr','oldAttrValue'],
                'assistSku',
                'merchant'=> function($query){
                    $query->field('mer_id,mer_avatar,mer_name,is_trader');
                }
            ])
            ->append(['assist_status','all','pay','success','us_status'])->toArray();

        if(!$data) throw new ValidateException('数据不存在');
        if(!$data['product']) throw new ValidateException('该商品已不存在');

        $spu_where = ['activity_id' => $id, 'product_type' => 3, 'product_id' => $data['product']['product_id']];
        $spu = app()->make(SpuRepository::class)->getSearch($spu_where)->find();
        $data['star'] = $spu['star'] ?? '';
        $data['mer_labels'] = $spu['mer_labels'] ?? '';

        $sku_make = app()->make(ProductAssistSkuRepository::class);
        $data['product']['delivery_way']  = empty($data['product']['delivery_way']) ? [] : explode(',',$data['product']['delivery_way']);
        foreach ($data['product']['oldAttrValue'] as $key => $item) {
            $sku = explode(',', $item['sku']);
            $item['old_stock'] = $item['stock'];
            $item['assistSku'] = $sku_make->getSearch([$this->dao->getPk() => $id,'unique' => $item['unique']])->find();
            foreach ($sku as $k => $v) {
                $item['value' . $k] = $v;
            }
            $data['product']['attrValue'][$key] = $item;
        }
        foreach ($data['product']['attr'] as $k => $v) {
            $data['product']['attr'][$k] = [
                'value'  => $v['attr_name'],
                'detail' => $v['attr_values']
            ];
        }
        unset($data['product']['oldAttrValue']);

        $data['product']['store_name'] = $data['store_name'];
        $data['product']['store_info'] = $data['store_info'];
        return $data;
    }


    /**
     *  移动端 详情
     * @param int $id
     * @return array|\think\Model|null
     * @author Qinii
     * @day 2020-10-19
     */
    public function apiDetail(int $id)
    {
        $where = $this->dao->assistShow();
        $where[$this->dao->getPk()] = $id;
        $data = $this->dao->search($where)->append(['assist_status'], true)->find();
        if(!$data) {
            app()->make(SpuRepository::class)->changeStatus($id,3);
            throw new ValidateException('商品已下架');
        }
        $make = app()->make(ProductRepository::class);
        $data['product'] = $make->apiProductDetail(['product_id' => $data['product_id']],3,$id);
        $data['product']['store_name'] = $data['store_name'];
        $data['product']['store_info'] = $data['store_info'];
        return $data;
    }


    /**
     *  商户编辑
     * @param int $id
     * @param array $data
     * @author Qinii
     * @day 2020-10-13
     */
    public function edit(int $id,array $data)
    {

        $resultData = [
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'assist_user_count' => $data['assist_user_count'],
            'assist_count' => $data['assist_count'],
            'status' => $data['status'] ,
            'is_show' => $data['is_show'] ?? 1,
            'store_name' => $data['store_name'],
            'pay_count' => $data['pay_count'],
            'store_info' => $data['store_info'],
        ];

        $product = [
            'image' => $data['image'],
            'slider_image' => implode(',', $data['slider_image']),
            'temp_id' => $data['temp_id'],
            'product_type' => 3,
            'guarantee_template_id'=>$data['guarantee_template_id'],
            'delivery_way' => implode(',',$data['delivery_way']),
            'delivery_free' => $data['delivery_free'],
            'sort' => $data['sort'],
        ];
        Db::transaction(function()use($id,$resultData,$product,$data){
            $res = $this->dao->get($id);
            event('product.assistUpdate.before',compact('id','data'));
            $this->dao->update($id,$resultData);

            $sku_make = app()->make(ProductAssistSkuRepository::class);

            $sku = $this->sltSku($data,$id,$res->product->old_product_id);
            $sku_make->clear($id);
            $sku_make->insertAll($sku);

            $product_make = app()->make(ProductRepository::class);
            $product_make->update($res['product_id'],$product);
            $data['price'] = $sku[0]['assist_price'];
            app()->make(SpuRepository::class)->baseUpdate($data,$res['product_id'],$id,3);
            event('product.assistUpdate',compact('id'));
            SwooleTaskService::admin('notice', [
                'type' => 'new_assist',
                'data' => [
                    'title' => '商品审核',
                    'message' => '您有一个新的助力商品待审核',
                    'id' => $id
                ]
            ]);
        });
    }

    /**
     *  删除信息
     * @param array $where
     * @author Qinii
     * @day 2020-10-17
     */
    public function delete(array $where)
    {
        $productAssist = $this->dao->getWhere($where,'*',['product']);
        if(!$productAssist) throw new ValidateException('数据不存在');
        Db::transaction(function()use($productAssist){
            $productAssist->is_del = 1;
            $productAssist->save();
            event('product.assistDelete',compact('productAssist'));
//            queue(ChangeSpuStatusJob::class, ['id' => $productAssist[$this->getPk()], 'product_type' => 3]);
            app()->make(SpuRepository::class)->changeStatus($productAssist[$this->getPk()],3);
        });
    }

    /**
     * 根据ID获取产品辅助信息
     *
     * 本函数通过ID从数据库中检索产品辅助信息及其相关联的SKU信息。
     * 它首先使用ID查询辅助信息，然后根据产品ID获取详细的产品信息。
     * 最后，将辅助信息的ID添加到产品信息中，以便返回一个包含完整辅助信息和产品信息的数组。
     *
     * @param int $id 产品辅助信息的唯一标识ID
     * @return array 返回包含产品辅助信息和产品详细信息的数组
     */
    public function get(int $id)
    {
        // 根据ID查询产品辅助信息及其关联的SKU信息，并转换为数组格式
        $data = $this->dao->getWhere([$this->dao->getPk() => $id],'*',['assistSku.sku'])->toArray();

        // 通过产品ID和产品辅助ID获取详细的产品信息
        $res = app()->make(ProductRepository::class)->getAdminOneProduct($data['product_id'],$id);

        // 将产品辅助ID添加到产品信息中
        $res['product_assist_id'] = $data['product_assist_id'];

        // 返回包含产品辅助信息和产品详细信息的数组
        return $res;
    }

    /**
     * 更新产品信息。
     *
     * 该方法用于根据给定的ID和数据数组更新产品的特定属性。它首先通过ID更新存储名称，
     * 然后获取更新后的对象以修改存储名称并保存。接下来，它调整SPU的排名并更新产品的其他属性。
     *
     * @param int $id 产品的ID，用于定位要更新的产品。
     * @param array $data 包含产品更新数据的数组，其中'star'字段在使用后被删除。
     */
    public function updateProduct(int $id,array $data)
    {
        // 更新数据库中产品的存储名称
        $this->dao->update($id,['store_name' => $data['store_name']]);

        // 获取更新后的对象，以进行进一步的修改和保存
        $res = $this->dao->get($id);
        $res->store_name = $data['store_name'];
        $res->save();

        // 调整SPU的排名，并传入相关参数
        app()->make(SpuRepository::class)->changRank($id,$res['product_id'],3,$data);

        // 删除数据数组中的'star'字段，因为它不再需要
        unset($data['star']);

        // 更新产品的其他属性，不包括'star'
        app()->make(ProductRepository::class)->adminUpdate($res['product_id'],$data);
    }


    /**
     * 检查助力活动的有效性
     *
     * 本函数用于在用户参与助力活动前，验证活动的有效性。它通过检查活动的状态、库存、购买次数限制等条件，确保用户可以合法地参与活动。
     * 参数 $id 代表助力活动的唯一标识，$uid 代表用户的唯一标识。
     * 返回活动的相关数据，如果活动无效或不存在，则抛出 ValidateException 异常。
     *
     * @param int $id 助力活动的ID
     * @param int $uid 用户的ID
     * @return array 活动的相关数据
     * @throws ValidateException 如果活动无效或不存在
     */
    public function checkAssist($id,$uid)
    {
        // 获取助力活动的查询条件
        $where = $this->dao->assistShow();
        // 将助力活动ID加入查询条件
        $where[$this->dao->getPk()] = $id;
        // 根据条件查询助力活动信息，并包含关联产品和SKU信息，以及附加购买状态
        $data = $this->dao->search($where)->with(['product','assistSku.sku'])->append(['assist_status'], true)->find();
        // 如果活动信息不存在，抛出异常
        if (!$data) throw new ValidateException('商品已下架或不在活动时间内');
        // 如果活动有限制购买次数，检查用户购买次数是否已达上限
        if($data['pay_count']){
            // 实例化订单仓库类
            $make = app()->make(StoreOrderRepository::class);
            // 构建查询参数
            $arr =  ['exsits_id' => $id,'product_type' => 3];
            // 获取用户已参加活动的次数
            $_counot = $make->getTattendCount($arr,$uid)->count();
            // 如果用户参加次数达到上限，抛出异常
            if($_counot >= $data['pay_count']) throw new ValidateException('您已达到购买次数上限');
        }
        // 如果活动信息不存在，抛出异常（虽然前面已检查，但这里再次强调）
        if(!$data) throw new ValidateException('商品不在活动时间内');
        // 如果活动状态不为开启状态，抛出异常
        if($data['assist_status'] !== 1)
            throw new ValidateException('商品不在活动时间内');
        // 如果活动SKU信息不存在，抛出异常
        if(!isset($data['assistSku'][0]['sku']))
            throw new ValidateException('商品SKU不存在');
        // 如果活动SKU库存不足，抛出异常
        if($data['assistSku'][0]['stock'] < 1 || $data['assistSku'][0]['sku']['stock'] < 1)
            throw new ValidateException('商品库存不足');
        // 返回验证通过的活动数据
        return $data;
    }

    /**
     * 获取用户参与助力的数量统计
     *
     * 本函数通过查询两种不同类型助力活动的用户参与数量，然后将其合并返回。
     * 这两种助力活动分别是：产品助力用户和产品助力设置用户。
     * 返回的数据包含总参与人数和详细的参与者列表。
     *
     * @return array 返回包含助力用户总数和用户列表的数组
     */
    public function getUserCount()
    {
        // 查询产品助力用户数量
        $_data = app()->make(ProductAssistUserRepository::class)->userCount();
        // 查询产品助力设置用户数量
        $_data1 = app()->make(ProductAssistSetRepository::class)->userCount();

        // 合并两种助力用户的数量
        $data['count'] = $_data['count'] + $_data1['count'];
        // 将产品助力用户的列表赋值给最终返回的数据
        $data['list'] = $_data['list'];

        return $data;
    }


    /**
     *  助力商品加入购物车检测
     * @param array $data
     * @param $userInfo
     * @author Qinii
     * @day 2020-10-21
     */
    public function cartCheck(array $data,$userInfo)
    {
        /**
         * 1 查询出商品信息；
         * 2 商品是否存在
         * 3 购买是否超过限制
         * 4 库存检测
         */
        if(!$data['is_new']) throw new ValidateException('助力商品不可加入购物车');

        $where = $this->dao->assistShow();
        $where[$this->dao->getPk()] = $data['product_id'];
        $result = $this->dao->search($where)->with('product')->find();
        if (!$result) throw new ValidateException('商品已下架');

        if($result['pay_count'] !== 0){
            $make = app()->make(StoreOrderRepository::class);
            $tattend = [
                'activity_id' => $data['product_id'],
                'product_type' => 3,
            ];
            $count = $make->getTattendCount($tattend,$userInfo->uid)->count();
            if ($count >= $result['pay_count']) throw new ValidateException('您的本次活动购买数量上限');
        }

        $sku_make = app()->make(ProductAssistSkuRepository::class);
        $_where = ['unique' => $data['product_attr_unique'], $this->dao->getPk() => $data['product_id']];
        $presellSku = $sku_make->getWhere($_where,'*',['sku']);

        if(($presellSku['stock'] < $data['cart_num']) || ($presellSku['sku']['stock'] < $data['cart_num']))
            throw new ValidateException('库存不足');
        $product = $result['product'];
        $sku = $presellSku['sku'];
        $cart = null;
        return compact('product','sku','cart');
    }

    /**
     * 更新SPU的排序信息。
     *
     * 该方法用于根据给定的ID和可选的商家ID来更新SPU的排序信息。它首先根据ID和可能的商家ID查询数据，
     * 如果数据不存在，则抛出一个验证异常。然后，它更新产品的信息，并最后调用另一个方法来更新SPU的排序。
     *
     * @param int $id 主键ID，用于定位特定的SPU。
     * @param int $merId 商家ID，可选，用于过滤属于特定商家的SPU。
     * @param array $data 包含要更新的SPU排序信息的数据数组。
     * @return mixed 返回更新排序后的SPU信息。
     * @throws ValidateException 如果根据给定的ID找不到SPU数据，则抛出此异常。
     */
    public function updateSort(int $id,?int $merId,array $data)
    {
        // 根据主键ID准备查询条件
        $where[$this->dao->getPk()] = $id;
        // 如果提供了商家ID，则加入查询条件
        if($merId) $where['mer_id'] = $merId;
        // 根据条件查询SPU信息
        $ret = $this->dao->getWhere($where);
        // 如果查询结果为空，则抛出异常
        if(!$ret) throw new  ValidateException('数据不存在');
        // 更新产品的信息
        app()->make(ProductRepository::class)->update($ret['product_id'],$data);
        // 制造SPU仓库实例，用于更新SPU排序
        $make = app()->make(SpuRepository::class);
        // 更新SPU的排序信息，并返回更新结果
        return $make->updateSort($ret['product_id'],$ret[$this->dao->getPk()],3,$data);
    }

    /**
     * 切换商品状态
     *
     * 本函数用于根据传入的ID和数据切换商品的状态。它首先将传入的数据中的'status'值复制到'product_status'字段，
     * 然后尝试根据ID获取商品数据。如果数据不存在，则抛出一个验证异常。接下来，它触发一个'before'事件来切换商品的辅助状态，
     * 并更新商品数据。之后，它再次触发一个事件来反映状态的切换。最后，根据新的状态发送通知，并调整SPU的状态。
     *
     * @param int $id 商品ID
     * @param array $data 包含商品状态的数据
     * @throws ValidateException 如果根据ID找不到商品数据，则抛出此异常
     */
    public function switchStatus($id, $data)
    {
        // 将状态值复制到product_status字段
        $data['product_status'] = $data['status'];

        // 尝试获取商品数据
        $ret = $this->dao->get($id);

        // 如果数据不存在，则抛出异常
        if (!$ret)
            throw new ValidateException('数据不存在');

        // 触发状态切换前的事件
        event('product.assistStatus.before', compact('id', 'data'));

        // 更新商品数据
        $this->dao->update($id, $data);

        // 触发状态切换后的事件
        event('product.assistStatus', compact('id', 'data'));

        // 根据新的状态确定通知类型
        $type = ProductRepository::NOTIC_MSG[$data['status']][3];

        // 组装通知消息
        $message = '您有1个助力'. ProductRepository::NOTIC_MSG[$data['status']]['msg'];

        // 发送通知给商家
        SwooleTaskService::merchant('notice', [
            'type' => $type,
            'data' => [
                'title' => $data['status'] == -2 ? '下架提醒' : '审核结果',
                'message' => $message,
                'id' => $id
            ]
        ], $ret->mer_id);

        // 调整SPU的状态
        app()->make(SpuRepository::class)->changeStatus($id,3);
    }

}
