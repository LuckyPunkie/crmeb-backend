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

namespace app\controller\api\community;

use app\common\repositories\community\CommunityReportRepository;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 社区举报
 */
class CommunityReport extends BaseController
{
    /**
     * @var CommunityReportRepository
     */
    protected $repository;

    public function __construct(App $app, CommunityReportRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        if (!systemConfig('community_status')) throw new ValidateException('未开启社区功能');
    }

    /**
     * 提交举报
     */
    public function create()
    {
        $uid = $this->request->uid();
        $data = $this->request->params(['community_id', 'report_type', 'reason', 'images']);
        if (empty($data['community_id'])) throw new ValidateException('请选择举报内容');
        if (empty($data['reason'])) throw new ValidateException('请填写举报原因');
        $report = $this->repository->create($data, $uid);
        return app('json')->success(['report_id' => $report['id']]);
    }
}
