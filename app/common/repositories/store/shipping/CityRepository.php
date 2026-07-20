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

namespace app\common\repositories\store\shipping;

use app\common\repositories\BaseRepository;
use app\common\dao\store\shipping\CityDao as dao;

class CityRepository extends BaseRepository
{

    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取格式化后的分类列表
     *
     * 本函数通过传入的条件数组，查询所有符合条件的数据，并将这些数据
     * 根据城市ID和父ID的关系进行格式化处理，返回一个层次化的分类列表。
     * 这种格式化处理有利于后续在前端展示或进行其他需要分类层次结构的操作。
     *
     * @param array $where 查询条件数组，用于筛选数据
     * @return array 格式化后的分类列表，具有层次结构
     */
    public function getFormatList(array $where = [])
    {
        // 调用DAO层的方法获取所有符合条件的数据，然后转换为数组格式
        // 这里的formatCategory函数用于将数据根据'city_id'和'parent_id'两个字段进行格式化
        return formatCategory($this->dao->getAll($where)->toArray(), 'id','parent_id');
    }




}
