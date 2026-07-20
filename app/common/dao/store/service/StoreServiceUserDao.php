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
use app\common\model\store\service\StoreServiceUser;
use app\common\repositories\system\ExtendRepository;
use app\common\repositories\user\UserRepository;

/**
 * Class StoreServiceDao
 * @package app\common\dao\store\service
 * @author xaboy
 * @day 2020/5/29
 */
class StoreServiceUserDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/5/29
     */
    protected function getModel(): string
    {
        return StoreServiceUser::class;
    }

    /**
     * 根据条件搜索服务用户。
     *
     * 该方法提供了一个灵活的方式，根据不同的条件来搜索服务用户。支持的条件包括用户ID（uid）、商家ID（mer_id）、
     * 服务人员ID（service_id）以及关键词（keyword）。其中，关键词搜索还支持对用户和扩展信息中的关键词进行匹配。
     *
     * @param array $where 搜索条件数组，包含可能的键值对：uid, mer_id, service_id, keyword。
     * @return \Illuminate\Database\Query\Builder|static 返回构建器对象或静态调用结果。
     */
    public function search(array $where)
    {
        // 从数据库获取服务用户时，根据条件应用过滤器
        return StoreServiceUser::getDB()->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            // 如果提供了用户ID，则按用户ID过滤结果
            $query->where('uid', $where['uid']);
        })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            // 如果提供了商家ID，则按商家ID过滤结果
            $query->where('mer_id', $where['mer_id']);
        })->when(isset($where['service_id']) && $where['service_id'] !== '', function ($query) use ($where) {
            // 如果提供了服务人员ID，则按服务人员ID过滤结果
            $query->where('service_id', $where['service_id']);
        })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            // 如果提供了关键词，则按关键词搜索用户和扩展信息，并对结果进行过滤
            $uid = app()->make(UserRepository::class)->search(['keyword' => $where['keyword']])->limit(30)->column('uid');
            $uid = array_merge($uid, app()->make(ExtendRepository::class)->search([
                'keyword' => $where['keyword'],
                'type' => ExtendRepository::TYPE_SERVICE_USER_MARK,
                'mer_id' => $where['mer_id'] ?? null
            ])->column('link_id'), [0]);
            $query->whereIn('uid', array_unique($uid));
        });
    }

}
