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
use app\common\dao\store\shipping\ShippingTemplateRegionDao as dao;

class ShippingTemplateRegionRepository extends BaseRepository
{

    /**
     * ShippingTemplateRegionRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 检查商家是否存在指定的运输模板。
     *
     * 本函数用于确认一个商家是否拥有特定ID的运输模板。它首先通过ID从数据访问对象（DAO）获取模板信息，
     * 然后利用依赖注入获取ShippingTemplateRepository实例，通过这个实例来检查商家是否存在指定的模板ID。
     * 这种设计模式使得代码更加灵活，易于维护和测试。
     *
     * @param int $merId 商家ID，用于确认商家身份。
     * @param int $id 指定的运输模板ID，需要确认商家是否拥有这个模板。
     * @return bool 如果商家存在指定的运输模板，则返回true，否则返回false。
     */
    public function merExists($merId , $id)
    {
        // 通过ID从DAO获取模板信息
        $result = $this->dao->get($id);

        // 通过依赖注入创建ShippingTemplateRepository实例
        $make = app()->make(ShippingTemplateRepository::class);

        // 如果模板信息存在，则检查商家是否存在指定的模板ID
        if ($result)
            return $make->merExists($merId,$result['temp_id']);

        // 如果模板信息不存在，则直接返回false
        return false;
    }

    /**
     *  暂未使用
     * @Author:Qinii
     * @Date: 2020/5/8
     * @param $id
     * @param $data
     */
    public function update($id,$data)
    {
        foreach ($data as $item) {
            if(isset($item['shipping_template_region_id']) && $item['shipping_template_region_id']){
                $item['city_id'] = implode('/',$item['city_id']);
                $this->dao->update($item['shipping_template_region_id'],$item);
            }else{
                $item['temp_id'] = $id;
                $this->dao->create($item);
            }
        }
    }

    /**
     * 批量插入数据。
     *
     * 本方法用于一次性插入多条数据记录。它通过调用DAO层的insertAll方法来实现，
     * 提高了数据插入的效率，减少了数据库操作的次数。此方法适用于需要批量导入或添加数据的场景。
     *
     * @param array $data 包含多条数据记录的数组，每条记录应为一个数组。
     *                    数组的每个元素代表一条数据，元素的键应与数据库表的字段名对应。
     * @return bool 插入操作的结果。成功返回true，失败返回false。
     */
    public function insertAll(array $data)
    {
        return $this->dao->insertAll($data);
    }

}
