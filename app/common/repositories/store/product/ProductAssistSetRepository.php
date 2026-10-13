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

use think\facade\Queue;
use crmeb\jobs\SendSmsJob;
use app\common\model\store\product\ProductAssistSet;
use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductAssistSetDao;
use app\common\repositories\store\order\StoreOrderRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 助力活动记录
 */
class ProductAssistSetRepository extends BaseRepository
{
    public function __construct(ProductAssistSetDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取API列表
     * 根据给定的条件数组($where)、页码($page)和每页数量($limit)获取搜索结果列表。
     * 主要用于协助销售平台的商家查询其相关的辅助销售设置。
     *
     * @param array $where 搜索条件数组，包含各种过滤条件。
     * @param int $page 当前页码。
     * @param int $limit 每页显示的数量。
     * @return array 返回包含总数和列表数据的数组。
     */
    public function getApiList(array $where, int $page, int $limit)
    {
        // 构建查询条件，根据创建时间降序排列，并加载关联数据
        $query = $this->dao->getSearch($where)->order('create_time DESC')
            ->with([
                "assist",
                "assistSku",
                'product' => function ($query) {
                    // 为产品模型加载特定的字段，以减少数据冗余和提高查询效率
                    $query->field('product_id,image,store_name,status,unit_name,rank,mer_status,slider_image,mer_id,price');
                }]);
        // 计算满足条件的总记录数
        $count = $query->count();
        // 分页查询，并处理每条数据，为每条数据添加订单信息
        $list = $query->page($page, $limit)->append(['check', 'stop_time'], true)->select()->each(function ($item) use ($where) {
            // 根据条件获取订单信息，并在数据中添加一个新的订单属性
            $order = $this->getOrderInfo($where['uid'], $item['product_assist_set_id']);
            return $item['order'] = $order ? $order : new \stdClass();
        });
        // 返回包含总数和列表数据的数组
        return compact('count', 'list');
    }

    /**
     * 获取商家列表
     *
     * 根据给定的条件、分页和限制获取商家列表。此方法包括对商家数据的复杂查询，
     * 如关联查询商家的产品和用户信息，并对查询结果进行分页。
     *
     * @param array $where 查询条件，作为一个关联数组传递
     * @param int $page 当前页码
     * @param int $limit 每页的记录数
     * @return array 返回包含总数和商家列表的关联数组
     */
    public function getMerchantList(array $where, int $page, int $limit)
    {
        // 构建查询
        $query = $this->dao->getSearch($where)
            ->order('create_time DESC')
            ->with([
                'assist.assistSku', // 关联查询商家助力商品的SKU信息
                'product' => function ($query) {
                    // 关联查询商家产品的详细信息，包括图片、名称、状态等
                    $query->field('product_id,image,store_name,status,unit_name,rank,mer_status,slider_image,mer_id');
                },
                'user' => function ($query) {
                    // 关联查询商家的用户信息，包括用户ID和昵称
                    $query->field('uid,nickname');
                }
            ])
            ->append(['check', 'user_count'], true); // 追加查询某些附加信息，如商家的检查状态和用户数量

        // 计算总记录数
        $count = $query->count();

        // 进行分页查询，并获取商家列表
        $list = $query->page($page, $limit)->select();

        // 返回包含总记录数和商家列表的数组
        return compact('count', 'list');
    }

    /**
     * 获取管理员列表
     *
     * 本函数用于根据条件获取管理员列表，支持分页和条件筛选。返回管理员列表的同时，还包括管理员相关的辅助信息，
     * 如关联的产品信息、商家信息和用户信息，以便更全面地展示管理员及其相关业务信息。
     *
     * @param array $where 筛选条件，用于指定查询的特定条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页显示的数量，用于分页查询。
     * @return array 返回包含管理员数量和管理员列表的数组。
     */
    public function getAdminList(array $where, int $page, int $limit)
    {
        // 构建查询条件，包括排序和关联信息的加载
        $query = $this->dao->getSearch($where)->order('create_time DESC')
            ->with(['assist.assistSku', 'product' => function ($query) {
                // 加载产品信息，只包含指定的字段
                $query->field('product_id,image,store_name,status,unit_name,rank,mer_status,slider_image,mer_id');
            }, 'merchant', 'user' => function ($query) {
                // 加载用户信息，只包含指定的字段
                $query->field('uid,nickname');
            }])
            ->append(['check', 'user_count'], true);
        // 分组权限
        if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
            $query->whereIn('mer_id', $region);
        }

        // 计算符合条件的管理员总数
        $count = $query->count();

        // 根据当前页码和每页的数量，获取管理员列表
        $list = $query->page($page, $limit)->select();

        // 将管理员总数和管理员列表打包成数组返回
        return compact('count', 'list');
    }

