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
namespace app\common\repositories\store;

use app\common\dao\store\StoreActivityDao;
use app\common\model\store\StoreActivity;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductLabelRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\RelevanceRepository;
use crmeb\services\QrcodeService;
use Exception;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;


/**
 * @mixin StoreActivityDao
 */
class StoreActivityRepository extends BaseRepository
{
    //氛围图
    const ACTIVITY_TYPE_ATMOSPHERE = 1;
    //活动边框
    const ACTIVITY_TYPE_BORDER = 2;
    //报名活动
    const ACTIVITY_TYPE_FORM = 4;

    //指定范围类型
    //0全部商品
    const TYPE_ALL = 0;
    //指定商品
    const TYPE_MUST_PRODUCT = 1;
    //指定分类
    const TYPE_MUST_CATEGORY = 2;
    //指定商户
    const TYPE_MUST_STORE = 3;
    //指定商品标签
    const TYPE_MUST_PRODUCT_LABEL = 4;
    //秒杀活动商品
    const TYPE_MUST_SECKILL_ACTIVE = 5;

    public $activeProductType = [1];
    //使用范围对应的类型
    public  $typeData = [
        self::TYPE_ALL => '',
        self::TYPE_MUST_PRODUCT => RelevanceRepository::SCOPE_TYPE_PRODUCT,
        self::TYPE_MUST_CATEGORY => RelevanceRepository::SCOPE_TYPE_CATEGORY,
        self::TYPE_MUST_STORE => RelevanceRepository::SCOPE_TYPE_STORE,
        self::TYPE_MUST_PRODUCT_LABEL => RelevanceRepository::SCOPE_TYPE_PRODUCT_LABEL,
        self::TYPE_MUST_SECKILL_ACTIVE => '',
    ];

    /**
     * @var StoreActivityDao
     */
    protected $dao;

