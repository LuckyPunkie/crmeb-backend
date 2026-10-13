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

namespace app\controller\admin\user;

use app\common\repositories\store\ExcelRepository;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;
use app\validate\api\UserExtractValidate as validate;
use app\common\repositories\user\UserExtractRepository as repository;

/**
 * Class UserExtract
 * app\controller\admin\user
 *  用户申请提现
 */
class UserExtract extends BaseController
{
    /**
     * @var repository
     */
    public $repository;

    /**
     * UserExtract constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * 申请提现列表
     * @return mixed
     * @author Qinii
     * @day 2020-06-16
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status', 'keyword', 'date', 'extract_type','uid','phone','real_name','nickname']);
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 审核表单
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-16
     */
    public function switchStatusForm($id)
    {
        return app('json')->success(formToData($this->repository->switchStatusForm($id)));
    }

    /**
     * 审核
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-16
     */
    public function switchStatus($id)
    {
        $data = $this->request->params(['fail_msg', 'mark']);
        $data['status'] = $this->request->param('status/d') == 1 ? 1 : -1;
        if ($data['status'] == '-1' && empty($data['fail_msg']))
            return app('json')->fail('请填写拒绝原因');
        if (!$this->repository->getWhereCount($id))
            return app('json')->fail('数据不存在或状态错误');
        $data['admin_id'] = $this->request->adminId();
        $data['status_time'] = date('Y-m-d H:i:s', time());
        $this->repository->switchStatus($id, $data);
        return app('json')->success('审核成功');
    }

    /**
     * 导出
     * @return \think\response\Json
     * @author Qinii
     */
    public function export()
    {
        $where = $this->request->params(['status', 'keyword', 'date', 'extract_type']);
        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->extract($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 详情
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-16
     */
    public function detail($id)
    {
        return app('json')->success($this->repository->detail((int)$id));
    }
}
