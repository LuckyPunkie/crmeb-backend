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


namespace app\common\dao\system\notice;


use app\common\dao\BaseDao;
use app\common\model\system\notice\SystemNotice;

class SystemNoticeDao extends BaseDao
{

    protected function getModel(): string
    {
        return SystemNotice::class;
    }

    /**
     * 根据条件搜索系统通知
     *
     * 本函数用于根据提供的条件搜索系统通知。支持的条件包括关键字和日期。
     * - 关键字搜索：通过关键词在通知标题和内容中进行模糊搜索。
     * - 日期搜索：根据指定的日期范围筛选通知。
     *
     * @param array $where 搜索条件数组，包含关键字(keyword)和日期(date)等条件。
     * @return \think\Paginator 返回搜索结果的分页对象，包含符合搜索条件的通知。
     */
    public function search(array $where)
    {
        // 从系统通知模型中获取数据库实例
        return SystemNotice::getDB()->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            // 如果提供了关键字，则在通知标题和内容中进行模糊搜索
            $query->whereLike('notice_title|notice_content', '%' . $where['keyword'] . '%');
        })->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            // 如果提供了日期，则根据日期进行筛选
            getModelTime($query, $where['date'], 'create_time');
        })->where('is_del', 0);
    }
}
