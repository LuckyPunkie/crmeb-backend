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


namespace app\common\dao\system\notice;


use app\common\dao\BaseDao;
use app\common\model\system\notice\SystemNoticeConfig;

class SystemNoticeConfigDao extends BaseDao
{

    protected function getModel(): string
    {
        return SystemNoticeConfig::class;
    }


    /**
     * 根据通知键获取通知状态
     *
     * 此方法用于查询特定通知键的通知状态。它通过通知键和字段名从数据库中检索信息，
     * 并根据字段的值（预期为1表示启用，其他值表示禁用）返回相应的布尔状态。
     *
     * @param string $key 通知键，用于唯一标识通知。
     * @param string $field 要查询的状态字段名，通常应该是表示状态的字段。
     *
     * @return bool 返回通知的状态，true表示启用，false表示禁用。
     */
    public function getNoticeStatusByKey(string $key, string $field)
    {
        // 通过通知键查询数据库，获取指定字段的值
        $value = $this->getModel()::getDb()->where('notice_key',$key)->value($field);

        // 根据字段值判断通知状态，如果值为1，则返回true，否则返回false
        return $value == 1  ? true  : false;
    }


    /**
     * 根据常量键获取通知状态
     *
     * 本函数通过常量键查询数据库，获取对应的通知设置状态。这些状态包括系统通知、微信通知、小程序通知和短信通知。
     * 主要用于在系统中根据用户的设置，决定是否发送某种类型的通知。
     *
     * @param string $key 常量键，用于唯一标识通知设置项。
     * @return array 返回包含通知状态的数组，包括系统通知、微信通知、小程序通知和短信通知的状态。
     */
    public function getNoticeStatusByConstKey(string $key)
    {
        // 通过常量键查询数据库，获取指定通知设置项的通知状态
        $value = $this->getModel()::getDb()->where('const_key',$key)->field('notice_sys,notice_wechat,notice_routine,notice_sms')->find();
        return $value;
    }

    /**
     * 根据条件搜索通知记录
     *
     * 本函数用于构建查询通知记录的数据库查询条件。它支持通过不同渠道的通知状态进行筛选，
     * 包括短信、常规任务和微信通知。查询条件根据传入的$where参数动态构建，只包含有实际筛选需求的条件。
     * 这种设计提高了查询的灵活性和效率，避免了不必要的数据库查询操作。
     *
     * @param array $where 查询条件数组，包含可能的筛选条件：is_sms, is_routine, is_wechat
     * @return \yii\db\Query 查询对象，包含了根据条件构建的查询条件
     */
    public function search($where)
    {
        // 获取模型对应的数据库查询对象
        $query = $this->getModel()::getDb();

        // 当is_sms条件存在且不为空时，添加notice_sms字段的查询条件
        $query = $query->when(isset($where['is_sms']) && $where['is_sms'] != '', function($query){
            // 筛选notice_sms字段为0或1的记录，表示短信通知的状态为启用或禁用
            $query->whereIn('notice_sms',[0,1]);
        });

        // 当is_routine条件存在且不为空时，添加notice_routine字段的查询条件
        $query = $query->when(isset($where['is_routine']) && $where['is_routine'] != '', function($query){
            // 筛选notice_routine字段为0或1的记录，表示常规任务通知的状态为启用或禁用
            $query->whereIn('notice_routine',[0,1]);
        });

        // 当is_wechat条件存在且不为空时，添加notice_wechat字段的查询条件
        $query = $query->when(isset($where['is_wechat']) && $where['is_wechat'] != '', function($query){
            // 筛选notice_wechat字段为0或1的记录，表示微信通知的状态为启用或禁用
            $query->whereIn('notice_wechat',[0,1]);
        });

        // 返回构建完成的查询对象
        return $query;
    }


    /**
     * 获取订阅配置的模板ID
     *
     * 此方法用于查询所有启用了小程序订阅通知的配置项，并将其对应的模板ID整理为数组返回。
     * 主要用于在系统中快速查找和配置订阅消息模板，以便在发送订阅消息时使用。
     *
     * @return array 返回一个键值对数组，其中键是配置项的标识，值是对应的小程序模板ID。
     */
    public function getSubscribe()
    {
        // 初始化空数组，用于存储查询结果
        $arr = [];

        // 查询启用了小程序订阅通知的配置项，并包含对应的模板信息
        $res = $this->search([])->where(['notice_routine' => 1])->with(['routineTemplate'])->select()->toArray();

        // 遍历查询结果，将配置项标识和对应的模板ID（如果存在）整理到数组中
        foreach ($res as $re) {
            $arr[$re['const_key']] = $re['routine_tempid'] ?? '';
        }

        // 返回整理后的数组
        return $arr;
    }

}
