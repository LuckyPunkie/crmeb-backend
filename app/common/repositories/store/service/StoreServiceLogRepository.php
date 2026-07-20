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


namespace app\common\repositories\store\service;


use app\common\dao\store\service\StoreServiceLogDao;
use app\common\model\store\service\StoreServiceLog;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use app\common\repositories\store\product\ProductGroupRepository;
use app\common\repositories\store\product\ProductPresellRepository;
use app\common\repositories\store\product\ProductRepository;
use think\exception\ValidateException;
use think\facade\Cache;

/**
 * 客户用户对话记录
 */
class StoreServiceLogRepository extends BaseRepository
{
    /**
     * StoreServiceLogRepository constructor.
     * @param StoreServiceLogDao $dao
     */
    public function __construct(StoreServiceLogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建客服聊天记录，自动维护 partition_ym（分区预备列）
     */
    public function create(array $data)
    {
        if (empty($data['partition_ym'])) {
            $time = $data['create_time'] ?? date('Y-m-d H:i:s');
            $ts = is_numeric($time) ? (int)$time : strtotime((string)$time);
            $data['partition_ym'] = (int)date('Ym', $ts ?: time());
        }
        return $this->dao->create($data);
    }

    /**
     * 获取用户列表
     *
     * 本函数用于根据商家ID（merId）、用户ID（uid）、页码（page）和每页数量（limit）
     * 查询用户的详细信息列表。列表数据按照服务日志ID降序排列。
     * 同时，本函数还会处理首次加载时的数据标记读取操作，以更新数据的读取状态。
     * 最后，函数返回包含用户列表数量和详细信息的数据结构。
     *
     * @param string $merId 商家ID，用于限定查询的商家范围
     * @param string $uid 用户ID，用于限定查询的用户范围
     * @param int $page 当前页码，用于分页查询
     * @param int $limit 每页数量，用于分页查询
     * @return array 返回包含用户列表数量和详细信息的数据结构
     */
    public function userList($merId, $uid, $page, $limit)
    {
        // 根据商家ID和用户ID查询符合条件的记录，并按照服务日志ID降序排列
        $query = $this->search(['mer_id' => $merId, 'uid' => $uid])->order('service_log_id DESC');

        // 统计查询结果的总数量
        $count = $query->count();

        // 分页查询用户详细信息，并加载关联数据（用户、服务信息），同时追加发送时间和日期字段
        $list = $query->page($page, $limit)->with(['user', 'service'])->select()->append(['send_time', 'send_date']);

        // 如果是第一页，则标记数据为已读，更新读取状态
        if ($page == 1) {
            $this->dao->userRead($merId, $uid);
            app()->make(StoreServiceUserRepository::class)->read($merId, $uid);
        }

        // 反转列表顺序，可能是为了满足特定的展示需求
        $list = array_reverse($this->getSendDataList($list)->toArray());

        // 返回查询结果的总数量和分页后的用户详细信息列表
        return compact('count', 'list');
    }

    /**
     * 获取服务商列表
     *
     * 本函数用于根据指定的条件获取服务商列表。它首先验证当前用户是否有权限访问指定的服务商，
     * 然后调用内部函数获取满足条件的服务商列表。
     *
     * @param int $merId 商家ID，用于指定要查询的服务商所属的商家。
     * @param int $toUid 接收方用户ID，表示列表是为这个用户展示的。
     * @param int $uid 当前操作的用户ID，用于权限验证和查询条件。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页记录数，用于分页查询。
     * @return array 返回符合查询条件的服务商列表。
     * @throws ValidateException 如果当前用户无权访问指定的服务商，则抛出异常。
     */
    public function merList($merId, $toUid, $uid, $page, $limit)
    {
        // 通过商家ID和用户ID获取服务商信息，并验证服务商是否存在且处于激活状态
        $service = app()->make(StoreServiceRepository::class)->getService($uid, $merId);
        if (!$service || !$service['status']) {
            throw new ValidateException('没有权限');
        }
        // 调用内部函数获取服务商列表，传入商家ID、服务商ID、接收方用户ID、当前页码和每页记录数
        return $this->serviceList($merId, $service->service_id, $toUid, $page, $limit);
    }

    /**
     * 获取服务列表
     *
     * 根据商家ID、服务ID、接收用户ID、页码和每页数量，查询服务记录。
     * 可选地，可以通过最后一个ID来获取新的服务记录，以实现分页和数据流的无限滚动。
     * 对于第一页的数据查询，会标记服务为已读，以更新用户和商家的服务状态。
     * 返回服务记录的总数和分页后的服务列表。
     *
     * @param string $merId 商家ID
     * @param int $service_id 服务ID
     * @param int $toUid 接收服务的用户ID
     * @param int $page 当前页码
     * @param int $limit 每页数量
     * @param string $last_id 上一次查询的最后一条ID，用于获取新数据
     * @return array 返回包含服务总数和列表的数据数组
     */
    public function serviceList($merId, $service_id, $toUid, $page, $limit, $last_id = '')
    {
        // 根据条件构造查询
        $query = $this->search(['mer_id' => $merId, 'uid' => $toUid, 'last_id' => $last_id])->order('service_log_id DESC');

        // 计算满足条件的服务总数
        $count = $query->count();

        // 执行分页查询，并加载关联数据（用户和服务信息）
        $list = $query->page($page, $limit)->with(['user', 'service'])->select()->append(['send_time', 'send_date']);

        // 如果是第一页，标记服务为已读
        if ($page == 1) {
            $this->dao->serviceRead($merId, $toUid, $service_id);
            app()->make(StoreServiceUserRepository::class)->read($merId, $toUid, true);
        }

        // 反转服务列表顺序，通常用于展示最新的服务在列表顶部
        $list = array_reverse($this->getSendDataList($list)->toArray());

        // 返回服务总数和列表的数组
        return compact('count', 'list');
    }

    /**
     * 根据不同的类型检查MSN的有效性。
     *
     * 该方法用于在不同的业务场景下，验证给定的MSN（可能代表商品ID、订单ID、退款单ID等）是否有效。
     * 通过传入的类型参数来决定检查的逻辑，如果MSN无效，则抛出ValidateException异常。
     *
     * @param string $merId 商家ID，用于限定查询的范围。
     * @param string $uid 用户ID，用于限定查询的范围。
     * @param int $type 类型标识，决定后续检查的逻辑和对应的业务实体。
     * @param string $msn 待检查的MSN，根据类型的不同，可能代表不同的ID。
     * @throws ValidateException 如果MSN无效，则抛出该异常。
     */
    public function checkMsn($merId, $uid, $type, $msn)
    {
        // 检查类型为4时，对应的商品是否存在
        if ($type == 4 && !app()->make(ProductRepository::class)->merExists($merId, $msn))
            throw new ValidateException('商品不存在');
        // 检查类型为5时，对应的订单是否存在
        else if ($type == 5 && !app()->make(StoreOrderRepository::class)->existsWhere(['uid' => $uid, 'mer_id' => $merId, 'order_id' => $msn]))
            throw new ValidateException('订单不存在');
        // 检查类型为6时，对应的退款单是否存在
        else if ($type == 6 && !app()->make(StoreRefundOrderRepository::class)->existsWhere(['uid' => $uid, 'mer_id' => $merId, 'refund_order_id' => $msn]))
            throw new ValidateException('退款单不存在');
        // 检查类型为7时，对应的预售商品是否存在
        else if ($type == 7 && !app()->make(ProductPresellRepository::class)->existsWhere(['product_presell_id' => $msn, 'mer_id' => $merId]))
            throw new ValidateException('商品不存在');
        // 检查类型为8时，对应的拼团商品是否存在
        else if ($type == 8 && !app()->make(ProductGroupRepository::class)->existsWhere(['product_group_id' => $msn, 'mer_id' => $merId]))
            throw new ValidateException('商品不存在');
    }

    /**
     * 根据日志的类型获取相应的发送数据。
     *
     * 本函数旨在根据服务日志中记录的不同类型（msn_type），提取出相应的数据。
     * msn_type 的不同值对应着不同的数据对象，包括产品、订单、退款订单、预售和产品组等信息。
     * 通过判断日志的类型，直接返回对应类型的数据对象，便于后续处理和使用。
     *
     * @param StoreServiceLog $log 服务日志对象，包含各种类型的数据信息。
     * @return StoreServiceLog 返回经过筛选后的服务日志对象，数据对象根据日志类型有所不同。
     */
    public function getSendData(StoreServiceLog $log)
    {
        // 根据日志的类型，提取相应的数据对象
        if ($log->msn_type == 4) {
            $log->product;
        } else if ($log->msn_type == 5) {
            $log->orderInfo;
        } else if ($log->msn_type == 6) {
            $log->refundOrder;
        } else if ($log->msn_type == 7) {
            $log->presell;
        } else if ($log->msn_type == 8) {
            $log->productGroup;
        }
        return $log;
    }

    /**
     * 批量预加载关联数据，消除 N+1 查询
     *
     * @param array $list 日志对象的列表。
     * @return array 处理后的日志对象列表。
     */
    public function getSendDataList($list)
    {
        $ids = ['product' => [], 'order' => [], 'refund' => [], 'presell' => [], 'group' => []];
        $cache = [];

        foreach ($list as $log) {
            if (!in_array($log->msn_type, [4, 5, 6, 7, 8])) {
                continue;
            }

            $key = $log->msn_type . $log->msn;

            if (isset($cache[$key])) {
                $this->assignToLog($log, $cache[$key]);
                continue;
            }

            switch ($log->msn_type) {
                case 4: $ids['product'][] = $log->msn; break;
                case 5: $ids['order'][] = $log->msn; break;
                case 6: $ids['refund'][] = $log->msn; break;
                case 7: $ids['presell'][] = $log->msn; break;
                case 8: $ids['group'][] = $log->msn; break;
            }
        }

        $products = $orders = $refunds = $presells = $groups = [];

        if (!empty($ids['product'])) {
            $products = \app\common\model\store\product\Product::whereIn('product_id', array_unique($ids['product']))
                ->column('*', 'product_id');
        }
        if (!empty($ids['order'])) {
            $orders = \app\common\model\store\order\StoreOrder::whereIn('order_id', array_unique($ids['order']))
                ->with('orderProduct')->select()->column(null, 'order_id');
        }
        if (!empty($ids['refund'])) {
            $refunds = \app\common\model\store\order\StoreRefundOrder::whereIn('refund_order_id', array_unique($ids['refund']))
                ->with('refundProduct.product')->select()->column(null, 'refund_order_id');
        }
        if (!empty($ids['presell'])) {
            $presells = \app\common\model\store\product\ProductPresell::whereIn('product_presell_id', array_unique($ids['presell']))
                ->append(['product'], true)->select()->column(null, 'product_presell_id');
        }
        if (!empty($ids['group'])) {
            $groups = \app\common\model\store\product\ProductGroup::whereIn('product_group_id', array_unique($ids['group']))
                ->append(['product'], true)->select()->column(null, 'product_group_id');
        }

        foreach ($list as $log) {
            if (!in_array($log->msn_type, [4, 5, 6, 7, 8])) {
                continue;
            }

            $key = $log->msn_type . $log->msn;
            if (isset($cache[$key])) {
                continue;
            }

            switch ($log->msn_type) {
                case 4:
                    $data = $products[$log->msn] ?? null;
                    if ($data) { $cache[$key] = $data; $log->set('product', $data); }
                    break;
                case 5:
                    $data = $orders[$log->msn] ?? null;
                    if ($data) { $cache[$key] = $data; $log->set('orderInfo', $data); }
                    break;
                case 6:
                    $data = $refunds[$log->msn] ?? null;
                    if ($data) { $cache[$key] = $data; $log->set('refundOrder', $data); }
                    break;
                case 7:
                    $data = $presells[$log->msn] ?? null;
                    if ($data) { $cache[$key] = $data; $log->set('presell', $data); }
                    break;
                case 8:
                    $data = $groups[$log->msn] ?? null;
                    if ($data) { $cache[$key] = $data; $log->set('productGroup', $data); }
                    break;
            }
        }

        return $list;
    }

    private function assignToLog(StoreServiceLog $log, $data): void
    {
        switch ($log->msn_type) {
            case 4: $log->set('product', $data); break;
            case 5: $log->set('orderInfo', $data); break;
            case 6: $log->set('refundOrder', $data); break;
            case 7: $log->set('presell', $data); break;
            case 8: $log->set('productGroup', $data); break;
        }
    }

    /**
     * 更新消息送达状态（ACK）
     */
    public function updateDeliverStatus(int $msgId, string $status): void
    {
        $deliverStatus = ($status === 'received') ? 1 : 0;
        $this->dao->update($msgId, ['deliver_status' => $deliverStatus]);
    }

    /**
     * 根据用户ID和是否为服务聊天获取聊天记录
     *
     * 本函数用于从缓存中获取指定用户的聊天记录。它可以根据用户是否是服务聊天来选择不同的缓存键，
     * 这样可以区分普通用户聊天记录和服务聊天记录，避免混淆。
     *
     * @param int $uid 用户ID，用于指定要获取聊天记录的用户。
     * @param bool $isService 指示是否为服务聊天，用于区分普通聊天和服务聊天。
     * @return mixed 返回从缓存中获取的聊天记录，如果不存在则返回null。
     */
    public function getChat($uid, $isService = false)
    {
        // 根据$isService的值决定缓存键的前缀，如果是服务聊天则为's_chat'，否则为'u_chat'。
        // 然后拼接上$uid作为完整的缓存键。
        $key = ($isService ? 's_chat' : 'u_chat') . $uid;

        // 使用Cache类的get方法尝试获取缓存中存储的聊天记录。
        return Cache::get($key);
    }

    /**
     * 获取某个客服的用户列表
     * @param $service_id
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2020-06-18
     */
    public function getServiceUserList($service_id, $page, $limit)
    {
        $query = $this->dao->getUserListQuery($service_id)->with(['user'])->group('uid');
        $count = $query->count();

        $list = $query->setOption('field', [])->field('uid,mer_id,create_time,type')
            ->page($page, $limit)
            ->select();

        return compact('count', 'list');
    }

    /**
     * 获取商户的聊天用户列表
     * @param $merId
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2020-06-19
     */
    public function getMerchantUserList($merId, $page, $limit)
    {
        $query = $this->dao->getMerchantUserList($merId)->with(['user'])->group('uid');
        $count = $query->count();
        $list = $query->setOption('field', [])->field('uid,mer_id,create_time,type')->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 根据用户ID和条件获取用户的MSN信息
     *
     * 本函数用于查询指定用户ID的MSN相关信息。支持根据商家ID和服務ID进行过滤查询，
     * 提供分页功能，以满足前端或业务需求的分批获取数据。
     *
     * @param int $uid 用户ID，用于指定查询哪个用户的MSN信息。
     * @param string $page 分页参数，指定当前页码。
     * @param string $limit 分页参数，指定每页显示的记录数。
     * @param int|null $merId 商家ID，可选参数，用于过滤属于特定商家的MSN信息。
     * @param int|null $serviceId 服务ID，可选参数，用于过滤属于特定服务的MSN信息。
     * @return array 返回包含记录总数和用户MSN信息列表的数组。
     */
    public function getUserMsn(int $uid, $page, $limit, ?int $merId = null, ?int $serviceId = null)
    {
        // 初始化查询条件
        $where['uid'] = $uid;

        // 如果提供了商家ID，则添加到查询条件中
        if ($merId) $where['mer_id'] = $merId;

        // 如果提供了服务ID，则添加到查询条件中
        if ($serviceId) $where['service_id'] = $serviceId;

        // 构建查询对象，并设置排序方式
        $query = $this->search($where)->order('service_log_id DESC');

        // 计算符合条件的记录总数
        $count = $query->count();

        // 执行分页查询，并加载关联数据（用户、服务信息）以及附加MSN信息
        $list = $query->page($page, $limit)->with(['user', 'service'])->append(['msn_info'], true)->select();

        // 返回记录总数和查询结果列表
        return compact('count', 'list');
    }
}
