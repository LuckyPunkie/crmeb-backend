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


use think\facade\Log;
use app\common\repositories\user\UserHistoryRepository;
use app\common\repositories\user\UserVisitRepository;
use crmeb\interfaces\JobInterface;

class VisitProductJob implements JobInterface
{

    public function fire($job, $data)
    {
        try{
            $uid = $data['uid'];
            $id = $data['id'];
            $make = app()->make(UserVisitRepository::class);
            $count = $make->search(['uid' => $uid, 'type' => 'product'])
                ->where('type_id', $id)
                ->whereTime('UserVisit.create_time', '>', date('Y-m-d H:i:s', strtotime('- 300 seconds')))->count();
            if (!$count) $make->visitProduct($uid,$id);
            app()->make(UserHistoryRepository::class)->createOrUpdate($data);
        }catch (\Exception $e){
            Log::info('浏览记录同步失败：'.$e->getMessage(), $data);
        }
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
