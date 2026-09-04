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
     * 提交用户举报（私信场景）
     */
    public function createUserReport(array $data, int $reporterUid, int $targetUid)
    {
        if ($reporterUid === $targetUid) {
            throw new ValidateException('不能举报自己');
        }
        if (empty($data['reason'])) {
            throw new ValidateException('请填写举报描述');
        }

        $user = app()->make(\app\common\repositories\user\UserRepository::class)->get($targetUid);
        if (!$user) {
            throw new ValidateException('用户不存在');
        }

        $images = $data['images'] ?? [];
        if (is_string($images)) {
            $images = json_decode($images, true) ?: [];
        }
        if (!is_array($images)) {
            $images = [];
        }
        if (count($images) > 9) {
            throw new ValidateException('最多上传9张图片');
        }

        return $this->dao->create([
            'community_id' => 0,
            'reporter_uid' => $reporterUid,
            'target_uid' => $targetUid,
            'report_type' => 'user',
            'reason' => trim((string)$data['reason']),
            'images' => json_encode(array_values($images)),
            'status' => 0,
        ]);
    }

    /**
     * 格式化后台展示字段
     */
    protected function formatAdminRow(array $row): array
    {
        $row['reported_user'] = $row['target'] ?? null;
        $row['images'] = $this->parseImages($row['images'] ?? null);

        if (!empty($row['community'])) {
            $row['reported_content'] = $row['community']['title'] ?? ($row['community']['content'] ?? '');
        } elseif ((int)($row['community_id'] ?? 0) === 0 && ($row['report_type'] ?? '') === 'user') {
            $row['reported_content'] = $row['reason'] ?? '';
        } else {
            $row['reported_content'] = $row['reason'] ?? '';
        }

        return $row;
    }

    protected function parseImages($images): array
    {
        if (is_array($images)) {
            return array_values(array_filter($images));
        }
        if (is_string($images) && $images !== '') {
            $decoded = json_decode($images, true);
            return is_array($decoded) ? array_values(array_filter($decoded)) : [];
        }
        return [];
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
        $formatted = [];
        foreach ($list as $item) {
            $formatted[] = $this->formatAdminRow($item->toArray());
        }
        return ['count' => $count, 'list' => $formatted];
    }

    /**
     * 管理员：举报详情
     */
    public function adminDetail(int $id)
    {
        $report = $this->dao->get($id);
        if (!$report) throw new ValidateException('举报记录不存在');
        $report->load([
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
        return $this->formatAdminRow($report->toArray());
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
