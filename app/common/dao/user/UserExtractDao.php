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

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\UserExtract;
use app\common\model\user\UserExtract as model;

class UserExtractDao extends  BaseDao
{
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 用户提现搜索
     * @param array $where
     * @return model|\think\db\BaseQuery
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/18
     */
    public function search(array $where)
    {
        $query = model::hasWhere('user',function ($query)use($where){
            $query->where(true);
        });
        $query->when(isset($where['wechat']) && $where['wechat'] !== '',function($query)use($where){
            $query->whereLike('User.nickname', "%{$where['wechat']}%");
        })->when(isset($where['phone']) && $where['phone'] !== '',function($query)use($where){
            $query->whereLike('User.phone', "%{$where['phone']}%");
        })->when(isset($where['nickname']) && $where['nickname'] !== '',function($query)use($where){
                $query->whereLike('User.nickname', "%{$where['nickname']}%");
        })
        ;
       $query->when(isset($where['uid']) && $where['uid'] !== '',function($query)use($where){
            $query->where('UserExtract.uid',$where['uid']);
        })->when(isset($where['extract_type']) && $where['extract_type'] !== '',function($query)use($where){
            $query->where('extract_type',$where['extract_type']);
        })->when(isset($where['keyword']) && $where['keyword'] !== '',function($query)use($where){
            //UserExtract.real_name|UserExtract.uid|
           $query->whereLike('bank_code|alipay_code|wechat',"%{$where['keyword']}%");
        })->when(isset($where['status']) && $where['status'] !== '',function($query)use($where){
            $query->where('UserExtract.status',$where['status']);
        })->when(isset($where['real_name']) && $where['real_name'] !== '',function($query)use($where){
            $query->where('UserExtract.real_name','%'.$where['real_name'].'%');
        })->when(isset($where['date']) && $where['date'] !== '',function($query)use($where){
            getModelTime($query, $where['date'],'UserExtract.create_time');
        })->when(isset($where['brokerage_level']) && $where['brokerage_level'], function ($query) use ($where) {
            $query->join('User user', 'UserExtract.uid = user.uid', 'left')->where('user.brokerage_level', intval($where['brokerage_level']));
       })->when(isset($where['user_keyword']) && $where['user_keyword'], function ($query) use ($where) {
           $query->join('User user', 'UserExtract.uid = user.uid', 'left')->where('user.uid|user.real_name|user.nickname|user.phone', 'like', '%' . $where['keyword'] . '%');
       })->order('create_time DESC');

        return $query;
    }

    /**
     * 获取推广员信息
     *
     * 本函数用于根据一组推广员用户ID，查询这些推广员的总提取金额和总提取次数。
     * 它通过数据库查询来实现，并返回一个包含每个推广员相关统计数据的数组。
     *
     * @param array $uids 推广员用户ID数组
     * @return array 包含每个推广员的总提取金额、总提取次数和用户ID的数组
     */
    public function getPromoterInfo(array $uids)
    {
        // 通过UserExtract模型获取数据库操作对象，然后进行查询
        return UserExtract::getDB()->field('sum(extract_price) as total_price,count(extract_id) as total_num, uid')->whereIn('uid', $uids)->group('uid')->where('status', 1)->select()->toArray();
    }
}
