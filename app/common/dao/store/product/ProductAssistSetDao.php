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

namespace app\common\dao\store\product;

use app\common\dao\BaseDao;
use app\common\model\store\product\ProductAssistSet;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\system\merchant\MerchantRepository;
use think\Exception;

class ProductAssistSetDao extends BaseDao
{
    protected function getModel(): string
    {
        return ProductAssistSet::class;
    }


    /**
     * 增加数值统计
     * 该方法用于根据类型增加指定ID的分享数或浏览数。
     * @param int $type 类型标识，1代表增加分享数，2代表增加浏览数。
     * @param int $id 要操作的数据ID。
     * @param int $inc 增加的数值，默认为1。
     */
    public function incNum(int $type,int $id,int $inc = 1)
    {
        try{
            // 根据ID获取模型实例，并准备更新操作
            $query = $this->getModel()::where($this->getPk(),$id);

            // 根据$type的值，执行不同的增量更新操作
            if($type == 1) {
                // 增加分享数
                $query->inc('share_num',$inc)->update();
            }
            if($type == 2) {
                // 增加浏览数
                $query->inc('view_num',$inc)->update();
            }
        }catch (Exception $exception){
            // 捕获并处理异常，此处为空实现，可根据需要添加日志记录等操作
        }
    }


    /**
     * 获取用户数量及最近活跃用户的列表
     *
     * 本函数用于查询数据库中用户总数，并获取最近活跃的10位用户的信息。
     * 活跃用户的信息中包括用户ID和头像URL。
     *
     * @return array 返回包含用户总数和最近活跃用户列表的数组。
     */
    public function userCount()
    {
        // 查询数据库中的用户总数
        $count = $this->getModel()::getDB()->count("*");

        // 查询最近活跃的10位用户的信息，按创建时间降序排列，并包含用户ID和头像信息
        $res = $this->getModel()::getDB()
                    ->order('create_time DESC')
                    ->with(['user' => function($query){
                        // 仅获取用户ID和头像URL
                        $query->field('uid,avatar avatar_img');
                    }])
                    ->limit(10)
                    ->group('uid')
                    ->select()
                    ->toArray();

        // 筛选带有有效头像URL的用户信息
        $list = [];
        foreach ($res as $item){
            if(isset($item['user']['avatar_img']) && $item['user']['avatar_img']){
                $list[] = $item['user'];
            }
        }

        // 返回用户总数和活跃用户列表
        return compact('count','list');
    }


    /**
     *  更新状态
     * @param int $id
     * @author Qinii
     * @day 2020-11-25
     */
    public function changStatus(int $id)
    {
        $this->getModel()::getDB()->where($this->getPk(),$id)->update(['status' => 20]);
    }
}