    /**
     * StoreActivityDao constructor.
     * @param StoreActivityDao $dao
     */
    public function __construct(StoreActivityDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取活动范围类型
     *
     * 此方法用于根据传入的类型参数返回相应的活动范围类型。如果没有指定类型参数，
     * 则返回所有活动范围类型的映射数组。这个方法主要用于内部逻辑，以确定活动的范围和类型。
     *
     * @param null $type 活动的类型标识，如果为null，则返回所有类型。
     * @return array|mixed 如果$type有值，返回对应的活动范围类型；否则返回所有类型的整体映射。
     */
    public function getActiveScopeType($type = null)
    {
        // 定义活动范围类型的映射
        $data = [
            1 => self::TYPE_MUST_SECKILL_ACTIVE,
        ];
        // 根据$type是否有值，返回相应的结果
        return $type ? $data[$type] : $data;
    }


    /**
     * 获取活动列表
     *
     * 根据给定的条件和分页信息，从数据库中检索活动列表。此方法主要用于处理数据的查询和分页逻辑。
     *
     * @param string $where 查询条件，用于筛选活动。这是一个SQL WHERE子句的字符串表示。
     * @param int $page 当前页码，用于指定要返回的页码。
     * @param int $limit 每页的记录数，用于指定每页返回的活动数量。
     * @return array 返回一个包含两个元素的数组，'count'表示活动的总数量，'list'表示当前页的活动列表。
     */
    public function getList($where, $page, $limit)
    {
        // 构建查询语句，根据$where条件搜索，并附加'time_status'字段
        $query = $this->dao->search($where)->append(['time_status'], true);

        // 计算满足条件的活动总数
        $count = $query->count();

        // 对查询结果进行分页，根据'sort'和'activity_id'排序，并返回当前页的活动列表
        $list = $query->page($page, $limit)->order('sort DESC,activity_id DESC')->select();
        // 初始化三个数组，分别用于存放未开始、正在进行和已结束的活动列表
        $noStart = []; // 未开始的活动列表
        $starting = []; // 正在进行的活动列表
        $ended = []; // 已结束的活动列表
        $dateTime = date('Y-m-d H:i:s');
        foreach($list as $item) {
            if($dateTime < $item['start_time']) {
                $noStart[] = $item;
            }
            if($dateTime > $item['start_time'] && $dateTime < $item['end_time']) {
                $starting[] = $item;
            }
            if($dateTime > $item['end_time']) {
                $ended[] = $item;
            }
        }
        // 将活动列表按照时间顺序重新排序（正在进行的活动在前，未开始和已结束的在后）
        $list = array_merge($starting, $noStart, $ended);

        // 将活动总数和列表一起返回
        return compact('count', 'list');
    }


    /**
     * 创建活动商品 的活动关联氛围图等
     * @param $data 主要信息
     * @param $type 商品类型
     * @return mixed
     * @author Qinii
     * @day 2024/4/15
     */
    public function saveByType(array $data, int $type)
    {
        $createData = [
            'activity_name' => $data['activity_name'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'pic' => $data['pic'],
            'link_id' => $data['link_id'],
            'activity_type' => $data['activity_type'],
        ];
        if (!in_array($data['activity_type'], [self::ACTIVITY_TYPE_ATMOSPHERE,self::ACTIVITY_TYPE_BORDER]))
            throw new ValidateException('活动类型错误');
        if (!in_array($type, $this->activeProductType))
            throw new ValidateException('请选择指定商品');
        if (count(array_filter($createData ?? [])) < 6)
            throw new ValidateException('缺少必传参数');
        $scope_type = $this->getActiveScopeType($type);
        $res = $this->getSearch(['activity_type' => $createData['activity_type']])
            ->where(['scope_type' => $scope_type, 'link_id' => $createData['link_id'],])->find();

        try {
            if ($res) {
                $this->dao->update($res->activity_id,$createData);
            } else {
                $createData['scope_type'] = $scope_type;
                $createData['status'] = strtotime($createData['start_time']) <= time() ? 1 : 0;
                $createData['sort'] = 999;
                $createData['is_display'] = 0;
                $createData['is_show'] = 1;
                $this->createActivity($createData);
            }
            return true;
        }catch (Exception $e) {
            throw new ValidateException($e->getMessage());
        }
    }

    /**
     *  创建活动
     * @param data['activity_name'] 名称
     * @param data['start_time'] 开始时间
     * @param data['end_time'] 结束时间
     * @param data['pic'] 活动图片
     * @param data['is_show'] 是否显示
     * @param data['status'] 是否在活动中
     * @param data['sort'] 排序
     * @param data['activity_type'] 活动类型
     * @param data['is_display'] 是否展示活动
     * @param data['link_id'] 关联ID
     * @param array $data
     * @param $extend
     * @param $func
     * @author Qinii
     * @day 2023/10/13
     */
    public function createActivity(array $data, $extend = null, $func = null)
    {
        $paramsData =  $extend ? $this->getParams($data, $extend) : [];
        return Db::transaction(function () use ($data, $extend, $func, $paramsData) {
            $createData = $this->dao->create($data);
            if (isset($paramsData['ids']) && !empty($paramsData['ids']))
                app()->make(RelevanceRepository::class)->createMany($createData->activity_id, $paramsData['ids'], $paramsData['type']);
            if ($func && function_exists($func)) $this->$func($createData, $extend);
        });
    }

    /**
     * 整理关联参数
     * @param $data
     * @param $extend
     * @return array
     * @author Qinii
     * @day 2023/10/13
     */
    public function getParams($data, $extend)
    {
        if (!$extend) return [];
        $res = [];
        $type = '';
        switch ($data['scope_type']) {
            case self::TYPE_ALL;
                break;
            case self::TYPE_MUST_PRODUCT:
                if (!isset($extend['spu_ids']) || empty($extend['spu_ids']))
                    throw new ValidateException('请选择指定商品');
                $res = app()->make(SpuRepository::class)->getSearch(['spu_ids' => $extend['spu_ids'], 'status' => 1])->column('spu_id');
                $type = RelevanceRepository::SCOPE_TYPE_PRODUCT;
                break;
            case self::TYPE_MUST_CATEGORY:
                if (!isset($extend['cate_ids']) || empty($extend['cate_ids']))
                    throw new ValidateException('请选择指定商品分类');
                $res = app()->make(StoreCategoryRepository::class)->getSearch(['ids' => $extend['cate_ids'], 'status' => 1])->column('store_category_id');
                $type = RelevanceRepository::SCOPE_TYPE_CATEGORY;
                break;
            case self::TYPE_MUST_STORE:
                if (!isset($extend['mer_ids']) || empty($extend['mer_ids']))
                    throw new ValidateException('请选择指定商户');
                $res = app()->make(MerchantRepository::class)->getSearch(['mer_ids' => $extend['mer_ids']])->column('mer_id');
                $type = RelevanceRepository::SCOPE_TYPE_STORE;
                break;
            case self::TYPE_MUST_PRODUCT_LABEL:
                if (!isset($extend['label_ids']) || empty($extend['label_ids']))
                    throw new ValidateException('请选择指定商品标签');
                $res = app()->make(ProductLabelRepository::class)->getSearch(['product_label_id' => $extend['label_ids']])->column('product_label_id');
                $type = RelevanceRepository::SCOPE_TYPE_PRODUCT_LABEL;
                break;
            default:
                throw new ValidateException('缺少活动类型');
                break;
        }
        $ids = array_unique($res);
        return compact('ids', 'type');
    }

    /**
     * 更新活动信息，并处理相关的扩展数据。
     *
     * 本函数用于更新指定ID的活动数据，并根据传入的扩展数据进行相关的关联操作。
     * 如有需要，还可以执行额外的自定义函数。
     *
     * @param int $id 活动的唯一标识ID。
     * @param array $data 需要更新的活动数据数组。
     * @param mixed $extend 扩展数据，可以是数组或者null，用于更新关联数据。
     * @param callable|null $func 自定义函数，如果传入则会在更新完成后调用。
     */
    public function updateActivity(int $id, array $data, $extend = null, $func = null)
    {
        // 获取参数数据，包括直接传入的数据和扩展数据。
        $paramsData = $this->getParams($data, $extend);

        // 使用数据库事务来确保更新操作的原子性。
        Db::transaction(function () use ($id, $data, $extend, $func, $paramsData) {
            // 清除旧的关联数据。
            $info = $this->dao->getSearch([$this->dao->getPk() => $id])->find()->toArray();
            app()->make(RelevanceRepository::class)->clear($id, $this->typeData[$info['scope_type']], 'left_id');
            // 更新活动的基本信息。
            $createData = $this->dao->update($id, $data);

            // 如果有扩展参数数据，则处理关联数据。
            if (!empty($paramsData)) {
                // 清除旧的关联数据。
                app()->make(RelevanceRepository::class)->clear($id, $paramsData['type'], 'left_id');

                // 根据新的关联ID创建新的关联数据。
                if (isset($paramsData['ids']) && !empty($paramsData['ids'])) {
                    app()->make(RelevanceRepository::class)->createMany($id, $paramsData['ids'], $paramsData['type']);
                }
            }

            // 如果传入了自定义函数，并且该函数存在，则调用它。
            if ($func && function_exists($func)) {
                $this->$func($createData, $extend);
            }
        });
    }

    /**
     * 详情
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2022/9/17
     */
    public function getAdminList($where, $page, $limit, array $with = [])
    {
        $where['is_display'] = 1;
        $query = $this->dao->search($where, $with)->order('sort DESC,activity_id DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->append(['time_status']);
        return compact('count', 'list');
    }

    /**
     * 详情
     * @param $id
     * @return array
     * @author Qinii
     * @day 2022/9/16
     */
    public function detail($id, $type = true)
    {
        $with = [];
        if ($type) $with[] = 'socpeData';
        $data = $this->dao->getSearch([$this->dao->getPk() => $id])->with($with)->append(['time_status'], true)->find()->toArray();
        if ($type) {
            try {
                $arr = array_column($data['socpeData'], 'right_id');
                if ($data['scope_type'] == self::TYPE_MUST_CATEGORY) {
                    $data['cate_ids'] = $arr;
                } else if ($data['scope_type'] == self::TYPE_MUST_STORE) {
                    $data['mer_ids'] = $arr;
                } else if ($data['scope_type'] == self::TYPE_MUST_PRODUCT_LABEL) {
                    $data['label_ids'] = $arr;
                } else {
                    $data['spu_ids'] = $arr;
                }
            } catch (Exception $e) {
            }
            unset($data['socpeData']);
        }
        return $data;
    }

    /**
     * 删除活动
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2022/9/17
     */
    public function deleteActivity($id)
    {
        return Db::transaction(function () use ($id) {
            $this->dao->delete($id);
            app()->make(RelevanceRepository::class)->clear($id, [RelevanceRepository::SCOPE_TYPE_PRODUCT, RelevanceRepository::SCOPE_TYPE_STORE, RelevanceRepository::SCOPE_TYPE_CATEGORY], 'left_id');
            Cache::tag('get_product')->clear();
        });
    }

    /**
     *  秒杀活动删除后删除边框
     * @param $linkId
     * @param $scope_type
     * @param $activity_type
     * @author Qinii
     * @day 2024/5/10
     */
    public function deleteSeckll(int $linkId,  $activity_type = 2,$type = 1)
    {
        $scope_type = $this->getActiveScopeType($type);
        $argc = $this->dao->search(['activity_type' => $activity_type])
            ->where('link_id',$linkId)
            ->where('scope_type',$scope_type)
            ->find();
        if ($argc) $this->deleteActivity($argc->activity_id);
        return ;
    }
    /**
     *  获取需要的商品 或 商品列表 所需要的活动图
     * @param array $data
     * @param int $type
     * @return array
     * @author Qinii
     * @day 2024/4/16
     */
    public function getPic(array $data, int $type, $push_key = 'border_pic')
    {
        if (!$data) return [];
        $where = [
            'activity_type' => $type,
            'status' => 1,
            'is_show' => 1,
            'gt_end_time' => date('Y-m-d H:i:s', time()),
            'is_del' => 0
        ];
        $activeData = $this->dao->getSearch($where)
            ->setOption('field', [])
            ->field('activity_id,scope_type,activity_type,pic')
            ->order('scope_type DESC,sort DESC,create_time DESC')->limit(10)->select()->toArray();

        $onList = $data;
        $list = $data;
        if ($onList) {
            foreach ($activeData as $active) {
                if (!$onList) break;
                switch ($active['scope_type']) {
                    case self::TYPE_ALL:
                        $newList = array_map(function ($item) use ($active, $push_key) {
                            $item[$push_key] = $active['pic'];
                            return $item;
                        }, $list);
                        $list = $newList;
                        $onList = [];
                        return $list;
                        break;
                    case self::TYPE_MUST_PRODUCT:
                        $field = 'spu_id';
                        break;
                    case self::TYPE_MUST_CATEGORY:
                        $field = 'cate_id';
                        break;
                    case self::TYPE_MUST_STORE:
                        $field = 'mer_id';
                        break;
                    case self::TYPE_MUST_PRODUCT_LABEL:
                        $newList = array_map(function ($item) use ($active, $push_key) {
                            $labelIds = $item['sys_labels'];

                            $_type = $this->typeData[$active['scope_type']];
                            $params = ['type' => $_type, 'left_id' => $active['activity_id']];
                            $rightIds = app()->make(RelevanceRepository::class)->getSearch($params)->whereIn('right_id', $labelIds)->select()->toArray();

                            $rightIds = array_column($rightIds,'right_id');
                            $intersectId = array_values(array_intersect($labelIds, $rightIds));
                            if($intersectId) {
                                $item[$push_key] = $active['pic'];
                            }
                            return $item;
                        }, $list);

                        $list = $newList;
                        $onList = [];
                        return $list;
                        break;
                    case self::TYPE_MUST_SECKILL_ACTIVE:
                        $field = 'active_id';
                        break;
                }
                $this->activeProductAndList($list, $onList, $active, $field, $push_key);
            }
        }
        return $list;
    }

    /**
     *  根据当前的数组 ， 获取所有活动是否存在符合的图片活动
     * @param $active
     * @param $field
     * @param $list
     * @param $onList
     * @author Qinii
     * @day 2024/4/16
     */
    public function activeProductAndList(&$list, &$onList,$active,$field,$push_key)
    {
        $_type = $this->typeData[$active['scope_type']];
        //获得所有商品的spuID
        $ids = array_column($onList, $field);
        $idss = array_unique($ids);
        if (count($idss) == 1 && !$idss[0]) return ;
        //以需求的字段为主键的数组
        $funList = [];
        if ($ids && $onList) $funList = array_combine($ids, $onList);
        if ($field == 'active_id') {
            //获取交集ID
            $intersectId = $ids;
        } else {
            $relevanceRepository = app()->make(RelevanceRepository::class);
            $params = ['type' => $_type, 'left_id' => $active['activity_id']];
            $rightIds = $relevanceRepository->getSearch($params)->whereIn('right_id', $ids)->select()->toArray();

            $rightIds = array_column($rightIds,'right_id');
            $intersectId = array_values(array_intersect($ids, $rightIds));
        }
        //存在交集则有符合条件的商品
        if (!empty($intersectId)) {
            $newList = array_map(function ($item) use ($intersectId, $active, $field, $push_key) {
                if (
                    ($field !== 'active_id' || $item['active_id'] !== 0) &&
                    in_array($item[$field], $intersectId) && !isset($item[$push_key])
                ) {
                    $item[$push_key] = $active['pic'];
                }
                return $item;
            }, $list);
            foreach ($intersectId as $spu_id) {
                unset($funList[$spu_id]);
            }
            $list = $newList;
            $onList = array_values($funList);
        }
    }


    /**
     *  弃用
     * @param int $type
     * @param $spuId
     * @param $cateId
     * @param $merId
     * @param $labelId
     * @return array|mixed
     * @author Qinii
     * @day 2024/5/10
     */
    public function getActivityBySpu(int $type, $spuId, $cateId, $merId, $labelId)
    {
        $make = app()->make(RelevanceRepository::class);
        $list = $this->dao->getSearch(['activity_type' => $type, 'status' => 1, 'is_show' => 1, 'gt_end_time' => date('Y-m-d H:i:s', time())])
            ->setOption('field', [])
            ->field('activity_id,scope_type,activity_type,pic')
            ->order('sort DESC,create_time DESC')
            ->select()
            ->toArray();
        foreach ($list as $item) {
            switch ($item['scope_type']) {
                case self::TYPE_ALL:
                    return $item;
                case self::TYPE_MUST_PRODUCT:
                    $_type = RelevanceRepository::SCOPE_TYPE_PRODUCT;
                    $right_id = $spuId ?: 0;
                    break;
                case self::TYPE_MUST_CATEGORY:
                    $_type = RelevanceRepository::SCOPE_TYPE_CATEGORY;
                    $right_id = $cateId ?: 0;
                    break;
                case self::TYPE_MUST_STORE:
                    $_type = RelevanceRepository::SCOPE_TYPE_STORE;
                    $right_id = $merId ?: 0;
                    break;
                case self::TYPE_MUST_PRODUCT_LABEL:
                    $_type = RelevanceRepository::SCOPE_TYPE_PRODUCT_LABEL;
                    $right_id = $labelId ?: '';
                    break;
            }
            if (isset($_type)) {
                $res = $make->checkHas($item['activity_id'], $right_id, $_type);
                if ($res) return $item;
            }
        }
        return [];
    }

    /**
     * 生成微信活动二维码
     *
     * 该方法用于生成与特定微信活动和用户关联的二维码。二维码的内容包含了活动的ID和用户的ID，
     * 用于在微信小程序中识别和引导用户参加特定活动。
     *
     * @param int $uid 用户ID，用于生成唯一标识并关联到二维码
     * @param StoreActivity $activity 活动对象，包含活动的相关信息，如活动ID
     * @return string 返回生成的微信活动二维码的路径
     */
    public function wxQrcode(int $uid, StoreActivity $activity)
    {
        // 生成二维码文件名，基于用户ID、活动ID和当前日期，确保唯一性
        $name = md5('wxactform_i' . $uid . $activity['activity_id'] . date('Ymd')) . '.jpg';

        // 构建二维码的key值，用于存储和检索
        $key = 'form_' . $activity['activity_id'] . '_' . $uid;

        // 调用二维码服务类，生成微信小程序二维码，并返回二维码的路径
        // 参数包括二维码文件名、二维码指向的URL、是否使用代理以及存储的key
        return app()->make(QrcodeService::class)->getWechatQrcodePath($name, '/pages/activity/registrate_activity/index?id=' . $activity['activity_id'] . '&spid=' . $uid, false, $key);
    }

    /**
     * 生成微页面二维码
     *
     * 该方法用于生成特定活动的微页面二维码，供用户扫码报名参加活动。二维码的生成基于用户的UID和活动ID，
     * 通过MD5加密生成唯一的名字，确保每个用户的活动二维码都是唯一的。
     *
     * @param int $uid 用户ID，用于生成唯一二维码名称的一部分。
     * @param StoreActivity $activity 活动对象，包含活动相关信息，如活动ID，用于生成二维码链接。
     * @return string 返回生成的二维码路径，供后续展示或下载使用。
     */
    public function mpQrcode(int $uid, StoreActivity $activity)
    {
        // 生成二维码文件名，包含UID、活动ID和当前日期，确保唯一性，并指定文件类型为jpg。
        $name = md5('mpactform_i' . $uid . $activity['activity_id'] . date('Ymd')) . '.jpg';

        // 调用二维码服务类，生成小程序二维码，指定二维码路径、目标页面和参数。
        // 这里的参数用于在打开小程序页面时传递活动ID和用户ID，用于识别和绑定用户活动关系。
        return app()->make(QrcodeService::class)->getRoutineQrcodePath($name, 'pages/activity/registrate_activity/index', 'id=' . $activity['activity_id'] . 'spid=' . $uid);
    }

    /**
     * 验证活动状态
     * @param StoreActivity|null $activity
     * @return true
     */
    public function verifyActivityStatus(?StoreActivity $activity, $isCreate = false)
    {
        if (empty($activity)) {
            throw new ValidateException('活动数据异常');
        }
        if (!$activity['is_show']) {
            throw new ValidateException('活动已被关闭');
        }
        if ($isCreate) {
            if ($activity['status'] == -1) {
                throw new ValidateException('活动已结束');
            }
            if ($activity['status'] == 0) {
                throw new ValidateException('活动未开始');
            }
            //如果存在结束时间，则判断当前时间是否大于结束时间
            $end_time = $activity['end_time'] ? (strtotime($activity['end_time']) <= time() ?: false) : false;
            if ($end_time)
                throw new ValidateException('活动已结束');
            //如果没有结束时间 则判断总人数
            if ($activity['count'] > 0 && $activity['count'] <= $activity['total'])
                throw new ValidateException('活动参与人数已满');
        }
        return true;
    }

    /**
     * 验证活动数据是否存在
     *
     * 本函数用于检查给定的用户ID和活动ID对应的活动数据是否已在系统中创建。
     * 主要用于确保用户参与的活动是有效的，防止非法或不存在的活动请求。
     *
     * @param int $uid 用户ID，表示参与活动的用户的身份。
     * @param int $activity_id 活动ID，表示用户参与的具体活动。
     * @return int 返回活动记录的ID，如果活动数据存在；如果不存在，则返回0或其他表示失败的值。
     */
    public function verifyActivityData(int $uid, int $activity_id)
    {
        // 实例化存储活动关联仓库类，用于后续查询活动数据。
        $formRelated = app()->make(StoreActivityRelatedRepository::class);

        // 准备查询条件，包括用户ID、活动ID和活动类型。
        $createData = [
            'uid' => $uid,
            'activity_id' => $activity_id,
            'activity_type' => $formRelated::ACTIVITY_TYPE_FORM,
        ];

        // 执行查询并返回活动ID，如果活动数据存在。
        return $formRelated->getSearch($createData)->value('id');
    }
}
