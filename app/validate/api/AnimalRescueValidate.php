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

namespace app\validate\api;

use think\Validate;

/**
 * 流浪动物救助 API 验证器
 * Class AnimalRescueValidate
 * @package app\validate\api
 */
class AnimalRescueValidate extends Validate
{
    /**
     * 定义验证规则
     */
    protected $rule = [
        'type' => 'require|in:1,2,3',
        'title' => 'require|max:120',
        'animal_name' => 'max:60',
        'animal_type' => 'in:dog,cat,rabbit,other',
        'city_id' => 'require|number',
        'phone' => 'require|max:20',
        'target_amount' => 'float|min:0.01|max:50000',
        'deposit_amount' => 'float|min:0.01|max:50000',
        'deposit_thaw_months' => 'in:1,3,6,12',
        'end_time' => 'date',
        'content' => 'require|max:5000',
        'images' => 'array|max:9',
        'amount' => 'require|float|min:0.01|max:50000',
        'pay_type' => 'in:weixin,alipay,bank,mock,wechat,routine',
        'is_anonymous' => 'in:0,1',
        'message' => 'max:200',
        'post_id' => 'require|number',
        'is_subscribe' => 'in:0,1',
        'real_name' => 'require|max:60',
        'id_card' => 'max:30',
        'address' => 'max:255',
        'income_info' => 'max:255',
        'housing_type' => 'in:owned,rented',
        'application_id' => 'require|number',
        // 帖子审核通过=1；领养申请审核通过=2；驳回统一=-1
        'status' => 'require|in:-1,1,2',
        'remark' => 'max:500',
        'agreed' => 'require|in:1',
    ];

    /**
     * 定义错误消息
     */
    protected $message = [
        'type.require' => '请选择发布类型',
        'type.in' => '发布类型无效',
        'title.require' => '请输入标题',
        'title.max' => '标题不能超过120字',
        'animal_type.in' => '动物类型无效',
        'city_id.require' => '请选择城市',
        'phone.require' => '请输入联系电话',
        'end_time.requireIf' => '请选择筹款截止时间',
        'end_time.date' => '筹款截止时间格式不正确',
        'content.require' => '请输入详情描述',
        'content.max' => '描述不能超过5000字',
        'images.max' => '最多上传9张图片',
        'amount.require' => '请输入金额',
        'amount.min' => '金额必须大于0',
        'amount.max' => '金额不能超过50000',
        'pay_type.in' => '支付方式无效',
        'message.max' => '留言不能超过200字',
        'post_id.require' => '帖子ID不能为空',
        'real_name.require' => '请填写真实姓名',
        'application_id.require' => '申请ID不能为空',
        'status.require' => '请选择审核状态',
        'status.in' => '审核状态无效',
        'agreed.require' => '请先同意领养协议',
        'agreed.in' => '请先同意领养协议',
    ];

    /**
     * 发帖验证场景
     */
    public function sceneCreate()
    {
        return $this->append('target_amount', 'requireIf:type,1|requireIf:type,3')
            ->append('end_time', 'requireIf:type,1|requireIf:type,3')
            ->append('deposit_amount', 'requireIf:type,2')
            ->append('deposit_thaw_months', 'requireIf:type,2');
    }

    /**
     * 捐款下单验证场景
     */
    public function sceneDonate()
    {
        return $this->only(['post_id', 'amount', 'pay_type', 'is_anonymous', 'message']);
    }

    /**
     * 领养申请验证场景
     */
    public function sceneApplyAdoption()
    {
        return $this->only(['post_id', 'real_name', 'phone', 'id_card', 'address', 'income_info', 'housing_type', 'agreed']);
    }

    /**
     * 缴纳保证金验证场景
     */
    public function scenePayDeposit()
    {
        return $this->only(['application_id', 'pay_type']);
    }

    /**
     * 编辑帖子验证场景
     */
    public function sceneUpdate()
    {
        return $this->remove('title', 'require')
            ->remove('content', 'require')
            ->remove('phone', 'require')
            ->remove('city_id', 'require')
            ->append('target_amount', 'requireIf:type,1|requireIf:type,3')
            ->append('deposit_amount', 'requireIf:type,2')
            ->append('deposit_thaw_months', 'requireIf:type,2');
    }

    /**
     * 审核验证场景
     */
    public function sceneAudit()
    {
        return $this->only(['status', 'remark']);
    }
}
