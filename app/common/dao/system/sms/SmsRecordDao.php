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


namespace app\common\dao\system\sms;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\sms\SmsRecord;
use think\db\BaseQuery;
use think\db\exception\DbException;

/**
 * Class SmsRecordDao
 * @package app\common\dao\system\sms
 * @author xaboy
 * @day 2020-05-18
 */
class SmsRecordDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return SmsRecord::class;
    }

    /**
     * 根据条件搜索短信记录
     *
     * 本函数用于根据提供的条件数组搜索短信记录。它支持通过类型字段对记录进行筛选，并且总是按照创建时间降序返回结果。
     *
     * @param array $where 搜索条件数组，可以包含多个条件，其中 'type' 字段用于指定短信类型。
     * @return BaseQuery|\think\db\Query
     */
    public function search(array $where)
    {
        // 从SmsRecord类中获取数据库对象
        return SmsRecord::getDB()->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            // 如果搜索条件中的'type'字段被设置且不为空，则添加where条件筛选结果码为指定类型的记录
            $query->where('resultcode', $where['type']);
        })->order('create_time DESC'); // 按照创建时间降序排序查询结果
    }

    /**
     * 统计短信记录的数量
     *
     * 本方法通过调用SmsRecord类的静态方法count，配合当前模型的主键值，来统计相关短信记录的数量。
     * 主要用于在不需要具体短信记录详情的情况下，快速获取短信记录的数量，例如在分页或统计功能中。
     *
     * @return int 返回短信记录的数量
     */
    public function count()
    {
        // 调用SmsRecord类的静态方法count，传入当前模型的主键值，以统计相关短信记录的数量
        return SmsRecord::count($this->getPk());
    }

    /**
     * 更新短信记录的状态
     *
     * 本函数用于根据记录ID更新短信发送记录的结果码。这在处理短信发送状态回调时特别有用，
     * 允许系统根据运营商返回的实际发送状态更新自身的记录，以便后续查询或统计。
     *
     * @param int $record_id 短信记录的唯一标识ID。这是更新记录的关键依据。
     * @param string $resultcode 短信发送的结果码。通常由运营商返回，表示短信发送的具体结果。
     * @return bool 更新操作的结果。成功返回true，失败返回false。
     */
    public function updateRecordStatus($record_id, $resultcode)
    {
        // 使用查询构建器定位到特定ID的短信记录，并更新其结果码
        return SmsRecord::getDB()->where('record_id', $record_id)->update(['resultcode' => $resultcode]);
    }

    /**
     * 获取超时记录ID列表
     *
     * 本函数用于查询并返回所有结果码为空且创建时间早于指定时间的短信记录的ID列表。
     * 这样做的目的是为了后续可能的操作，比如清理这些超时记录，或者对这些记录进行进一步的分析处理。
     *
     * @param int $time 用于查询的截止时间，以时间戳形式表示。
     * @return array 包含符合条件的短信记录ID的数组。
     */
    public function getTimeOutIds($time)
    {
        // 使用SmsRecord类的getDB方法获取数据库实例，并链式调用where方法指定查询条件，最后调用column方法获取record_id列的值。
        return SmsRecord::getDB()->where('resultcode', null)->where('create_time', '<=', $time)->column('record_id');
    }

}
