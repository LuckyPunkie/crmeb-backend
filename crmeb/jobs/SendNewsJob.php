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


namespace crmeb\jobs;


use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\interfaces\JobInterface;
use crmeb\services\wechat\Messages;
use crmeb\services\wechat\OfficialAccount;
use think\queue\Job;

class SendNewsJob implements JobInterface
{

    public function fire($job, $data)
    {
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $userRepository =  app()->make(UserRepository::class);
        [$id, $news] = $data;
        if(!$id || !$userInfo = $userRepository->get($id)){
            return  $job->delete();
        }
        if (!($openId = $wechatUserRepository->idByOpenId((int)$userInfo['wechat_user_id']))) {
            return  $job->delete();
        }
        try {
            OfficialAccount::staffSend(Messages::newsMessage($news), $openId);
        } catch (\Exception $e) {
            $job->failed($e);
        }
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
