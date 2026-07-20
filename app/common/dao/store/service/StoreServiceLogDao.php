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


namespace app\common\dao\store\service;


use app\common\dao\BaseDao;
use app\common\model\store\service\StoreServiceLog;
use think\db\BaseQuery;

/**
 * Class StoreServiceLogDao
 * @package app\common\dao\store\service
 * @author xaboy
 * @day 2020/5/29
 */
class StoreServiceLogDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/5/29
     */
    protected function getModel(): string
    {
        return StoreServiceLog::class;
    }

    /**
     * 更新用户服务记录的状态
     *
     * 本函数用于将特定用户的特定服务记录的状态从未读改为已读。
     * 它通过指定的商家ID和用户ID来定位特定的服务记录，并更新其类型字段。
     *
     * @param string $merId 商家ID，用于定位服务记录所属的商家。
     * @param string $uid 用户ID，用于定位服务记录所属的用户。
     * @return int 返回更新操作影响的行数，即被更新的服务记录数量。
     * @throws \think\db\exception\DbException
     */
    public function userRead($merId, $uid)
    {
        // 使用where子句构建查询条件，定位到特定商家、特定用户且类型不为1的服务记录，
        // 然后将这些记录的类型更新为1，表示这些记录已被读取。
        return StoreServiceLog::getDB()->where('mer_id', $merId)->where('uid', $uid)->where('type', '<>', 1)->update(['type' => 1]);
    }

    /**
     * 检查用户是否有未发送的日志
     *
     * 本函数用于查询指定用户（$uid）在指定商户（$merId）下是否存在未发送类型的日志记录。
     * 通过返回布尔值来表示查询结果，如果存在至少一条未发送的日志记录，则返回true，否则返回false。
     * 这对于判断是否需要发送日志或者是否已完成所有日志发送等情况非常有用。
     *
     * @param int $uid 用户ID，用于指定查询哪个用户的日志记录。
     * @param int $merId 商户ID，用于指定查询哪个商户的日志记录。
     * @return bool 如果查询结果存在至少一条记录则返回true，否则返回false。
     * @throws \think\db\exception\DbException
     */
    public function issetLog($uid, $merId)
    {
        // 使用数据库查询方法，根据$merId, $uid和send_type为0的条件，限制查询结果为一条，然后检查查询结果的数量是否大于0。
        return StoreServiceLog::getDB()->where('mer_id', $merId)->where('uid', $uid)->where('send_type', 0)->limit(1)->count() > 0;
    }

    /**
     * 标记服务为已读
     *
     * 本函数用于更新特定商户、用户、服务ID的服务日志状态，将其标记为已读。
     * 通过查询条件筛选出特定的服务日志记录，确保服务类型不为1的情况下，更新服务类型为1。
     * 这样的设计可能是为了区分服务的阅读状态，避免某些操作被错误地标记为已读。
     *
     * @param string $merId 商户ID，用于限定查询的商户范围
     * @param string $uid 用户ID，用于限定查询的用户范围
     * @param string $serviceId 服务ID，用于限定查询的具体服务
     * @return int 返回更新操作影响的行数，用于确认操作是否成功
     * @throws \think\db\exception\DbException
     */
    public function serviceRead($merId, $uid, $serviceId)
    {
        // 使用where子句构建查询条件，确保查询到正确的服务日志记录
        // 并通过update方法更新这些记录的服务类型为1，表示已读
        return StoreServiceLog::getDB()->where('mer_id', $merId)->where('uid', $uid)->where('service_id', $serviceId)->where('service_type', '<>', 1)->update(['service_type' => 1]);
    }

    /**
     * 根据条件搜索商店服务日志。
     *
     * 该方法用于查询商店服务日志数据库，根据传入的条件进行过滤。支持的条件包括用户ID（uid）、商家ID（mer_id）、
     * 最后一条日志ID（last_id）和服务ID（service_id）。每个条件都是可选的，只有当相应条件的值被设置且不为空时，
     * 才会应用该条件的查询过滤。
     *
     * @param array $where 查询条件数组，包含可能的过滤条件：uid、mer_id、last_id和服务_id。
     * @return BaseQuery|\think\db\Query
     */
    public function search(array $where)
    {
        // 获取数据库查询对象
        return StoreServiceLog::getDB()->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            // 如果设置了用户ID，则添加用户ID的查询条件
            $query->where('uid', $where['uid']);
        })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            // 如果设置了商家ID，则添加商家ID的查询条件
            $query->where('mer_id', $where['mer_id']);
        })->when(isset($where['last_id']) && $where['last_id'] !== '', function ($query) use ($where) {
            // 如果设置了最后一条日志ID，则添加服务日志ID小于指定ID的查询条件
            $query->where('service_log_id', '<', $where['last_id']);
        })->when(isset($where['service_id']) && $where['service_id'] !== '', function ($query) use ($where) {
            // 如果设置了服务ID，则添加服务ID的查询条件
            $query->where('service_id', $where['service_id']);
        });
    }

    /**
     * 获取用户最近一次的服务ID
     *
     * 本函数用于查询指定用户和商家的最近一次服务记录的ID。
     * 通过查询StoreServiceLog表，根据商家ID（merId）和用户ID（uid），
     * 以服务日志ID降序的方式获取最新的服务ID。
     *
     * @param string $merId 商家ID，用于指定查询哪个商家的服务记录
     * @param string $uid 用户ID，用于指定查询哪个用户的服务记录
     * @return mixed 返回最近一次服务的ID，如果不存在则返回null
     */
    public function getLastServiceId($merId, $uid)
    {
        // 使用数据库查询工具，指定查询条件为商家ID和用户ID，按服务日志ID降序排列，返回最新的服务ID
        return StoreServiceLog::getDB()->where('mer_id', $merId)->order('service_log_id DESC')->where('uid', $uid)->value('service_id');
    }

    /**
     * 获取商家列表查询
     *
     * 本函数用于构造查询指定用户所关联的商家列表的数据库查询语句。
     * 通过用户ID（$uid）筛选出相关数据，并对结果按商家ID（mer_id）进行分组。
     * 这样做的目的是为了获取每个商家的相关信息，避免数据重复，便于后续处理和展示商家列表。
     *
     * @param int $uid 用户ID，用于查询该用户关联的商家信息。
     * @return \think\db\Query 返回一个数据库查询对象，该对象包含了根据用户ID筛选并按商家ID分组的查询条件。
     */
    public function getMerchantListQuery($uid)
    {
        // 使用StoreServiceLog类中的getDB方法获取数据库操作对象，并链式调用where和group方法构造查询语句
        return StoreServiceLog::getDB()->where('uid', $uid)->group('mer_id');
    }

    /**
     * 根据服务ID获取用户列表查询
     *
     * 本函数用于构建查询特定服务ID的用户列表的数据库查询条件。它不直接执行查询，
     * 而是返回一个构建好的查询对象，以便进一步定制查询条件或执行查询。
     *
     * @param int $serviceId 服务ID，用于指定查询哪个服务的用户列表。
     * @return \yii\db\ActiveQuery 返回一个ActiveQuery对象，该对象包含了根据服务ID筛选的查询条件。
     */
    public function getUserListQuery($serviceId)
    {
        // 通过StoreServiceLog类的getDB方法获取数据库连接对象，并链式调用where方法指定查询条件
        return StoreServiceLog::getDB()->where('service_id', $serviceId);
    }

    /**
     * 根据商家ID获取商家用户列表
     *
     * 本函数通过查询StoreServiceLog中的数据，来获取指定商家ID下的用户列表。
     * 主要用于商家服务记录的查询与管理，以便商家能够查看与其相关的用户服务记录。
     *
     * @param string $merId 商家ID，用于查询指定商家的服务记录。
     * @return \Illuminate\Database\Query\Builder|Collection 返回查询结果，可以是查询构建器对象或集合。
     */
    public function getMerchantUserList($merId)
    {
        // 通过StoreServiceLog的getDB方法获取数据库查询构建器，并指定查询条件为mer_id等于$merId
        return StoreServiceLog::getDB()->where('mer_id', $merId);
    }

    /**
     * 获取指定商户和用户最后一次服务日志
     *
     * 本函数通过查询StoreServiceLog模型，找到指定商户(merId)和用户(uid)的最后一次服务日志。
     * 它使用了数据库查询语句来过滤结果，并按照service_log_id降序排列，确保返回的是最新的日志记录。
     *
     * @param string $merId 商户ID，用于指定查询哪个商户的服务日志
     * @param string $uid 用户ID，用于指定查询哪个用户的服务日志
     * @return array 返回最后一次服务日志的信息，如果不存在则返回空数组
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getLastLog($merId, $uid)
    {
        // 使用where方法指定查询条件，查询指定商户和用户的服务日志
        // 并通过order方法按service_log_id降序排列，确保获取最新的日志记录
        // 最后使用find方法查找并返回第一条符合条件的数据
        return StoreServiceLog::getDB()->where('mer_id', $merId)->where('uid', $uid)->order('service_log_id DESC')->find();
    }

    /**
     * 获取未读消息数量
     * 该方法用于查询特定用户、商家和消息类型的未读消息数量。
     * @param $merId 商家ID，用于指定查询哪个商家的消息
     * @param $uid 用户ID，用于指定查询哪个用户的消息
     * @param $sendType 消息发送类型，用于进一步筛选消息
     * @return int 返回未读消息的数量
     */
    public function getUnReadNum($merId, $uid, $sendType)
    {
        // 根据传入的参数，使用条件查询未读消息数量
        // 其中，查询条件包括用户ID、商家ID、消息发送类型以及消息类型（当sendType为真时查询type，否则查询service_type）
        return StoreServiceLog::getDB()->where('uid', $uid)->where('mer_id', $merId)->where('send_type', $sendType)->where($sendType ? 'type' : 'service_type', 0)->count();
    }

    /**
     * 计算用户未读消息的数量
     *
     * 本函数用于查询并返回指定用户ID的未读消息总数。未读消息是指发送类型为1，消息类型为0的那些消息。
     * 这种统计方式适用于需要区分用户并关注其未读消息数量的场景，例如在商城应用中，用户可能需要知道有多少未读的系统通知。
     *
     * @param int $uid 用户的唯一标识符。这个参数用于指定查询哪个用户的未读消息数量。
     * @return int 返回未读消息的数量。这个数量是根据查询条件计算得出的，只包括发送类型为1，消息类型为0的消息。
     */
    public function totalUnReadNum($uid)
    {
        // 使用数据库查询方法，根据条件统计未读消息的数量
        return StoreServiceLog::getDB()->where('uid', $uid)->where('send_type', 1)->where('type', 0)->count();
    }

}
