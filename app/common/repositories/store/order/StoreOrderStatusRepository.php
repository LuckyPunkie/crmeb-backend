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


namespace app\common\repositories\store\order;

use app\common\repositories\user\UserRepository;
use app\common\dao\store\order\StoreOrderStatusDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\store\service\StoreServiceUserRepository;

/**
 * Class StoreOrderStatusRepository
 * @package app\common\repositories\store\order
 * @author xaboy
 * @day 2020/6/11
 * @mixin StoreOrderStatusDao
 */
class StoreOrderStatusRepository extends BaseRepository
{
    //订单日志
    public const TYPE_ORDER  = 'order';
    //退款单日志
    public const TYPE_REFUND = 'refund';
    //商品日志
//    public const TYPE_PRODUCT = 'product';

    //操作者类型
    public const U_TYPE_SYSTEM = 0;
    public const U_TYPE_USER = 1;
    public const U_TYPE_ADMIN = 2;
    public const U_TYPE_MERCHANT = 3;
    public const U_TYPE_SERVICE = 4;

    //订单变动类型
    //取消
    const ORDER_STATUS_CANCEL = 'cancel';
    //改价
    const ORDER_STATUS_CHANGE = 'change';
    //创建
    const ORDER_STATUS_CREATE = 'create';
    //删除
    const ORDER_STATUS_DELETE = 'delete';
    //收货
    const ORDER_STATUS_TAKE   = 'take';
    //拆单
    const ORDER_STATUS_SPLIT   = 'split';
    //完成
    const ORDER_STATUS_OVER   = 'over';
    const ORDER_STATUS_AUTO_OVER    = 'auto_over';
    //预售订单
    const ORDER_STATUS_PRESELL= 'presell';
    const ORDER_STATUS_PRESELL_CLOSE = 'presell_close';
    //全部退款
    const ORDER_STATUS_REFUND_ALL   = 'refund_all';
    //支付成功
    const ORDER_STATUS_PAY_SUCCCESS  = 'pay_success';
    //拼图成功
    const ORDER_STATUS_GROUP_SUCCESS = 'group_success';
    //申请退款
    const CHANGE_REFUND_CREATGE = 'refund_create';
    //已发货
    const CHANGE_BACK_GOODS = 'back_goods';
    //退款申请已通过
    const CHANGE_REFUND_AGREE = 'refund_agree';
    //退款成功
    const CHANGE_REFUND_PRICE = 'refund_price';
    //订单退款已拒绝
    const CHANGE_REFUND_REFUSE = 'refund_refuse';
    //用户取消退款
    const CHANGE_REFUND_CANCEL = 'refund_cancel';
    //用户申请平台介入
    const CHANGE_REFUND_PLATFORM_INTERVENE = 'refund_platform_intervene';
    //平台同意退款
    const CHANGE_REFUND_PLATFORM_AGREE = 'refund_platform_agree';
    //平台拒绝退款
    const CHANGE_REFUND_PLATFORM_REFUSE = 'refund_platform_refuse';
    // 预约订单派单
    const RESERVATION_ORDER_DISPATCH = 'order_dispatch';
    // 预约订单改期
    const RESERVATION_ORDER_RESCHEDULE = 'order_reschedule';
    // 同城配送订单派单
    const DELIVERY_ORDER_DISPATCH = 'delivery_order_dispatch';

