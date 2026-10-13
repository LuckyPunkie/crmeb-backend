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

namespace app\common\dao\circle;

use app\common\dao\BaseDao;
use crmeb\traits\CategoresDao;
use app\common\model\circle\Circle;

class CircleDao extends BaseDao
{
    use CategoresDao;

    const TYPE = [
        "REGION" => 0, // 区域
        "MERCHANT" => 1, // 商户
    ];

    protected function getModel(): string
    {
        return Circle::class;
    }

    public function search(array $where)
    {
        $query = ($this->getModel()::getDB());

        $query->when(isset($where['circle_agent_id']) && $where['circle_agent_id'] !== '', function ($query) use ($where) {
            $query->where('circle_agent_id', $where['circle_agent_id']);
        })->when(isset($where['name']) && $where['name'] !== '', function ($query) use ($where) {
            $query->whereLike('name', "%{$where['name']}%");
        })->when(isset($where['create_time']) && !empty($where['create_time']), function ($query) use ($where) {
            $query->whereBetween('create_time', $where['create_time']);
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', $where['status']);
        })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            $query->where('type', $where['type']);
        });

        $query->order('sort DESC, create_time DESC');

        return $query;
    }

    public function getStatus(): string
    {
        return 'status';
    }

    public function getAllOptions($status = 1, $level = null, $type = '', $circleAgentId = 0)
    {
        $field = $this->getParentId().',name, sort';
        $query = ($this->getModel()::getDB());
        $query->when($status,function($query)use($status){
                $query->where($this->getStatus(),$status);
            })->when(($level != '' && $level != null),function($query)use($level){
                $query->where($this->getLevel(),'<',$level);
            })->when((isset($type) && $type !== ''), function ($query) use ($type) {
                $query->where('type', $type);
            })->when((isset($circleAgentId) && $circleAgentId !== 0), function ($query) use ($circleAgentId) {
                $query->where('circle_agent_id', $circleAgentId);
            });
        return $query->order('sort DESC,'.$this->getPk().' DESC')->column($field, $this->getPk());
    }
}
