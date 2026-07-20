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

namespace app\controller\admin\community;

use app\common\repositories\community\CommunityReportRepository;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 社区举报（管理端）
 */
class CommunityReport extends BaseController
{
    protected $repository;

    public function __construct(App $app, CommunityReportRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 举报列表
     */
    public function list()
    {
        $status = $this->request->param('status', '');
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->adminList($status, $page, $limit));
    }

    /**
     * 举报详情
     */
    public function detail($id)
    {
        if (!$this->repository->exists((int)$id))
            return app('json')->fail('举报记录不存在');
        return app('json')->success($this->repository->adminDetail((int)$id));
    }

    /**
     * 处理举报
     */
    public function handle($id)
    {
        $result = $this->request->param('result', 0);
        $adminRemark = $this->request->param('admin_remark', '');

        if (!in_array($result, [0, 1])) throw new ValidateException('处理结果类型错误');

        $this->repository->adminHandle((int)$id, (int)$result, $adminRemark);
        return app('json')->success('处理完成');
    }
}
