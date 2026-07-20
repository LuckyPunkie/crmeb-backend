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
use app\common\model\BaseModel;
use app\common\model\wechat\WechatReply;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class WechatReplyDao
 * @package app\common\dao\wechat
 * @author xaboy
 * @day 2020-04-24
 */
class WechatReplyDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return WechatReply::class;
    }

    /**
     * 根据回复关键字获取微信回复对象
     *
     * 本函数旨在通过给定的关键字，在微信回复数据库中查找相应的回复配置。
     * 这对于根据用户的输入关键字，动态生成相应的回复内容至关重要。
     *
     * @param string $key 关键字，用于在数据库中查询对应的微信回复配置。
     * @return WechatReply|null 返回找到的微信回复对象，如果未找到则返回null。
     */
    public function keyByReply(string $key)
    {
        // 使用关键字查询数据库中的微信回复配置，并尝试获取第一条匹配的数据。
        return WechatReply::where('key', $key)->find();
    }

    /**
     * 根据条件搜索微信回复配置
     *
     * 本函数用于查询微信回复数据库表中不被隐藏的记录，可以根据关键字进行模糊搜索。
     * 主要用于在管理界面提供搜索功能，帮助用户快速定位特定的微信回复配置。
     *
     * @param array $where 搜索条件数组，其中可能包含关键字 keyword。
     * @return \think\db\Query 查询对象，可用于进一步的查询操作。
     */
    public function search(array $where)
    {
        // 初始化查询对象，查询不被隐藏的微信回复记录
        $query = WechatReply::getDB()->where('hidden', 0);

        // 如果搜索关键字存在，则进行模糊搜索
        if (isset($where['keyword']) && $where['keyword']) {
            $query->whereLike('key', "%{$where['keyword']}%");
        }

        // 返回查询对象
        return $query;
    }

    /**
     * 删除指定ID的记录
     *
     * 此方法用于根据给定的ID删除数据库中的一条记录。它首先通过调用getModel方法获取模型实例，
     * 然后使用该实例的getDB方法来获取数据库对象。接下来，它构造一个查询，指定主键为$id$，
     * 并且确保被删除的记录的'hidden'字段值为0（即未被隐藏）。最后，执行删除操作。
     *
     * @param int $id 需要被删除的记录的ID
     * @return int 返回删除操作影响的行数。如果未删除任何行，则返回0。
     */
    public function delete(int $id)
    {
        // 根据ID和'hidden'字段的值删除记录，确保只删除未隐藏的记录
        return ($this->getModel())::getDB()->where($this->getPk(), $id)->where('hidden', 0)->delete();
    }


    /**
     * 根据给定的关键字查询有效的数据并以其为键进行分组
     *
     * 本函数旨在从微信回复数据库中检索与给定关键字相关且状态为有效的数据。
     * 它通过构造一个SQL查询，该查询使用关键字进行匹配，并且只返回状态为1的记录。
     * 使用关键字的特殊查询逻辑确保了关键字在数据的`key`字段中以逗号分隔的列表中出现。
     *
     * @param string $key 要查询的关键字
     * @return array|false|null|\think\Model|\think\Model[] 带有给定关键字的有效数据数组，如果找不到则返回false，如果没有数据则返回null
     */
    public function keyByValidData($key)
    {
        // 从微信回复数据库中获取数据，应用两个条件：关键字匹配和状态为有效
        return WechatReply::getDB()->where(function ($query) use ($key) {
            // 构造一个SQL查询，该查询使用关键字进行匹配，确保关键字在`key`字段的以逗号分隔的值中出现
            $query->where('key', $key)->whereFieldRaw('CONCAT(\',\',`key`,\',\')', 'LIKE', '%,' . $key . ',%', 'OR');
        })->where('status', 1)->find();
    }
}