    /**
     *  发起助力活动
     * @param int $assistId
     * @param int $uid
     * @return \app\common\dao\BaseDao|array|\think\Model|null
     * @author Qinii
     * @day 2020-10-27
     */
    public function create(int $assistId, int $uid)
    {
        $where['product_assist_id'] = $assistId;
        $where['uid'] = $uid;
        $where['is_del'] = 0;
        $make = app()->make(StoreOrderRepository::class);
        $arr = ['exsits_id' => $assistId, 'product_type' => 3];
        $make->getTattendCount($arr, $uid)->count();

        $result = $this->dao->getSearch($where)->where('status', 'in', [1, 10])->find();
        if ($result) {
            $order = $this->getOrderInfo($uid, $result['product_assist_set_id']);
            $paid = $order['paid'] ?? null;
            if (!$order || $result['status'] == 1 || !$paid) return $result;
        }
        $make = app()->make(ProductAssistRepository::class);
        $res = $make->checkAssist($assistId, $uid);
        $where['product_id'] = $res['product_id'];
        $where['assist_count'] = $res['assist_count'];
        $where['assist_user_count'] = $res['assist_user_count'];
        $where['mer_id'] = $res['mer_id'];
        $where['share_num'] = 1;
        $where['view_num'] = 1;
        $result = $this->dao->create($where);

        return $result;
    }

    /**
     *  助力操作
     * @param int $id
     * @param $userInfo
     * @author Qinii
     * @day 2020-10-27
     */
    public function set(int $id, $userInfo)
    {
        $where = [
            "product_assist_set_id" => $id,
            "status" => 1,
        ];
        $result = $this->dao->getSearch($where)->find();
        if (!$result) throw new ValidateException('活动不存在或已关闭');

        $relation = $this->relation($result, $userInfo->uid);
        if (!$relation) throw new ValidateException('活动不存在或已关闭');
        if ($relation == -1) throw new ValidateException('您的助力次数已达上限');
        if (!$relation == -2) throw new ValidateException('您已助力过了');
        if ($relation == 10) throw new ValidateException('不能为自己助力');

        if ($result['assist_count'] <= $result['yet_assist_coount']) {
            $result->yet_assist_count = $result->assist_count;
            $result->status = 10;
            $result->save();
            throw new ValidateException('助力已完成');
        }

        $data = [
            "product_assist_set_id" => $id,
            'uid' => $userInfo->uid,
            'avatar_img' => $userInfo->avatar,
            'nickname' => $userInfo->nickname,
            "product_assist_id" => $result['product_assist_id']
        ];

        Db::transaction(function () use ($id, $data, $result) {

            $yet = $result->yet_assist_count + 1;
            $status = 0;
            if ($yet >= $result['assist_count']) {
                $yet = $result->assist_count;
                $result->status = 10;
                $status = 10;
            }
            $result->yet_assist_count = $yet;
            $result->save();

            $make = app()->make(ProductAssistUserRepository::class);
            $make->create($data);
            if ($status) Queue::push(SendSmsJob::class,['tempId' => 'ASSIST_SUCCESS', 'id' => $id]);
        });
    }

