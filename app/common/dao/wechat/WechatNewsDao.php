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

namespace app\common\dao\wechat;

use app\common\dao\BaseDao;
use app\common\model\wechat\WechatNews;

class WechatNewsDao extends BaseDao
{

    protected function getModel(): string
    {
        return WechatNews::class;
    }

    /**
     * 根据主键查询
     * @param int $merId
     * @param int $id
     * @param null $except
     * @return bool
     * @author Qinii
     */
    public function merExists(int $merId, int $id, $except = null)
    {
        return $this->merFieldExists($merId, $this->getPk(), $id, $except);
    }

    /**
     * 根据 字段名查询
     * @param int $merId
     * @param $field
     * @param $value
     * @param null $except
     * @return bool
     * @author Qinii
     */
    public function merFieldExists(int $merId, $field, $value, $except = null)
    {
        return ($this->getModel())::getDB()->when($except, function ($query, $except) use ($field) {
                $query->where($field, '<>', $except);
            })->where('mer_id', $merId)->where($field, $value)->count() > 0;
    }

    /**
     * 根据条件获取所有的微信新闻信息。
     *
     * 此方法主要用于查询微信新闻及相关文章的信息。通过接收一个数组参数$where，
     * 来确定查询条件。特别地，如果数组中包含'cate_name'键，并且其值不为空，
     * 则会进一步根据 cate_name 来模糊查询文章标题。
     *
     * @param array $where 查询条件数组，可以包含任何用于筛选新闻的条件。
     * @return \Illuminate\Database\Eloquent\Builder|WechatNews 查询结果，包含微信新闻及其相关文章。
     */
    public function getAll(array $where)
    {
        // 当$where数组中包含'cate_name'且不为空时，进行模糊查询
        if(isset($where['cate_name']) && $where['cate_name'] !== ''){
            $query = WechatNews::hasWhere('article',function ($query)use($where){
                // 在文章中模糊搜索标题包含$cate_name的记录
                $query->whereLike('title',"%{$where['cate_name']}%");
            });
        }else{
            // 如果没有指定cate_name，则直接查询微信新闻表
            $query = WechatNews::alias('WechatNews');
        }

        // 带上相关文章信息
        $query->with('article');

        // 按照创建时间降序排序
        return $query->order('WechatNews.create_time DESC');
    }

    /**
     * 根据ID和商家ID获取数据
     *
     * 本函数用于从数据库中检索指定ID和商家ID对应的数据。它通过调用getModel方法获取数据模型实例，
     * 并使用该实例的getDB方法来访问数据库。查询时，会指定条件为mer_id等于传入的商家ID，
     * 并且通过with方法加载关联的article内容。最后，使用find方法根据指定的ID查找数据。
     *
     * @param int $id 需要获取的数据的ID
     * @param int $merId 商家ID，用于限定查询的数据范围，默认为0，表示不进行商家ID的限定
     * @return \think\Model|null 返回符合查询条件的数据模型对象，如果未找到数据则返回null
     */
    public function get( $id, int $merId = 0)
    {
        // 根据ID和商家ID查询数据，同时加载关联的article内容
        return ($this->getModel())::getDB()->where('mer_id',$merId)->with('article.content')->find($id);
    }

}
