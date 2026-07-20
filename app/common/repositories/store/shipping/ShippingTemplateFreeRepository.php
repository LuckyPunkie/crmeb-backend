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
use app\common\dao\store\shipping\ShippingTemplateFreeDao as dao;

class ShippingTemplateFreeRepository extends BaseRepository
{

    /**
     * ShippingTemplateFreeRepository constructor.
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
     * 更新或创建运输模板免费配送信息。
     *
     * 此方法主要用于处理运输模板免费配送信息的更新与创建。它遍历传入的数据数组，
     * 对于每个项，如果存在运输模板免费配送ID，则更新该条信息；否则，创建新的配送信息。
     * 这个方法解决了同时处理更新和创建操作的需求，避免了对数据库进行不必要的读写操作。
     *
     * @param int $id 运输模板ID，用于创建新配送信息时作为关联ID。
     * @param array $data 包含多个配送信息项的数组，每个项可能包含运输模板免费配送ID和城市ID等信息。
     */
    public function update($id,$data)
    {
        // 遍历传入的数据数组
        foreach ($data as $item){
            // 检查当前项是否已存在运输模板免费配送ID
            if(isset($item['shipping_template_free_id']) && $item['shipping_template_free_id']){
                // 将城市ID转换为字符串格式，用作数据库中的字段值
                $item['city_id'] = implode('/',$item['city_id']);
                // 更新已存在的运输模板免费配送信息
                $this->dao->update($item['shipping_template_free_id'],$item);
            }else{
                // 为新创建的配送信息设置运输模板ID
                $item['temp_id'] = $id;
                // 创建新的运输模板免费配送信息
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
