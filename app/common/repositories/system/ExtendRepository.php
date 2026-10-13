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


namespace app\common\repositories\system;


use app\common\dao\system\ExtendDao;
use app\common\repositories\BaseRepository;

/**
 * Class ExtendRepository
 * @package app\common\repositories\system
 * @author xaboy
 * @day 2020-04-24
 * @mixin ExtendDao
 */
class ExtendRepository extends BaseRepository
{

    const TYPE_SERVICE_USER_MARK = 'service_user_mark';

    /**
     * CacheRepository constructor.
     * @param ExtendDao $dao
     */
    public function __construct(ExtendDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 更新扩展信息
     * 根据给定的类型、链接ID和商家ID，更新相应的扩展信息的值。
     * 如果该扩展信息已存在，则直接更新其值；如果不存在，则创建新的扩展信息。
     *
     * @param string $extend_type 扩展信息的类型
     * @param int $link_id 链接ID，用于关联扩展信息和特定的链接
     * @param int $mer_id 商家ID，用于指定该扩展信息属于哪个商家
     * @param mixed $extend_value 扩展信息的值，具体类型取决于扩展信息的要求
     * @return object 返回更新或创建后的扩展信息对象
     */
    public function updateInfo($extend_type, $link_id, $mer_id, $extend_value)
    {
        // 组装查询数据
        $data = compact('extend_type', 'link_id', 'mer_id');

        // 根据类型、链接ID和商家ID查询现有的扩展信息
        $extend = $this->getWhere($data);

        // 如果扩展信息已存在
        if ($extend) {
            // 更新扩展信息的值，并保存
            $extend->extend_value = $extend_value;
            $extend->save();
        } else {
            // 如果扩展信息不存在，创建新的扩展信息
            $data['extend_value'] = $extend_value;
            $extend = $this->dao->create($data);
        }

        // 返回更新或创建后的扩展信息对象
        return $extend;
    }

}
