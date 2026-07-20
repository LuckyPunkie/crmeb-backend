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

use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\coupon\StoreCouponProductRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\parameter\ParameterValueRepository;
use app\common\repositories\store\pionts\PointsProductRepository;
use app\common\repositories\store\StoreActivityRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\SendSmsJob;
use crmeb\jobs\SyncProductTopJob;
use crmeb\services\CopyCommand;
use crmeb\services\RedisCacheService;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;
use app\common\repositories\BaseRepository;
use app\common\dao\store\product\SpuDao;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use app\common\repositories\user\UserVisitRepository;
use think\facade\Queue;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\merchant\StoreGroupRepository;
use app\common\repositories\system\RelevanceRepository;

class SpuRepository extends BaseRepository
{
    public $dao;
    public $merchantFiled = 'mer_id,mer_name,mer_avatar,is_trader,mer_info,mer_keyword,type_id,long,lat';
    public $productFiled = 'P.product_id,cate_hot,S.store_name,S.image,activity_id,S.keyword,S.price,S.mer_id,spu_id,S.status,store_info,brand_id,cate_id,unit_name,S.star,S.rank,S.sort,sales,S.product_type,rate,reply_count,extension_type,S.sys_labels,S.mer_labels,P.delivery_way,P.delivery_free,S.ot_price,svip_price_type,stock,mer_svip_status,P.active_id,mer_form_id,P.activity_label_ids,P.delivery_free,P.give_coupon_ids,P.delivery_way';