    /*
      2   => '待取货',
      3   => '配送中',
      4   => '已完成',
      -1  => '已取消',
      9   => '物品返回中',
      10  => '物品返回完成',
      100 => '骑士到店',
    */
    const ORDER_DELIVERY_COURIER    = 'delivery_0';
    const ORDER_DELIVERY_SELF       = 'delivery_1';
    const ORDER_DELIVERY_NOTHING    = 'delivery_2';
    const ORDER_DELIVERY_CITY       = 'delivery_5';
    const ORDER_DELIVERY_CITY_CANCEL    = 'delivery_5_-1';
    const ORDER_DELIVERY_CITY_ARRIVE    = 'delivery_5_100';
    const ORDER_DELIVERY_CITY_WAITING   = 'delivery_5_2';
    const ORDER_DELIVERY_CITY_ING       = 'delivery_5_3';
    const ORDER_DELIVERY_CITY_OVER      = 'delivery_5_4';
    const ORDER_DELIVERY_CITY_REFUND    = 'delivery_5_10';
    const ORDER_DELIVERY_CITY_REFUNDING = 'delivery_5_9';
    const ORDER_DELIVERY_SHIPMENT           = 'delivery_8';
    const ORDER_DELIVERY_SHIPMENT_CANCEL    = 'delivery_8_-1';
    const ORDER_DELIVERY_SHIPMENT_PACKAGE   = 'delivery_8_1';
    const ORDER_DELIVERY_SHIPMENT_SUCCESS   = 'delivery_8_10';


