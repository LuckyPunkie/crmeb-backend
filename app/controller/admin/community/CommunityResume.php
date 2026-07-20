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

use app\common\model\community\CommunityRecruit;
use app\common\repositories\community\CommunityResumeRepository;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 社区简历管理（管理端）
 */
class CommunityResume extends BaseController
{
    protected $repository;

    public function __construct(App $app, CommunityResumeRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 简历列表（按投递岗位分组）
     * GET/POST /api/admin/community/resume/list
     */
    public function list()
    {
        $positionId = $this->request->param('position_id', '');
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->adminList($positionId, $page, $limit));
    }

    /**
     * 简历详情
     * GET /api/admin/community/resume/detail/:id
     */
    public function detail($id)
    {
        if (!$this->repository->exists((int)$id))
            return app('json')->fail('简历不存在');
        return app('json')->success($this->repository->adminDetail((int)$id));
    }

    /**
     * 招聘岗位选项（简历筛选）
     */
    public function positionOption()
    {
        $list = CommunityRecruit::getDB()
            ->where('status', 1)
            ->field('id, job_title as title')
            ->order('id DESC')
            ->select();
        return app('json')->success($list);
    }

    /**
     * 批量导出
     * GET /api/admin/community/resume/export
     */
    public function export()
    {
        $positionId = $this->request->param('position_id', '');
        if (!$positionId) throw new ValidateException('请选择投递岗位');

        $list = $this->repository->adminExport($positionId);
        if (empty($list)) throw new ValidateException('该岗位暂无简历数据');

        // 生成 CSV
        $headers = ['姓名', '性别', '年龄', '学历', '工作经验年限', '手机号', '邮箱', '投递岗位', '投递时间'];

        // 设置响应头
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="resume_export_' . date('YmdHis') . '.csv"');
        header('Cache-Control: max-age=0');

        // 输出 BOM 以保证 Excel 正确识别 UTF-8 中文
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // 写入表头
        fputcsv($output, $headers);

        // 写入数据行
        foreach ($list as $row) {
            $genderMap = [0 => '未知', 1 => '男', 2 => '女'];
            $educationMap = [0 => '不限', 1 => '高中', 2 => '大专', 3 => '本科', 4 => '硕士', 5 => '博士'];

            // 计算年龄
            $age = '';
            if (!empty($row['birthday'])) {
                $age = date('Y') - date('Y', strtotime($row['birthday']));
            }

            fputcsv($output, [
                $row['real_name'] ?? '',
                $genderMap[$row['gender'] ?? 0] ?? '未知',
                $age,
                $educationMap[$row['education'] ?? 0] ?? '不限',
                $row['work_years'] ?? '',
                $row['phone'] ?? '',
                $row['email'] ?? '',
                $row['position_name'] ?? '',
                $row['apply_time'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }
}
