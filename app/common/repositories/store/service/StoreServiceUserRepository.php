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


use app\common\dao\store\service\StoreServiceUserDao;
use app\common\model\store\service\StoreServiceLog;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;

/**
 * Class StoreServiceRepository
 * @package app\common\repositories\store\service
 * @author xaboy
 * @day 2020/5/29
 * @mixin StoreServiceUserDao
 */
class StoreServiceUserRepository extends BaseRepository
{
    /**
     * StoreServiceRepository constructor.
     * @param StoreServiceUserDao $dao
     */
    public function __construct(StoreServiceUserDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 更新服务信息。
     *
     * 本函数用于根据提供的服务日志对象更新相关服务用户的信息。如果该服务用户不存在，则创建新记录；
     * 如果存在，则根据是否为服务人员更新未读消息计数，并更新最后一条日志ID和时间戳。
     *
     * @param StoreServiceLog $log 服务日志对象，包含服务ID、用户ID和商家ID等信息。
     * @param bool $isService 指示当前操作是否为服务人员的操作，用于区分更新哪一方的未读消息计数。
     * @return StoreServiceLog 返回更新后的服务用户对象。
     */
    public function updateInfo(StoreServiceLog $log, $isService)
    {
        // 根据服务日志信息尝试获取已存在的服务用户记录
        $serviceUser = $this->dao->getWhere(['service_id' => $log->service_id, 'uid' => $log->uid, 'mer_id' => $log->mer_id]);

        // 如果服务用户不存在，则创建新记录，并初始化相关字段
        if (!$serviceUser) {
            $serviceUser = $this->dao->create([
                'service_id' => $log->service_id,
                'uid' => $log->uid,
                'mer_id' => $log->mer_id,
                'service_unread' => $isService ? 1 : 0,
                'user_unread' => $isService ? 0 : 1,
                'is_online' => 1,
                'last_log_id' => $log->service_log_id,
                'last_time' => date('Y-m-d H:i:s')
            ]);
        } else {
            // 如果服务用户已存在，根据$isService的值更新服务人员或用户的未读消息计数
            $isService ? $serviceUser->service_unread++ : $serviceUser->user_unread++;
            // 更新最后一条日志ID和时间戳
            $serviceUser->last_log_id = $log->service_log_id;
            $serviceUser->last_time = date('Y-m-d H:i:s');
            // 设置在线状态为1
            $serviceUser->is_online = 1;
            // 保存更新后的服务用户信息
            $serviceUser->save();
        }

        // 返回更新或创建后的服务用户对象
        return $serviceUser;
    }

    /**
     * 读取消息方法
     * 该方法用于标记指定用户的消息为已读。它可以处理用户消息和客服消息两种类型。
     *
     * @param string $merId 商户ID，用于确定消息所属的商户。
     * @param string $uid 用户ID，用于确定要标记已读消息的用户。
     * @param bool $isService 是否为客服消息，默认为null，表示用户消息。如果设置为true，则表示处理客服消息。
     */
    public function read($merId, $uid, $isService = null)
    {
        // 根据$isService的值决定更新user_unread还是service_unread字段
        $field = $isService ? 'service_unread' : 'user_unread';

        // 查询满足条件的记录并更新相应的未读消息字段为0，表示已读
        $this->dao->search([
            'mer_id' => $merId,
            'uid' => $uid,
        ])->update([$field => 0]);
    }

    /**
     * 获取用户的商家列表
     *
     * 根据用户ID（$uid）获取该用户关注的商家列表，支持分页和数量限制。
     * 商家列表按照最后更新时间降序排列。同时，还包括每个商家的未读消息总数。
     *
     * @param int $uid 用户ID
     * @param int $page 当前页码
     * @param int $limit 每页显示数量
     * @return array 包含商家总数和商家列表的数组
     */
    public function userMerchantList($uid, $page, $limit)
    {
        // 构建查询条件，筛选出属于用户$uid的商家，按最后更新时间降序排列
        $query = $this->dao->search(['uid' => $uid])->group('mer_id')->order('last_time DESC');

        // 计算符合条件的商家总数
        $count = $query->count();

        // 查询商家列表，包括商家基本信息和最新的消息信息
        // 使用with语法加载关联数据，简化查询并优化性能
        $list = $query->with(['merchant' => function ($query) {
            // 仅加载商家ID、头像和名称，优化数据库查询性能
            $query->field('mer_id,mer_avatar,mer_name');
        }, 'last'])->page($page, $limit)->setOption('field', [])->field('*,max(last_log_id) as last_log_id,sum(user_unread) as num')->select()->toArray();

        // 加载系统配置，用于后续设置默认商家头像和名称
        $config = systemConfig(['site_logo', 'site_name']);

        // 遍历商家列表，对于默认商家（mer_id为0）设置系统默认头像和名称
        foreach ($list as &$item) {
            if ($item['mer_id'] == 0) {
                $item['merchant'] = [
                    'mer_avatar' => $config['site_logo'],
                    'mer_name' => $config['site_name'],
                    'mer_id' => 0,
                ];
            }
        }
        unset($item);

        // 返回商家总数和商家列表的数组
        return compact('count', 'list');
    }


    /**
     * 获取商家用户列表
     * 该方法用于根据商家ID和用户ID，以及分页信息，获取特定商家的用户列表。
     * 这是通过验证商家服务状态，并查询相应的用户列表来实现的。
     *
     * @param int $merId 商家ID，用于确定要查询的商家。
     * @param int $uid 用户ID，用于确定要查询的用户。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数量，用于分页查询。
     * @return array 返回符合查询条件的商家用户列表。
     * @throws ValidateException 如果没有查询权限或商家服务状态不在线，则抛出异常。
     */
    public function merUserList($merId, $uid, $page, $limit)
    {
        // 通过商家ID和用户ID获取商家服务实例
        $service = app()->make(StoreServiceRepository::class)->getService($uid, $merId);

        // 验证是否有查询权限，如果没有则抛出异常
        if (!$service)
            throw new ValidateException('没有权限');

        // 验证商家服务是否在线，如果离线则抛出异常
        if (!$service['status'])
            throw new ValidateException('客服已离线，请开启客服状态');

        // 调用内部方法查询商家用户列表，并返回结果
        return $this->serviceUserList(['service_id' => $service->service_id], $merId, $page, $limit);
    }

    /**
     * 获取服务人员列表
     * 根据给定的条件、分页和限制，查询服务人员列表及其相关信息。
     *
     * @param string $where 查询条件
     * @param int $merId 商家ID
     * @param int $page 当前页码
     * @param int $limit 每页记录数
     * @return array 返回包含总数和列表的数组
     */
    public function serviceUserList($where, $merId, $page, $limit)
    {
        // 构建查询语句，根据条件进行搜索，并按最后活跃时间降序排列
        $query = $this->dao->search($where)->group('uid')->order('last_time DESC');

        // 计算满足条件的总记录数
        $count = $query->count();

        // 分页查询，并加载关联数据，包括用户信息、标记信息和最后一条消息
        $list = $query->page($page, $limit)->with([
            'user' => function ($query) {
                // 加载用户详细信息，包括用户类型、性别、是否推广员等，并加载推广人信息
                $query->field('uid,avatar,nickname,user_type,sex,is_promoter,phone,now_money,phone,birthday,spread_uid')->with([
                    'spread' => function ($query) {
                        // 加载推广人的基本信息
                        $query->field('uid,avatar,nickname,cancel_time');
                    }
                ]);
            },
            'mark' => function ($query) use ($merId) {
                // 根据商家ID加载标记信息
                $query->where('mer_id', $merId);
            },
            'last'
        ])->setOption('field', [])->field('*,max(last_log_id) as last_log_id,sum(service_unread) as num')->select()->toArray();
        foreach ($list as &$item) {
            $m = $item['mark']['extend_value'] ?? '';
            unset($item['mark']);
            $item['mark'] = $m;
        }

        // 如果列表中第一个元素的服务用户ID为null，则说明查询结果不正常，返回空列表
        if (count($list) && is_null($list[0]['service_user_id'])) {
            $list = [];
        }

        // 返回包含总数和列表的数组
        return compact('count', 'list');
    }

    /**
     * 更新用户在线状态
     *
     * 本函数用于根据用户ID更新用户的在线状态。它首先通过搜索找到特定用户，然后更新该用户的在线状态。
     * 在线状态通常是一个布尔值，表示用户当前是否在线。
     *
     * @param int $uid 用户ID。这是用于唯一标识用户的数值。
     * @param bool $online 用户的在线状态。true表示用户在线，false表示用户不在线。
     * @return bool 更新操作的结果。成功返回true，失败返回false。
     */
    public function online($uid, $online)
    {
        // 根据$uid搜索用户，并更新is_online字段为$online值
        return $this->dao->search(['uid' => $uid])->update(['is_online' => $online]);
    }


    /**
     * 将在线状态设置为离线
     *
     * 本函数用于更新指定记录的在线状态，将 `is_online` 字段的值从 1 更改为 0。
     * 它不接受任何参数，直接调用 DAO（数据访问对象）进行查询和更新操作。
     * 查询条件是 `is_online` 等于 1，这意味着只会影响之前处于在线状态的记录。
     *
     * @return int 返回影响的行数，这可以帮助判断操作是否成功。
     */
    public function onlineDown()
    {
        // 使用DAO的search方法初始化查询，然后通过where方法指定查询条件。
        // 最后，通过update方法更新查询结果集中的`is_online`字段为0，即将在线状态改为离线。
        return $this->dao->search([])->where(['is_online' => 1])->update(['is_online' => 0]);
    }

}
