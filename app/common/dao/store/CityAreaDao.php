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


namespace app\common\dao\store;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\store\CityArea;
use think\exception\ValidateException;

class CityAreaDao extends BaseDao
{

    protected function getModel(): string
    {
        return CityArea::class;
    }

    public function search(array $where)
    {
        return CityArea::getDB()->when(isset($where['pid']) && $where['pid'] !== '', function ($query) use ($where) {
            $query->where('parent_id', $where['pid']);
        })->when(isset($where['address']) && $where['address'] !== '', function ($query) use ($where) {
            $address = explode('/', trim($where['address'], '/'));
            $p = array_shift($address);
            $_p = $p;
            if (mb_strlen($p) - 1 === mb_strpos($p, '市')) {
                $p = mb_substr($p, 0, -1);
            } elseif (mb_strlen($p) - 1 === mb_strpos($p, '省')) {
                $p = mb_substr($p, 0, -1);
            } elseif (mb_strlen($p) - 3 === mb_strpos($p, '自治区')) {
                $p = mb_substr($p, 0, -3);
            }
            $pcity = $this->search([])->where('name', $p)->find();
            if (!$pcity) $pcity = $this->search([])->where('name', $_p)->find();
            if (!$pcity) throw new ValidateException('获取地址失败'.$_p);
            $street = array_pop($address);
            if ($pcity) {
                $path = '/' . $pcity->id . '/';
                $query->whereLike('path', "/{$pcity->id}/%");
                foreach ($address as $item) {
                    $id = $this->search([])->whereLike('path', $path . '%')->where('name', $item)->value('id');
                    if ($id) {
                        $path .= $id . '/';
                    } else {
                        break;
                    }
                }
            }
            $query->whereLike('path', $path . '%')->where('name', $street);
        });
    }

    /**
     * 获取城市列表
     *
     * 本函数用于根据给定的城市区域对象，获取该城市区域的所有父级城市直到根城市的一个列表。
     * 这个功能适用于需要展示城市层级关系的场景，比如导航菜单或选择框。
     *
     * @param CityArea $city 城市区域对象，包含城市区域的信息。
     * @return array 返回一个包含城市区域对象的数组，从根城市到给定城市的父级城市。
     */
    public function getCityList(CityArea $city)
    {
        // 如果城市区域没有父级ID，则说明它是根城市，直接返回该城市自身
        if (!$city->parent_id) return [$city];

        // 通过查询找到给定城市的所有父级城市，路径用'/'分隔
        $lst = $this->search([])->where('id', 'in', explode('/', trim($city->path, '/')))->order('id ASC')->select();

        // 将给定的城市对象添加到结果列表末尾，确保列表以给定城市结尾
        $lst[] = $city;

        // 返回包含所有父级城市和给定城市的列表
        return $lst;
    }
}
