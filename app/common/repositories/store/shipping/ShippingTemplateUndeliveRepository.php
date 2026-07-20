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
use app\common\dao\store\shipping\ShippingTemplateUndeliveryDao as dao;

class ShippingTemplateUndeliveRepository extends BaseRepository
{

    /**
     * ShippingTemplateUndeliveRepository constructor.
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
     * 更新或创建运输模板未送达地址信息。
     *
     * 根据传入的$id和$data，如果$data中包含shipping_template_undelivery_id字段且不为空，
     * 则将city_id字段合并为字符串，并更新对应的运输模板未送达地址信息。
     * 如果$data中不包含shipping_template_undelivery_id字段，则认为是新建记录，
     * 将$id赋值给temp_id字段，并创建新的运输模板未送达地址信息。
     *
     * @param int $id 运输模板的ID。
     * @param array $data 包含运输模板未送达地址信息的数据数组。
     */
    public function update($id,$data)
    {
        // 检查$data中是否包含shipping_template_undelivery_id且不为空
        if(isset($data['shipping_template_undelivery_id']) && $data['shipping_template_undelivery_id']){
            // 将city_id数组转换为字符串格式，用于更新操作
            $data['city_id'] = implode('/',$data['city_id']);
            // 更新已存在的运输模板未送达地址信息
            $this->dao->update($data['shipping_template_undelivery_id'],$data);
        }else{
            // 为新记录设置temp_id字段
            $data['temp_id'] = $id;
            // 创建新的运输模板未送达地址信息
            $this->dao->create($data);
        }
    }



}
