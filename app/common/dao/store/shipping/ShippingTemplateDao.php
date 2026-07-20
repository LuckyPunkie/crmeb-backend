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

namespace app\common\dao\store\shipping;

use app\common\repositories\store\shipping\ShippingTemplateFreeRepository;
use app\common\repositories\store\shipping\ShippingTemplateRegionRepository;
use app\common\repositories\store\shipping\ShippingTemplateUndeliveRepository;
use app\common\repositories\system\merchant\MerchantAdminRepository;
use crmeb\jobs\ClearMerchantStoreJob;
use think\facade\Db;
use app\common\dao\BaseDao;
use app\common\model\store\shipping\ShippingTemplate as model;
use think\facade\Queue;

class ShippingTemplateDao  extends BaseDao
{
    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 根据条件搜索信息。
     *
     * 本函数用于根据给定的商家ID和额外的搜索条件，从数据库中检索相关信息。
     * 它支持根据商家名称和类型进行模糊搜索或精确搜索。
     *
     * @param int $merId 商家ID，用于限定搜索范围。
     * @param array $where 搜索条件数组，包含可选的名称和类型条件。
     * @return \think\db\Query 返回一个查询对象，该对象可用于进一步的查询操作或数据检索。
     */
    public function search(int $merId,array $where)
    {
        // 初始化查询，指定商家ID并按排序降序排列
        $query = ($this->getModel()::getDB())->where('mer_id',$merId)->order('sort desc');

        // 如果指定了名称，并且名称不为空，则添加名称模糊搜索条件
        if(isset($where['name']) && !empty($where['name'])) {
            $query->where('name','like','%'.$where['name'].'%');
        }

        // 如果指定了类型，并且类型不为空，则添加类型精确搜索条件
        if(isset($where['type']) && !empty($where['type'])) {
            $query->where('type',$where['type']);
        }

        // 最终按排序降序排列，如果未指定排序则默认按照创建时间降序排列
        return $query->order('sort DESC,create_time DESC');
    }


    /**
     * 查询是否存在
     * @Author:Qinii
     * @Date: 2020/5/7
     * @param int $merId
     * @param $field
     * @param $value
     * @param null $except
     * @return bool
     */
    public function merFieldExists(int $merId, $field, $value, $except = null)
    {
       return  ($this->getModel())::getDB()->when($except, function ($query, $except) use ($field) {
                $query->where($field, '<>', $except);
            })->where('mer_id', $merId)->where($field, $value)->count() > 0;
    }

    /**
     * 关联删除
     * @Author:Qinii
     * @Date: 2020/5/7
     * @param int $id
     * @return int|void
     */
    public function delete(int $id)
    {
        $result = $this->getModel()::with(['free','region','undelives'])->find($id);
        $result->together(['free','region','undelives'])->delete();
    }

    /**
     * 批量删除
     * @Author:Qinii
     * @Date: 2020/5/8
     * @param int $id
     * @return mixed
     */
    public function batchRemove(int $id)
    {
        return ($this->getModel())::getDB()->where($this->getPk(),'in',$id)->delete();
    }

    /**
     * 获取商家的物流模板列表
     *
     * 本函数用于查询指定商家ID对应的物流模板信息。它通过构建数据库查询条件，
     * 筛选出商家ID对应的物流模板，并按照排序和创建时间进行降序排列，最终返回查询结果。
     *
     * @param int $merId 商家ID，用于确定查询的商家范围
     * @return array 返回包含物流模板信息的数组，每个元素包含shipping_template_id、name、is_default字段
     */
    public function getList($merId)
    {
        // 调用getModel方法获取模型实例，并直接进行数据库查询
        // 查询条件为mer_id等于$merId，返回字段包括shipping_template_id、name、is_default
        // 按照sort降序和create_time降序进行结果排序
        return ($this->getModel())::getDB()->where('mer_id',$merId)->field('shipping_template_id,name,is_default')->order('sort DESC,create_time DESC')->select();
    }

    /**
     * 清理与指定字段和ID相关的运输模板数据。
     *
     * 此函数用于根据给定的字段ID清理相关的运输模板数据，包括删除模板本身以及与该模板相关的所有区域、未投递和免费运输设置。
     * 使用事务确保数据一致性和完整性。
     *
     * @param int $id 主键ID，用于查询和删除相关数据。
     * @param string $field 字段名，用于查询和删除相关数据。
     */
    public function clear($id,$field)
    {
        // 查询与指定字段和ID相关的运输模板ID。
        $shipping_template_id = $this->getModel()::getDB()->where($field, $id)->column('shipping_template_id');

        // 使用事务处理来确保相关操作的原子性。
        Db::transaction(function () use ($id,$field,$shipping_template_id) {
            // 删除指定字段和ID的运输模板记录。
            $this->getModel()::getDB()->where($field, $id)->delete();

            // 删除与运输模板ID相关的所有免费运输设置。
            app()->make(ShippingTemplateFreeRepository::class)->getSearch([])->whereIn('temp_id',$shipping_template_id)->delete();

            // 删除与运输模板ID相关的所有区域设置。
            app()->make(ShippingTemplateRegionRepository::class)->getSearch([])->whereIn('temp_id',$shipping_template_id)->delete();

            // 删除与运输模板ID相关的所有未投递设置。
            app()->make(ShippingTemplateUndeliveRepository::class)->getSearch([])->whereIn('temp_id',$shipping_template_id)->delete();
        });
    }


    /**
     * 设置默认模板
     * @param $merId
     * @param $id
     * @return bool
     *
     * @date 2023/10/07
     * @author yyw
     */
    public function setDefault($merId, $id)
    {
        ($this->getModel())::getDB()->where('mer_id', $merId)->where('is_default', 1)->update(['is_default' => 0]);
        ($this->getModel())::getDB()->where($this->getPk(), $id)->update(['is_default' => 1]);
        return true;
    }

}
