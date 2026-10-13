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


namespace app\common\dao\wechat;

use app\common\dao\BaseDao;
use app\common\model\wechat\TemplateMessage;

class TemplateMessageDao extends BaseDao
{
    protected function getModel(): string
    {
        return TemplateMessage::class;
    }


    /**
     * 根据条件搜索数据。
     *
     * 该方法用于根据提供的条件数组搜索数据库中的记录。支持的条件包括状态(status)、类型(type)和关键字(keyword)。
     * 搜索逻辑是条件性的，只有当相应的条件存在且不为空时，才会应用到查询中。
     * 查询结果按照创建时间降序排序。
     *
     * @param array $where 包含搜索条件的数组。支持的条件有：status（状态）、type（类型）、keyword（关键字）。
     * @return \Illuminate\Database\Query\Builder|static 返回构建器对象或静态调用结果。
     */
    public function search(array $where)
    {
        // 获取模型对应的数据库实例。
        return ($this->getModel()::getDB())->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            // 如果提供了状态条件，则应用到查询中。
            $query->where('status', $where['status']);
        })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            // 如果提供了类型条件，则应用到查询中。
            $query->where('type', $where['type']);
        })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            // 如果提供了关键字条件，则应用到查询中，支持名称和临时ID的模糊搜索。
            $query->where(function($query) use ($where) {
                $query->where('name', 'like', '%' . $where['keyword'] . '%');
                $query->whereOr('tempid', 'like', '%' . $where['keyword'] . '%');
            });
        })->order('create_time DESC');
    }

    /**
     * 根据键值和类型获取模板消息的临时ID
     *
     * 此方法用于从数据库中检索特定类型和键值对应的模板消息临时ID。
     * 它通过查询数据库表中的相应记录，返回满足条件的模板消息的临时ID。
     *
     * @param string $key 模板消息的键值，用于唯一标识模板消息。
     * @param string $type 模板消息的类型，用于进一步筛选模板消息。
     * @return string 返回查询到的模板消息的临时ID，如果未找到则返回空字符串。
     */
    public function getTempId($key, $type)
    {
        // 通过Type和TempKey查询数据库，返回对应的TempID
        return TemplateMessage::getDB()->where(['type' => $type, 'tempkey' => $key])->value('tempid');
    }

}