    /**
     *
     * @param int $id
     * @return array|\think\Model|null
     * @author Qinii
     * @day 2020-10-27
     */
    /**
     * 获取活动详情
     *
     * 本函数用于根据提供的活动ID和其他参数，详细查询活动的信息。
     * 它包括活动的基本信息、产品信息、参与者信息等。此外，它还会根据用户的状态，
     * 添加一些额外的信息，如用户是否已参与活动、活动的结束时间等。
     *
     * @param int $id 活动ID
     * @param object $userInfo 用户信息对象，包含用户ID等
     * @param int $type 活动类型，默认为1
     * @return array 活动详情，包含各种相关信息
     * @throws ValidateException 如果查询不到活动信息，则抛出异常
     */
    public function detail(int $id, $userInfo, $type = 1)
    {
        // 根据类型判断是否需要创建活动助力设置信息
        //    if($type == 2){
        //        $product_assist_set_info = $this->create($id,$userInfo['uid']);
        //        if(empty($product_assist_set_info)){
        //            throw new ValidateException('活动助力创建失败');
        //        }
        //        $id = $product_assist_set_info['product_assist_set_id'];
        //    }

        // 构建查询条件
        $where = [
            "product_assist_set_id" => $id,
        ];
        // 执行查询，包括活动的基本信息、产品内容、助力SKU、用户信息等
        $res = $this->dao->getSearch($where)->with([
            'product' => function($query){
                $query->with(['merchant','reservation','content'])->field('product_id,store_name,image,old_product_id,activity_label_ids,delivery_free,give_coupon_ids,delivery_way,type,product_type,mer_id');;
            },
            'assist.assistSku.sku',
            'user' => function ($query) {
                $query->field('uid,avatar,nickname');
            }
        ])->append(['stopTime'], true)->find();
        // 如果查询结果为空，则抛出异常
        if (!$res) throw new ValidateException('数据丢失');
        // 设置产品唯一标识
        $res['product']['unique'] = $res['assist']['assistSku'][0]['unique'];
        // 计算用户与活动的关系
        $relation = $this->relation($res, $userInfo->uid);
        // 将关系信息添加到查询结果中
        $res['relation'] = $relation;
        // 获取参与活动的用户总数
        $countData = app()->make(ProductAssistRepository::class)->getUserCount();
        // 添加用户总数到查询结果
        $res['user_count_all'] = $countData['count'];
        // 添加该活动的参与者数量到查询结果
        $res['user_count_product'] = $res->assist->user_count;
        // 获取用户的订单信息，如果用户没有订单，则设置为一个空对象
        $order = $this->getOrderInfo($userInfo->uid, $id);
        if ($relation == 10) $res['order'] = $order ? $order : new \stdClass();

        // 初始化用户是否可以创建活动的标志
        $where = [
            "product_assist_id" => $res['product_assist_id'],
            'uid' => $userInfo->uid,
            'status' => 1
        ];
        $res['create_status'] = true;
        // 如果当前用户不是活动的发起者，则增加活动的参与人数
        if ($res['uid'] !== $userInfo->uid) $this->dao->incNum(2, $id);

        // 返回活动详情
        return $res;
    }


    /**
     * 根据用户ID和助力集ID获取订单信息
     *
     * 此方法用于查询指定用户在一个特定助力活动中的订单情况。
     * 它首先尝试根据助力集ID和用户ID查询订单数据，然后返回相关订单的支付状态、订单ID和团订单ID。
     * 如果找不到相关订单，则返回null。
     *
     * @param int $uid 用户ID。表示要查询订单的用户。
     * @param int $assistSetId 助力集ID。表示特定的助力活动。
     * @return array|null 返回包含订单支付状态、订单ID和团订单ID的数组，如果找不到订单则返回null。
     */
    public function getOrderInfo(int $uid, int $assistSetId)
    {
        // 初始化结果变量为null
        $result = null;

        // 实例化订单仓库，用于后续的订单查询
        $order_make = app()->make(StoreOrderRepository::class);

        // 定义查询条件，包括助力集ID和产品类型
        $tattend = [
            'activity_id' => $assistSetId,
            'product_type' => 3
        ];

        // 根据查询条件和用户ID查询订单，并尝试获取第一条数据
        $order = $order_make->getTattendCount($tattend, $uid)->find();

        // 如果查询到订单数据
        if ($order) {
            // 构建并返回订单信息数组，包含支付状态、订单ID和团订单ID
            $result = [
                'paid' => $order['paid'],
                'order_id' => $order['order_id'],
                'group_order_id' => $order['group_order_id'],
            ];
        }

        // 返回查询结果，可能是订单信息数组或null
        return $result;
    }