    /**
     * StoreOrderStatusRepository constructor.
     * @param StoreOrderStatusDao $dao
     */
    public function __construct(StoreOrderStatusDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 更新订单状态
     *
     * 通过调用数据访问对象（DAO）的方法，创建一个新的记录来反映订单状态的变更。
     * 此方法封装了订单状态更新的过程，抽象了数据操作的细节，使得业务逻辑层不需要直接与数据库交互。
     *
     * @param int $order_id 订单ID，用于唯一标识订单。
     * @param string $change_type 状态变更类型，表示订单状态的变化方向，例如：支付、退款等。
     * @param string $change_message 状态变更说明，提供关于状态变更的详细信息，有助于后续的问题排查和订单审计。
     * @return bool|object 返回值为布尔类型或数据对象，取决于DAO操作的结果。成功时返回新创建的对象，失败时返回false。
     */
    public function status($order_id, $change_type, $change_message)
    {
        // 使用compact函数将方法参数打包为一个数组，并通过DAO的create方法创建一个新的订单状态记录。
        return $this->dao->create(compact('order_id', 'change_message', 'change_type'));
    }


    /**
     * 根据条件搜索数据并分页返回结果。
     *
     * 本函数用于根据提供的条件查询数据库，并返回满足条件的数据的分页列表。
     * 参数$where用于指定查询条件，$page指定当前页码，$limit指定每页的数据条数。
     * 返回一个包含总数和数据列表的数组。
     *
     * @param string|array $where 查询条件，可以是字符串或数组形式的条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页显示的数据条数。
     * @return array 返回一个包含总数和数据列表的数组。
     */
    public function search($where,$page, $limit)
    {
        // 根据查询条件进行搜索，并按照变更时间降序排序。
        $query = $this->dao->search($where)->order('change_time DESC');

        // 统计满足条件的数据总数。
        $count = $query->count();

        // 根据当前页码和每页的数据条数，获取满足条件的数据列表。
        $list = $query->page($page, $limit)->select();

        // 返回包含总数和数据列表的数组。
        return compact('count','list');
    }

    /**
     * 创建管理员日志
     * 该方法用于记录管理员的操作日志。它捕获当前请求的信息，包括用户类型、用户ID和用户昵称，
     * 并将这些信息连同其他数据一起存储在日志中。
     *
     * @param array $data 包含日志信息的数据数组
     * @return bool|int 返回创建的日志ID或false，如果创建失败
     */
    public function createAdminLog(array $data)
    {
        try{
            // 获取当前请求对象
            $request = request();

            // 从请求对象中获取用户类型，并将其添加到数据数组中
            $data['user_type'] = $request->userType();

            // 从请求对象中获取管理员ID，并将其添加到数据数组中
            $data['uid'] = $request->adminId();

            // 从请求对象中获取管理员的真实姓名，并将其添加到数据数组中
            $data['nickname'] = 'ID:'.$request->adminId().',昵称：'.$request->adminInfo()->real_name;
            // 使用DAO对象创建日志条目
            return $this->dao->create($data);
        } catch (\Exception $e) {
            // 如果创建日志时发生异常，则尝试创建系统日志
            return $this->createSysLog($data);
        }
    }

    /**
     * 创建服务日志
     *
     * 本函数用于记录与特定服务相关的日志信息。通过提供的服务ID，检索服务相关信息，
     * 并结合传入的数据数组，创建一条包含详细服务信息的日志记录。
     *
     * @param int $service_id 服务ID，用于查询特定服务信息。
     * @param array $data 包含日志详细信息的数据数组，其中用户类型、UID和昵称将被特别处理。
     * @return bool|int 返回创建的日志ID或false，如果创建失败。
     */
    public function createServiceLog($service_id, array $data)
    {
        // 根据服务ID获取服务信息
        $service = app()->make(StoreServiceRepository::class)->getWhere(['service_id' => $service_id]);

        // 设置日志数据的用户类型为服务类型
        $data['user_type'] = self::U_TYPE_SERVICE;
        // 设置日志数据的UID为服务ID
        $data['uid'] = $service_id;
        // 设置日志数据的昵称为服务的昵称
        $data['nickname'] = $service->nickname;

        // 创建日志记录
        return $this->dao->create($data);
    }

    /**
     * 创建用户日志
     *
     * 该方法用于记录用户的操作日志。它通过接收一个数据数组，然后在这个数组中添加用户类型、用户ID和用户昵称等信息。
     * 如果能够成功获取到用户信息，则正常添加到数组中；如果获取用户信息失败，则标记为异常情况。
     * 最后，将这个填充好的数据数组提交给DAO层，进行实际的日志创建操作。
     *
     * @param array $data 用户操作日志的数据数组，需要包含相关信息。
     * @return bool 创建日志的操作结果，成功返回true，失败返回false。
     */
    public function createUserLog($uid, array $data)
    {
        // 设置用户类型为普通用户
        $data['user_type'] = self::U_TYPE_USER;

        try {
            $user = app()->make(UserRepository::class)->get($uid);
            // 尝试获取当前请求的用户ID和昵称，并添加到数据数组中
            $data['uid'] = $user->uid;
            $data['nickname'] = $user->nickname;
        } catch (\Exception $exception) {
            // 如果获取用户信息失败，将用户ID标记为-1，昵称为空字符串
            $data['uid'] = -1;
            $data['nickname'] = '';
        }

        // 调用DAO层的create方法，尝试创建用户日志
        return $this->dao->create($data);
    }

    /**
     * 创建系统日志
     *
     * 该方法用于生成系统的操作日志记录。它通过指定的日志数据数组，填充特定的字段值，
     * 然后调用DAO层的方法来创建日志记录。系统日志主要包含用户类型、用户ID、用户昵称等信息。
     *
     * @param array $data 日志数据数组，包含日志的具体内容。
     * @return bool|int 返回创建操作的结果，通常是新插入记录的ID或布尔值（操作成功或失败）。
     */
    public function createSysLog(array $data)
    {
        // 设置日志的用户类型为系统
        $data['user_type'] = self::U_TYPE_SYSTEM;
        // 设置日志的用户ID为0，表示系统操作
        $data['uid'] = 0;
        // 设置日志的用户昵称为“系统”
        $data['nickname'] = '系统';

        // 调用DAO层的create方法，插入日志数据
        return $this->dao->create($data);
    }

    /**
     * 批量创建日志记录。
     *
     * 本函数用于处理给定数据集的日志记录批量创建。如果传入的数据集不为空，
     * 则通过调用DAO层的insertAll方法将所有日志数据插入数据库。此方法的设计
     * 旨在减少数据库交互次数，提高数据插入的效率。
     *
     * @param array $data 包含日志数据的数组。每个元素都应该是一个日志条目的数组。
     *                   数组的每个元素应该包含日志记录的所有必要字段。
     * @return bool|void 如果$data不为空，返回DAO层的insertAll方法的执行结果，通常是布尔值，
     *                   表示插入操作是否成功。如果$data为空，则不执行插入操作，也不返回任何值。
     */
    public function batchCreateLog($data)
    {
        // 检查传入的数据集是否为空
        if(!empty($data)) {
            // 如果数据集不为空，调用DAO层的insertAll方法插入所有日志数据
            return $this->dao->insertAll($data);
        }
    }
}