    public function __construct(SpuDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建新的活动参与记录
     *
     * 本函数用于根据提供的参数数组、产品ID、活动ID和可选的产品类型，创建一个新的活动参与记录。
     * 它首先通过调用setparam方法来处理和组合参数，然后调用DAO层的方法来实际执行创建操作。
     *
     * @param array $param 包含参与活动所需信息的参数数组
     * @param int $productId 参与活动的产品ID
     * @param int $activityId 用户参与的活动ID
     * @param int $productType 产品的类型（可选，默认为0），用于区分不同类型的產品
     * @return mixed 返回DAO层创建操作的结果，可能是新记录的ID或其他标识符
     */
    public function create(array $param, int $productId, int $activityId, $productType = 0)
    {
        // 处理和组合参数，准备创建新的活动参与记录
        $data = $this->setparam($param, $productId, $activityId, $productType);
        // 调用DAO层的create方法，实际创建活动参与记录
        return $this->dao->create($data);
    }

    /**
     * 根据给定的参数更新基础数据。
     * 如果指定的产品在活动中不存在，则插入新的数据；否则，更新现有数据。
     *
     * @param array $param 更新或插入的数据参数
     * @param int $productId 产品ID
     * @param int $activityId 活动ID
     * @param int $productType 产品类型，默认为0
     * @return bool 更新或插入操作的执行结果
     */
    public function baseUpdate(array $param, int $productId, int $activityId, $productType = 0)
    {
        /* 构建查询条件 */
        $where = [
            'product_id' => $productId,
            'activity_id' => $activityId,
            'product_type' => $productType,
        ];

        /* 根据条件查询现有数据 */
        $ret = $this->dao->getSearch($where)->find();

        /* 如果数据不存在，则插入新数据 */
        if (!$ret) {
            return $this->create($param, $productId, $activityId, $productType);
        } else {
            /* 设置更新数据的参数 */
            $data = $this->setparam($param, $productId, $activityId, $productType);

            /* 处理标签数据，确保其以逗号分隔的字符串格式 */
            $value = $data['mer_labels'];
            if (!empty($value)) {
                if (!is_array($value)) {
                    $data['mer_labels'] = ',' . $value . ',';
                } else {
                    $data['mer_labels'] = ',' . implode(',', $value) . ',';
                }
            }else{
                $data['mer_labels'] = '';
            }
            /* 更新数据 */
            return $this->dao->update($ret->spu_id, $data);
        }
    }


    /**
     * 设置产品参数
     *
     * 本函数用于根据传入的参数数组和其他单独指定的参数，构造一个包含产品详细信息的数组。
     * 这些信息可用于产品搜索、过滤或存储。特别是，它处理了不同产品类型的特定逻辑。
     *
     * @param array $param 一个包含产品属性的数组，如商店名称、关键词、图片等。
     * @param int $productId 产品的ID，用于标识特定产品。
     * @param int $activityId 活动的ID，产品可能参与的活动的标识。
     * @param int $productType 产品的类型，用于区分不同种类的产品，如积分产品。
     * @return array 返回一个包含所有设置参数的数组，准备用于进一步处理或存储。
     */
    public function setparam(array $param, $productId, $activityId, $productType)
    {
        // 初始化数据数组，包含产品的主要属性
        $data = [
            'product_id' => $productId, // 产品的ID
            'product_type' => $productType ?? 0, // 产品的类型，如果没有指定，默认为0
            'activity_id' => $activityId, // 活动的ID
            'store_name' => $param['store_name'], // 商店的名称
            'keyword' => $param['keyword'] ?? '', // 产品的关键词，如果没有指定，则为空字符串
            'image' => $param['image'], // 产品的图片链接
            'ot_price' => $param['ot_price'] ?? 0, // 原价，如果没有指定，则为0
            'price' => $param['price'], // 产品的价格
            'status' => $productType == PointsProductRepository::PRODUCT_TYPE_POINTS ? 1 : $param['status'],// 根据产品类型设置产品的状态，积分产品为1，其他为0
            'rank' => $param['rank'] ?? 0, // 产品的排名，如果没有指定，则为0
            'temp_id' => $param['temp_id'], // 模板的ID
            'sort' => $param['sort'] ?? 0, // 产品的排序值，如果没有指定，则为0
            'mer_labels' => $param['mer_labels'] ?? '', // 商家标签，如果没有指定，则为空字符串
        ];

        // 如果参数数组中包含mer_id，将其添加到数据数组中
        if (isset($param['mer_id'])) {
            $data['mer_id'] = $param['mer_id'];
        }

        // 返回构造好的数据数组
        return $data;
    }

    /**
     * 修改排序
     * @param $productId
     * @param $activityId
     * @param $productType
     * @param $data
     * @author Qinii
     * @day 1/19/21
     */
    public function updateSort($productId, $activityId, $productType, $data)
    {
        $where = [
            'product_id' => $productId,
            'activity_id' => $activityId,
            'product_type' => $productType,
        ];
        $ret = $this->dao->getSearch($where)->find();
        if ($ret) $this->dao->update($ret['spu_id'], $data);
    }

    protected function getMerIdsByRegin($regionId)
    {
        // 获取区域下的所有门店分组ID
        $stroeGroupIds = app()->make(StoreGroupRepository::class)
            ->search([
                'path' => $regionId,
                'status' => 1,
            ])->column('store_group_id');
        array_push($stroeGroupIds, $regionId);
        // 获取门店分组下关联的店铺id
        $merIds = app()->make(RelevanceRepository::class)->getSearch([])
            ->whereIn('left_id', $stroeGroupIds)
            ->where('type', RelevanceRepository::STORE_GROUP)
            ->column('right_id');
        $merIds = array_unique($merIds);

        return $merIds;
    }

    /**
     * 移动端列表
     * @param $where
     * @param $page
     * @param $limit
     * @param $userInfo
     * @return array
     * @author Qinii
     * @day 12/18/20
     */
    public function getApiSearch($where, $page, $limit, $userInfo = null, $recommend_location = true)
    {
        // 筛选商圈商家ID列表
        if(isset($where['region_id']) && !empty($where['region_id'])) {
            $merIds = $this->getMerIdsByRegin($where['region_id']);
            if (!$merIds) {
                return [];
            }
            $where['mer_ids'] = $merIds;
            unset($where['region_id']);
        }

        if (isset($where['keyword']) && !empty($where['keyword'])) {
            if (preg_match('/^(\/@[1-9]{1}).*\*\//', $where['keyword'])) {
                $command = app()->make(CopyCommand::class)->getMassage($where['keyword']);
                if (!$command || in_array($command['type'], [30, 40])) return ['count' => 0, 'list' => []];
                if ($userInfo && $command['uid']) app()->make(UserRepository::class)->bindSpread($userInfo, $command['uid']);
                $where['spu_id'] = $command['id'];
                unset($where['keyword']);
            } else {
                app()->make(UserVisitRepository::class)->searchProduct($userInfo ? $userInfo['uid'] : 0, $where['keyword'], (int)($where['mer_id'] ?? 0));
            }
        }
        $where['spu_status'] = 1;
        $where['mer_status'] = 1;
        $where['is_show'] = 1;
        $where['not_type'] = [20];
        $query = $this->dao->search($where);
        $query->with([
            'merchant' => function ($query) {
                $query->field($this->merchantFiled)->with(['type_name']);
            },
            'issetCoupon',
            'product',
            'product.reservation'
        ]);
        $count = $query->count();

        $list = $query->page((int)$page, (int)$limit)->setOption('field', [])->field($this->productFiled)->select();
        $append = ['stop_time', 'svip_price', 'show_svip_info', 'is_svip_price'];
        $list = $list->append($append)->toArray();
        foreach ($list as &$item) {
            $activityLabels = $item['product']['activity_labels'];
            foreach ($activityLabels as $key => $val) {
                if (!$val['is_show']) unset($activityLabels[$key]);
            }
            $item['product']['activity_labels'] = array_values($activityLabels);
        }
        $list = app()->make(StoreActivityRepository::class)->getPic($list, StoreActivityRepository::ACTIVITY_TYPE_BORDER);
        // 计算距离
        if(
            (isset($where['latitude']) && !empty($where['latitude'])) &&
            (isset($where['longitude']) && !empty($where['longitude'])) &&
            $recommend_location
        )
        {
            foreach ($list as &$item) {
                [$item['distance'], $item['distanceM']] = $this->distance($where, $item['merchant']['lat'], $item['merchant']['long']);
            }
            if($where['order'] == 'distance_asc') {
                // 距离排序
                usort($list, function($a, $b) {
                    return ($a['distanceM'] ?? 0) <=> ($b['distanceM'] ?? 0);
                });
            }
            if($where['order'] == 'distance_desc') {
                // 距离排序
                usort($list, function($a, $b) {
                    return ($b['distanceM'] ?? 0) <=> ($a['distanceM'] ?? 0);
                });
            }
        }

        $list = $this->spuCart($userInfo, getThumbWaterImage($list, ['image'], 'mid'));
        return compact('count', 'list');
    }

    protected function spuCart($userInfo, array $list)
    {
        if (!$userInfo || empty($list)) {
            return $list;
        }
        $cartData = app()->make(StoreCartRepository::class)->getList($userInfo);
        // 使用array_column构建商品ID到购物车数据的映射
        $productCarts = [];
        foreach ($cartData['list'] as $item) {
            $productCarts += array_column($item['list'], null, 'product_id');
        }

        // 一次性更新所有商品的购物车信息
        array_walk($list, function(&$item) use ($productCarts) {
            $item['cart'] = $productCarts[$item['product_id']] ?? [];
        });

        return $list;
    }

    /**
     * 计算距离
     *
     * @param array $params
     * @param string $merLat
     * @param string $merLong
     * @return void
     */
    public function distance(array $params, string $merLat, string $merLong)
    {
        if (!$merLat || !$merLong) {
            return false;
        }
        $distance = getDistance($params['latitude'], $params['longitude'], $merLat, $merLong);
        $distanceM = $distance;
        if ($distance < 0.9) {
            $distance = max(bcmul($distance, 1000, 0), 1).'m';
            if ($distance == '1m') {$distance = '100m以内';}
        } else {
            $distance.= 'km';
        }

        return [$distance,$distanceM];
    }
    /**
     * 修改状态
     * @param array $data
     * @author Qinii
     * @day 12/18/20
     */
    public function changeStatus(int $id, int $productType, array $operate_data = [])
    {
        $make = app()->make(ProductRepository::class);
        $status = 1;
        try {
            switch ($productType) {
                case 1:
                    $_make = app()->make(ProductRepository::class);
                    $res = $_make->getSearch([])->where(['product_id' => $id])->find();
                    $where = [
                        'activity_id' => $res['active_id'],
                        'product_id' => $id,
                        'product_type' => $productType,
                    ];
                    break;
                case 2:
                    $_make = app()->make(ProductPresellRepository::class);
                    $res = $_make->getWhere([$_make->getPk() => $id]);

                    $endttime = strtotime($res['end_time']);
                    if ($endttime <= time()) {
                        $status = 0;
                    } else {
                        if (
                            $res['product_status'] !== 1 ||
                            $res['status'] !== 1 ||
                            $res['action_status'] !== 1 ||
                            $res['is_del'] !== 0 ||
                            $res['is_show'] !== 1
                        ) {
                            $status = 0;
                        }
                    }
                    $where = [
                        'activity_id' => $id,
                        'product_id' => $res['product_id'],
                        'product_type' => $productType,
                    ];
                    break;
                case 3:
                    $_make = app()->make(ProductAssistRepository::class);
                    $res = $_make->getWhere([$_make->getPk() => $id]);

                    $endttime = strtotime($res['end_time']);
                    if ($endttime <= time()) {
                        $status = 0;
                    } else {
                        if (
                            $res['product_status'] !== 1 ||
                            $res['status'] !== 1 ||
                            $res['is_show'] !== 1 ||
                            $res['action_status'] !== 1 ||
                            $res['is_del'] !== 0
                        ) {
                            $status = 0;
                        }
                    }

                    $where = [
                        'activity_id' => $id,
                        'product_id' => $res['product_id'],
                        'product_type' => $productType,
                    ];
                    break;
                case 4:
                    $_make = app()->make(ProductGroupRepository::class);
                    $wher = $_make->actionShow();
                    $wher[$_make->getPk()] = $id;

                    $res = $_make->getWhere([$_make->getPk() => $id]);
                    $endttime = strtotime($res['end_time']);
                    if ($endttime <= time()) {
                        $status = 0;
                    } else {
                        if (
                            $res['product_status'] !== 1 ||
                            $res['status'] !== 1 ||
                            $res['is_show'] !== 1 ||
                            $res['action_status'] !== 1 ||
                            $res['is_del'] !== 0
                        ) {
                            $status = 0;
                        }
                    }

                    $where = [
                        'activity_id' => $id,
                        'product_id' => $res['product_id'],
                        'product_type' => $productType,
                    ];
                    break;
                case 0:
                default:
                    $where = [
                        'activity_id' => 0,
                        'product_id' => $id,
                        'product_type' => $productType,
                    ];
                    break;
            }
            if (empty($where)) return;
            $ret = $make->getWhere(['product_id' => $where['product_id'], 'product_type' => $productType]);
            if (
                !$ret ||
                $ret['status'] !== 1 ||
                $ret['mer_status'] !== 1 ||
                $ret['is_del'] ||
                (in_array($productType, [0, 1, 20]) && ($ret['is_show'] !== 1 || $ret['is_used'] !== 1))
            ) {
                $status = 0;
            }
            $result = $this->dao->getSearch($where)->find();
            if (!$result && $ret) {
                $result = $this->create($ret->toArray(), $where['product_id'], $where['activity_id'], $productType);
            }
            if ($result) $this->dao->update($result['spu_id'], ['status' => $status]);
            if ($productType == 0) {
                Queue::push(SyncProductTopJob::class, []);
                if ($status == 1) Queue(SendSmsJob::class, ['tempId' => 'PRODUCT_INCREASE', 'id' => $id]);
                // 记录操作日志
                if (!empty($operate_data)) {
                    $make->addChangeStatusLog($operate_data['field'], $operate_data['status'], $operate_data['admin_info'], $ret);
                }
            }
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
        }
    }

    /**
     * 平台编辑商品同步修改
     * @param int $id
     * @param int $productId
     * @param int $productType
     * @param array $data
     * @author Qinii
     * @day 12/18/20
     */
    public function changRank(int $id, int $productId, int $productType, array $data)
    {
        $where = [
            'product_id' => $productId,
            'product_type' => $productType,
            'activity_id' => $id,
        ];
        $res = $this->dao->getWhere($where);
        if (!$res && $id) $this->changeStatus($id, $productType);
        $res = $this->dao->getWhere($where);
        if ($res) {
            if (isset($data['store_name']) && $data['store_name']) {
                $res->store_name = $data['store_name'];
            }
            $res->rank = $data['rank'];
            $res->star = $data['star'] ?? 1;
            $res->save();
        }
    }

    /**
     * 同步各类商品到spu表
     * @param array|null $productType
     * @author Qinii
     * @day 12/25/20
     */
    public function updateSpu(?array $productType)
    {
        if (!$productType) $productType = [0, 1, 2, 3, 4, 20];
        $_product_make = app()->make(ProductRepository::class);
        foreach ($productType as $value) {
            $_product_make->activitSearch($value)->chunk(100, function ($product) use ($_product_make) {
                $data = $_product_make->commandChangeProductStatus($product->toArray());
                $this->dao->findOrCreateAll($data);
                echo count($data) . '条数据处理成功' . PHP_EOL;
            }, 'P.product_id');
        }
    }

    /**
     * 获取活动商品的一级分类
     * @param $type
     * @return mixed
     * @author Qinii +0
     * @day 1/12/21
     */
    public function getActiveCategory($type)
    {
        $pathArr = $this->dao->getActivecategory($type);
        $path = [];
        foreach ($pathArr as $item) {
            $path[] = explode('/', $item)[1];
        }
        $path = array_unique($path);
        $cat = app()->make(StoreCategoryRepository::class)->getSearch(['ids' => $path])->field('store_category_id,cate_name')->select();
        return $cat;
    }

    /**
     * 根据产品类型获取SPU数据
     *
     * 该方法用于根据给定的产品ID和产品类型，查询并返回相应的SPU数据。
     * 它处理了不同产品类型的查询逻辑，包括普通产品、秒杀产品、预售价产品、辅助销售产品和团购产品。
     * 如果产品类型不匹配或查询出现异常，将抛出异常提示数据不存在。
     *
     * @param int $id 产品ID
     * @param int $productType 产品类型，对应不同的产品子类
     * @param int $merId 商家ID，用于查询特定商家的产品数据（可选）
     * @return array 查询到的SPU数据
     * @throws ValidateException 如果数据不存在或查询出错，则抛出异常
     */
    public function getSpuData($id, $productType, $merId)
    {
        try {
            // 根据产品类型选择不同的查询条件
            switch ($productType) {
                case 0:
                    // 普通产品，直接查询
                    $where = [
                        'activity_id' => 0,
                        'product_id' => $id,
                        'product_type' => $productType,
                    ];
                    break;
                case 1:
                    // 秒杀产品，通过repository查询并构造条件
                    $_make = app()->make(ProductRepository::class);
                    $res = $_make->getSearch([])->where(['product_id' => $id])->find();
                    $where = [
                        'activity_id' => $res['active_id'],
                        'product_id' => $id,
                        'product_type' => $productType,
                    ];
                    break;
                case 2:
                    // 预售价产品
                    $_make = app()->make(ProductPresellRepository::class);
                    $res = $_make->getWhere([$_make->getPk() => $id]);
                    $where = [
                        'activity_id' => $id,
                        'product_id' => $res['product_id'],
                        'product_type' => $productType,
                    ];
                    break;
                case 3:
                    // 辅助销售产品
                    $_make = app()->make(ProductAssistRepository::class);
                    $res = $_make->getWhere([$_make->getPk() => $id]);
                    $where = [
                        'activity_id' => $id,
                        'product_id' => $res['product_id'],
                        'product_type' => $productType,
                    ];
                    break;
                case 4:
                    // 团购产品
                    $_make = app()->make(ProductGroupRepository::class);
                    $where[$_make->getPk()] = $id;
                    $res = $_make->getWhere([$_make->getPk() => $id]);
                    $where = [
                        'activity_id' => $id,
                        'product_id' => $res['product_id'],
                        'product_type' => $productType,
                    ];
                    break;
                default:
                    // 默认情况，处理未知产品类型
                    $where = [
                        'activity_id' => 0,
                        'product_id' => $id,
                        'product_type' => 0,
                    ];
                    break;
            }
            // 如果有商家ID，则加入查询条件
            if ($merId) $where['mer_id'] = $merId;
            // 执行查询
            $result = $this->dao->search($where)->setOption('field', [])->field($this->productFiled)->find();
            // 如果查询结果为空，抛出异常
            if (!$result) throw new ValidateException('spu');
            // 返回查询结果
            return $result;
        } catch (\Exception $e) {
            // 捕获异常并抛出验证异常，附带错误信息
            throw new ValidateException($e->getMessage() . '数据不存在');
        }
    }

    /**
     * 设置产品的标签
     *
     * 本函数用于根据传入的数据为指定产品设置标签。产品标签可以是系统标签或商家标签，
     * 根据数据中是否存在'sys_labels'或'mer_labels'来决定。如果标签存在，则会检查该标签是否已
     * 经被使用；如果标签不存在，则标签字段将为空。最后，更新产品数据并保存。
     *
     * @param int $id 产品ID
     * @param string $productType 产品类型
     * @param array $data 包含产品信息的数据数组，其中可能包含'sys_labels'或'mer_labels'字段
     * @param int $merId 商家ID，用于标识标签的归属。默认为0，表示系统标签。
     */
    public function setLabels($id, $productType, $data, $merId = 0)
    {
        // 根据$data中是否存在'sys_labels'或'mer_labels'，决定使用哪个字段
        $field = isset($data['sys_labels']) ? 'sys_labels' : 'mer_labels';

        // 如果$data中存在标签数据，则检查该标签是否已存在
        if ($data[$field]) {
            app()->make(ProductLabelRepository::class)->checkHas($merId, $data[$field]);
        }

        // 获取产品数据
        $ret = $this->getSpuData($id, $productType, $merId);

        // 设置产品标签字段的值，如果不存在标签数据，则字段值为空字符串
        $value = $data[$field] ? $data[$field] : '';
        $ret->$field = $value;

        // 保存更新后的 product data
        $ret->save();
    }

    /**
     * 批量为商品设置标签
     *
     * 本函数用于批量为一组商品ID设置标签。它首先检查传入的$ids是否为数组，
     * 如果不是，则将其解析为数组。然后遍历每个商品ID，调用setLabels方法为每个商品设置标签。
     * 这种批量处理方式可以提高效率，减少对数据库或API的调用次数。
     *
     * @param mixed $ids 商品ID的集合，可以是数组或者以逗号分隔的字符串
     * @param array $data 标签数据，包含需要设置的标签信息
     * @param int $merId 商家ID，用于标识哪个商家进行的操作
     */
    public function batchLabels($ids, $data, $merId)
    {
        // 检查$ids的类型，如果不是数组，则将其转换为数组
        $ids = is_array($ids) ? $ids : explode(',', $ids);

        // 遍历每个商品ID，调用setLabels方法设置标签
        foreach ($ids as $id) {
            $this->setLabels($id, 0, $data, $merId);
        }
    }


    /**
     * 根据优惠券ID获取API搜索产品信息
     *
     * 本函数主要用于根据用户提供的优惠券ID和其他条件，从数据库中搜索相应的产品信息。
     * 它首先根据优惠券ID查找有效的优惠券，然后根据优惠券的类型和关联产品ID等信息，
     * 组装查询条件，最后调用另一个API搜索产品并返回搜索结果。
     *
     * @param array $where 搜索条件，包括coupon_id和其他可能的条件
     * @param int $page 当前页码
     * @param int $limit 每页显示的数量
     * @param array $userInfo 用户信息，可能用于权限检查或搜索条件的进一步定制
     * @return array 包含搜索结果的产品信息，包括总数和产品列表
     */
    public function getApiSearchByCoupon($where, $page, $limit, $userInfo)
    {
        // 根据优惠券ID查询有效的优惠券信息
        $coupon = app()->make(StoreCouponRepository::class)->search(null, [
            'status' => 1,
            'coupon_id' => $where['coupon_id']
        ])->find();

        // 初始化返回的数据数组，并将查询到的优惠券信息放入其中
        $data['coupon'] = $coupon;

        // 如果找到了优惠券，则根据优惠券类型调整搜索条件
        if ($coupon) {
            // 根据优惠券类型，动态调整搜索产品的条件
            switch ($coupon['type']) {
                case 0:
                    // 适用于指定商家的优惠券，限定搜索的商家ID
                    $where['mer_id'] = $coupon['mer_id'];
                    break;
                case 1:
                case 11:
                case 12:
                    // 适用于指定产品的优惠券，分别通过不同方式处理产品ID
                    $ids = app()->make(StoreCouponProductRepository::class)->search([
                        'coupon_id' => $where['coupon_id']
                    ])->column('product_id');
                    switch ($coupon['type']) {
                        case 1:
                            // 适用于指定产品的优惠券，限定搜索的产品ID
                            $where['product_ids'] = $ids;
                            break;
                        case 11:
                            // 适用于指定产品分类的优惠券，限定搜索的产品分类ID
                            $where['cate_pid'] = $ids;
                            break;
                        case 12:
                            // 适用于指定商家的优惠券，限定搜索的商家ID
                            $where['mer_ids'] = $ids;
                            break;
                    }
                    break;
                case 10:
                    // 适用于全品类优惠券，无需额外搜索条件
                    break;
            }
            // 添加优惠券使用标记和排序方式
            $where['is_coupon'] = 1;
            $where['common'] = 1;
            // 根据优惠券发送类型，考虑是否添加svip条件（此处代码被注释掉）
            // $where['svip'] = ($coupon['send_type'] == StoreCouponRepository::GET_COUPON_TYPE_SVIP) ? 1 : '';

            // 调用API搜索产品，传入调整后的条件
            $product = $this->getApiSearch($where, $page, $limit, $userInfo);
        }

        // 组装返回的数据，包括产品总数和列表
        $data['count'] = $product['count'] ?? 0;
        $data['list'] = $product['list'] ?? [];

        // 返回搜索结果数据数组
        return $data;
    }

    /**
     * 获取热门排名列表
     * 该方法用于根据分类ID从Redis缓存中获取热门排名列表。如果缓存中不存在，则从数据库中查询并缓存结果。
     * 主要用于展示指定分类下的热门商品，提高数据获取速度，提升用户体验。
     *
     * @param int $cateId 分类ID，用于查询指定分类下的热门商品。
     * @param int $limit 返回结果的数量限制，默认为15条。
     * @return array 返回热门商品列表，列表项包含商品的基本信息。
     */
    public function getHotRanking(int $cateId, $limit = 15, $regionId = 0)
    {
        // 实例化Redis缓存服务
        $RedisCacheService = app()->make(RedisCacheService::class);
        // 构建缓存键名前缀，根据环境变量确定队列名称，后接固定字符串和分类ID
        $prefix = env('queue_name', 'merchant') . '_hot_ranking_';
        // 从Redis中获取热门排名ID列表
        $ids = $RedisCacheService->handler()->get($prefix . 'top_' . intval($cateId));
        // 如果ID列表为空，则直接返回空数组
        $ids = $ids ? explode(',', $ids) : [];
        if (!count($ids)) {
            return [];
        }
        // 将字符串ID转换为整数ID
        $ids = array_map('intval', $ids);
        // 定义查询条件，只查询启用状态、正常状态、未删除的商品，并按销售量排序
        $where['mer_status'] = 1;
        $where['status'] = 1;
        $where['is_del'] = 0;
        $where['product_type'] = 0;
        $where['order'] = 'sales';
        $where['is_gift_bag'] = 0;
        $where['spu_ids'] = $ids;
        // 筛选商圈商家ID列表
        if($regionId) {
            $merIds = $this->getMerIdsByRegin($regionId);
            $where['mer_ids'] = $merIds;
        }

        // 执行查询，限制返回结果的数量
        $list = $this->dao->search($where)->setOption('field', [])->field('spu_id,S.image,S.price,S.product_type,P.product_id,P.sales,S.status,S.store_name,P.ot_price,P.cost')->limit($limit)->select();
        // 如果查询结果存在，则转换为数组格式并返回
        if ($list) $list = $list->toArray();
        return $list;
    }

    /**
     * 获取商品列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2022/9/22
     */
    public function makinList($where, $page, $limit)
    {
        $where['spu_status'] = 1;
        $where['mer_status'] = 1;
        $query = $this->dao->search($where);
        $query->with([
            'merchant',
            'issetCoupon',
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->productFiled)->select();
        return compact('count', 'list');
    }

    /**
     * 更新商品价格
     *
     * 通过调用数据访问对象（DAO）来更新指定商家和商品ID对应的商品价格。
     * 此函数封装了与数据库交互的逻辑，使得业务逻辑层不需要直接处理数据库操作细节。
     *
     * @param int $mer_id 商家ID，用于指定要更新价格的商家。
     * @param int $product_id 商品ID，用于指定要更新价格的商品。
     * @param float $price 新的商品价格。
     * @return bool 返回更新操作的结果，通常为TRUE表示更新成功，FALSE表示更新失败。
     */
    public function updatePrice($mer_id, $product_id, $price)
    {
        return $this->dao->updatePrice($mer_id, $product_id, $price);
    }
    /**
     * 获取商品SPU信息
     *
     * @param array $product
     * @param array $append
     * @return array
     */
    public function productSpu(array $product, array $append) : array
    {
        $where = [
            'activity_id' => $product['active_id'],
            'product_id' => $product['product_id'],
            'product_type' => $product['product_type']
        ];
        $spu = $this->getSearch($where)->field('star, mer_labels, sys_labels')->append($append, true)->find()->toArray();

        return array_merge($product, $spu);
    }
}