    /**
     *  用户于当前助力活动的关系
     * @param ProductAssistSetRepository $res
     * @param int $uid
     * @return bool
     * @author Qinii
     * @day 2020-10-27
     */
    public function relation(ProductAssistSet $res, int $uid)
    {
        if ($res['status'] == -1) return false; // 活动过结束
        //过期 活动结束
        if ($res->stop_time < time()) {
            $res->status = -1;
            $res->save();
            return false;
        }
        if ($uid == $res['uid']) {
            //发起者
            $relation = 10;
        } else {
            //不可助力
            $relation = -2;
            $make = app()->make(ProductAssistUserRepository::class);
            //$_count 有自己助力
            $_count = $make->getSearch(['product_assist_set_id' => $res['product_assist_set_id'], 'uid' => $uid])->count();
            if (!$_count && $res['assist_count'] > $res['yet_assist_count']) {
                $count = $make->getSearch(['product_assist_id' => $res['product_assist_id'], 'uid' => $uid])->count();
                $relation = -1;
                //用户还可以助力
                if ($count < $res['assist_user_count']) $relation = 1;
            }
        }
        return $relation;
    }

    /**
     * 检查购物车中的助力商品是否符合购买条件。
     *
     * 此函数用于在用户将助力商品添加到购物车时，验证该商品是否满足购买条件。
     * 这包括检查商品是否为新助力活动，是否已购买过相同商品，活动是否结束，以及是否已完成助力任务等条件。
     * 如果商品不符合任何条件，将抛出一个验证异常，阻止商品添加到购物车。
     *
     * @param array $data 商品数据，包含商品ID和购买数量等信息。
     * @param object $userInfo 用户信息，包含用户的唯一标识符。
     * @throws ValidateException 如果商品不符合购买条件，抛出验证异常。
     * @return array 返回包含商品、SKU和购物车信息的紧凑数组。
     */
    public function cartCheck(array $data, $userInfo)
    {
        // 检查商品是否为新助力活动，如果不是，则抛出异常。
        /**
         *  1 活动是否助力完成
         *  2 商品是否有效
         *  2 库存是否不足
         */
        if (!$data['is_new']) throw new ValidateException('助力商品不可加入购物车');
        // 检查助力商品是否只能购买一件，如果不是，则抛出异常。
        if ($data['cart_num'] != 1) throw new ValidateException('助力商品每次只能购买一件');
        // 构建查询条件，根据商品ID和用户ID查询助力活动信息。
        $where[$this->dao->getPk()] = $data['product_id'];
        $where['uid'] = $userInfo->uid;
        // 查询助力活动信息。
        $result = $this->dao->getSearch($where)->find();
        // 如果查询结果为空，表示用户未参与该助力活动，抛出异常。
        if (!$result) throw new ValidateException('请先发起您自己的助力活动');
        // 实例化订单仓库，用于查询用户的助力活动参与情况。
        $order_make = app()->make(StoreOrderRepository::class);
        // 构建查询条件，检查用户是否已参与过相同的助力活动。
        $tattend = [
            'activity_id' => $data['product_id'],
            'product_type' => 3,
        ];
        // 如果用户已参与过相同的助力活动，抛出异常。
        if ($order_make->getTattendCount($tattend, $userInfo->uid)->count())
            throw new ValidateException('请勿重复下单');
        // 实例化助力活动仓库，用于检查助力活动的状态和完成情况。
        $make = app()->make(ProductAssistRepository::class);
        // 检查助力活动是否已结束，如果已结束，抛出异常。
        if ($result['status'] == -1) throw new ValidateException('活动已结束');
        // 检查助力任务是否已完成，如果未完成，抛出异常。
        if ($result['assist_count'] !== $result['yet_assist_count']) throw new ValidateException('快去邀请好友来助力吧');
        // 根据助力活动ID和用户ID检查助力活动的参与情况。
        $res = $make->checkAssist($result['product_assist_id'], $userInfo->uid);
        // 提取商品和SKU信息，准备返回。
        $product = $res['product'];
        $sku = $product['assistSku'];
        // 初始化购物车信息为空，因为助力商品不直接加入购物车。
        $cart = null;
        // 返回商品、SKU和购物车信息的数组。
        return compact('product', 'sku', 'cart');
    }
}

