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

namespace app\common\repositories\community;

use app\common\dao\community\CommunityReportDao;
use app\common\dao\community\CommunityDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;

/**
 * 社区举报
 */
class CommunityReportRepository extends BaseRepository
{
    /**
     * @var CommunityReportDao
     */
    protected $dao;

    public function __construct(CommunityReportDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 提交举报
     */
    public function create(array $data, int $reporterUid)
    {
        $communityDao = app()->make(CommunityDao::class);
        $community = $communityDao->get($data['community_id']);
        if (!$community) throw new ValidateException('笔记不存在');

        $data['reporter_uid'] = $reporterUid;
        $data['target_uid'] = $community['uid'];
        $data['status'] = 0;
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = json_encode($data['images']);
        }
        return $this->dao->create($data);
    }

    /**
     * 管理员：举报列表
     */
    public function adminList($status, int $page, int $limit)
    {
        $where = [];
        if ($status !== null && $status !== '') {
            $where['status'] = (int)$status;
        }
        $query = $this->dao->search($where)
            ->with([
                'reporter' => function ($query) {
                    $query->field('uid,nickname,avatar');
                },
                'target' => function ($query) {
                    $query->field('uid,nickname,avatar');
                },
                'community' => function ($query) {
                    $query->field('community_id,title,community_type');
                }
            ])
            ->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 管理员：举报详情
     */
    public function adminDetail(int $id)
    {
        $report = $this->dao->get($id);
        if (!$report) throw new ValidateException('举报记录不存在');
        return $report->load([
            'reporter' => function ($query) {
                $query->field('uid,nickname,avatar');
            },
            'target' => function ($query) {
                $query->field('uid,nickname,avatar');
            },
            'community' => function ($query) {
                $query->field('community_id,title,content,community_type');
            }
        ]);
    }

    /**
     * 管理员：处理举报
     */
    public function adminHandle(int $id, int $result, string $adminRemark = '')
    {
        $report = $this->dao->get($id);
        if (!$report) throw new ValidateException('举报记录不存在');
        if ($report['status'] == 2) throw new ValidateException('举报已处理');

        $this->dao->update($id, [
            'status' => 2,
            'result' => $result,
            'admin_remark' => $adminRemark,
        ]);
    }
}
