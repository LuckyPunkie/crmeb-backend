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

use app\common\dao\community\CommunityRecruitDao;
use app\common\dao\community\CommunityRecruitApplyDao;
use app\common\dao\community\CommunityResumeDao;
use app\common\model\community\CommunityRecruitApply;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 社区招聘
 */
class CommunityRecruitRepository extends BaseRepository
{
    /**
     * @var CommunityRecruitDao
     */
    protected $dao;

    public function __construct(CommunityRecruitDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取招聘详情（含当前用户投递状态）
     */
    public function getDetail(int $communityId, $uid = null)
    {
        $data = $this->dao->search(['community_id' => $communityId])
            ->with([
                'merchant' => function ($query) {
                    $query->field('uid,avatar,nickname');
                },
                'community' => function ($query) {
                    $query->field('community_id,title,content,image');
                }
            ])->find();
        if (!$data) throw new ValidateException('招聘信息不存在');

        if ($uid) {
            $applyDao = app()->make(CommunityRecruitApplyDao::class);
            $myApply = $applyDao->search(['recruit_id' => $data['id'], 'uid' => $uid])->find();
            $data['my_apply'] = $myApply;
        }
        return $data;
    }

    /**
     * 投递简历
     */
    public function apply(int $communityId, int $uid, int $resumeId)
    {
        $recruit = $this->dao->search(['community_id' => $communityId])->find();
        if (!$recruit) throw new ValidateException('招聘岗位不存在');
        if ($recruit['status'] != 1) throw new ValidateException('岗位已关闭');

        // 验证简历属于当前用户
        $resumeDao = app()->make(CommunityResumeDao::class);
        $resume = $resumeDao->search(['uid' => $uid, 'id' => $resumeId])->find();
        if (!$resume) throw new ValidateException('简历不存在', 10011);

        $applyDao = app()->make(CommunityRecruitApplyDao::class);
        if ($applyDao->search(['recruit_id' => $recruit['id'], 'uid' => $uid])->count() > 0) {
            throw new ValidateException('已投递过该岗位', 10010);
        }

        return Db::transaction(function () use ($applyDao, $recruit, $uid, $resumeId) {
            $apply = $applyDao->create([
                'recruit_id' => $recruit['id'],
                'community_id' => $recruit['community_id'],
                'uid' => $uid,
                'resume_id' => $resumeId,
                'status' => 0,
            ]);
            $this->dao->update($recruit['id'], ['apply_count' => Db::raw('apply_count + 1')]);
            return $apply;
        });
    }

    /**
     * 商家岗位列表
     */
    public function myList(int $merUid, $status, int $page, int $limit)
    {
        $where = ['mer_uid' => $merUid];
        if ($status !== null && $status !== '') {
            $where['status'] = (int)$status;
        }
        $query = $this->dao->search($where)->with(['community'])->order('id DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select()->each(function ($item) {
            $applyDao = app()->make(CommunityRecruitApplyDao::class);
            $item['pending_count'] = $applyDao->search(['recruit_id' => $item['id'], 'status' => 0])->count();
            return $item;
        });
        return compact('count', 'list');
    }

    /**
     * 商家招聘统计
     */
    public function merchantStats(int $merUid)
    {
        $recruitIds = $this->dao->search(['mer_uid' => $merUid])->column('id');
        $openCount = $this->dao->search(['mer_uid' => $merUid, 'status' => 1])->count();
        $totalApply = 0;
        $pendingCount = 0;
        $suitableCount = 0;
        $talkCount = 0;
        if ($recruitIds) {
            $totalApply = CommunityRecruitApply::getDB()->whereIn('recruit_id', $recruitIds)->count();
            $pendingCount = CommunityRecruitApply::getDB()->whereIn('recruit_id', $recruitIds)->where('status', 0)->count();
            $suitableCount = CommunityRecruitApply::getDB()->whereIn('recruit_id', $recruitIds)->where('status', 2)->count();
            $talkCount = CommunityRecruitApply::getDB()->whereIn('recruit_id', $recruitIds)->where('status', 4)->count();
        }
        return [
            'open_count' => $openCount,
            'position_count' => count($recruitIds),
            'total_apply' => $totalApply,
            'pending_count' => $pendingCount,
            'suitable_count' => $suitableCount,
            'talk_count' => $talkCount,
            'processed_count' => max(0, $totalApply - $pendingCount),
        ];
    }

    /**
     * 岗位应聘列表
     */
    public function getApplications(int $recruitId, int $merUid, $status, int $page, int $limit)
    {
        $recruit = $this->dao->get($recruitId);
        if (!$recruit) throw new ValidateException('岗位不存在');
        if ($recruit['mer_uid'] != $merUid) throw new ValidateException('无权查看');

        return $this->queryApplications($merUid, $recruitId, $status, $page, $limit);
    }

    /**
     * 商家全部应聘列表（可按岗位筛选）
     */
    public function getMerchantApplications(int $merUid, $recruitId, $status, int $page, int $limit)
    {
        return $this->queryApplications($merUid, $recruitId, $status, $page, $limit);
    }

    /**
     * 应聘列表查询
     */
    protected function queryApplications(int $merUid, $recruitId, $status, int $page, int $limit)
    {
        $recruitIds = $this->dao->search(['mer_uid' => $merUid])->column('id');
        if (!$recruitIds) {
            return ['count' => 0, 'list' => []];
        }

        $query = CommunityRecruitApply::getDB()->whereIn('recruit_id', $recruitIds);
        if ($recruitId !== null && $recruitId !== '') {
            $rid = (int)$recruitId;
            if (!in_array($rid, $recruitIds)) throw new ValidateException('无权查看');
            $query->where('recruit_id', $rid);
        }

        if ($status !== null && $status !== '') {
            if ($status === 'pending') {
                $query->where('status', 0);
            } elseif ($status === 'processed') {
                $query->where('status', '>', 0);
            } else {
                $query->where('status', (int)$status);
            }
        }

        $count = (clone $query)->count();
        $list = $query->with(['user' => function ($q) {
            $q->field('uid,avatar,nickname');
        }, 'resume', 'recruit'])
            ->order('create_time DESC')
            ->page($page, $limit)
            ->select();
        return compact('count', 'list');
    }

    /**
     * 标记应聘状态
     */
    public function markApply(int $applyId, int $merUid, int $status, string $remark = '')
    {
        $applyDao = app()->make(CommunityRecruitApplyDao::class);
        $apply = $applyDao->get($applyId);
        if (!$apply) throw new ValidateException('应聘记录不存在');

        $recruit = $this->dao->get($apply['recruit_id']);
        if ($recruit['mer_uid'] != $merUid) throw new ValidateException('无权操作');

        $applyDao->update($applyId, ['status' => $status, 'remark' => $remark]);

        // 商家标记后推送 C 端系统通知（面试邀请 / 不合适等）
        $this->notifyApplicantStatusChange($apply, $recruit, $status, $remark);
    }

    /**
     * 应聘状态变更 → 用户系统通知
     */
    protected function notifyApplicantStatusChange($apply, $recruit, int $status, string $remark = ''): void
    {
        $uid = (int)($apply['uid'] ?? 0);
        if ($uid <= 0) {
            return;
        }

        $statusMap = [
            1 => ['type' => 'recruit', 'title' => '简历已被查看', 'text' => '商家已查看你的简历'],
            2 => ['type' => 'interview', 'title' => '面试通知', 'text' => '商家邀请你参加面试'],
            3 => ['type' => 'recruit', 'title' => '应聘结果通知', 'text' => '很遗憾，商家认为暂不合适'],
            4 => ['type' => 'recruit', 'title' => '商家沟通通知', 'text' => '商家希望与你进一步沟通'],
        ];
        if (!isset($statusMap[$status])) {
            return;
        }

        $jobTitle = (string)($recruit['job_title'] ?? '岗位');
        $company = '';
        try {
            $merUid = (int)($recruit['mer_uid'] ?? 0);
            if ($merUid) {
                $merUser = Db::name('user')->where('uid', $merUid)->field('uid,nickname,mer_id')->find();
                $merId = (int)($merUser['mer_id'] ?? 0);
                if ($merId) {
                    $company = (string)(Db::name('merchant')->where('mer_id', $merId)->value('mer_name') ?: '');
                }
                if ($company === '') {
                    $company = (string)($merUser['nickname'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            $company = '';
        }

        $cfg = $statusMap[$status];
        $contentText = $cfg['text'];
        if ($company !== '') {
            $contentText = "【{$company}】" . $contentText;
        }
        if ($jobTitle !== '') {
            $contentText .= "（{$jobTitle}）";
        }
        if ($remark !== '') {
            $contentText .= "。备注：{$remark}";
        }

        $payload = \app\common\repositories\user\UserNotificationRepository::buildNoteContent([
            'text' => $contentText,
            'company' => $company,
            'position' => $jobTitle,
            'interview_time' => '',
            'apply_id' => (int)($apply['id'] ?? $apply['apply_id'] ?? 0),
            'recruit_id' => (int)($recruit['id'] ?? 0),
            'community_id' => (int)($recruit['community_id'] ?? 0),
            'remark' => $remark,
            'status' => $status,
        ]);

        try {
            app()->make(\app\common\repositories\user\UserNotificationRepository::class)
                ->createAndPush(
                    $uid,
                    (int)($recruit['mer_uid'] ?? 0),
                    $cfg['type'],
                    $cfg['title'],
                    $payload,
                    'recruit_apply',
                    (int)($apply['id'] ?? 0)
                );
        } catch (\Throwable $e) {
        }
    }

    /**
     * 关闭/开启招聘
     */
    public function toggleStatus(int $communityId, int $merUid, int $status)
    {
        $recruit = $this->dao->search(['community_id' => $communityId])->find();
        if (!$recruit) throw new ValidateException('招聘岗位不存在');
        if ($recruit['mer_uid'] != $merUid) throw new ValidateException('无权操作');

        $this->dao->update($recruit['id'], ['status' => $status]);
    }

    /**
     * 用户投递记录
     */
    public function myApplications(int $uid, $status, int $page, int $limit)
    {
        $applyDao = app()->make(CommunityRecruitApplyDao::class);
        $where = ['uid' => $uid];
        if ($status !== null && $status !== '') {
            $where['status'] = (int)$status;
        }
        $query = $applyDao->search($where)
            ->with(['recruit' => function ($query) {
                $query->with(['merchant' => function ($q) {
                    $q->field('uid,nickname,mer_id');
                }]);
            }])
            ->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        // 批量查 Merchant 表获取企业名称，挂到 recruit.mer_name 上
        $merIds = [];
        foreach ($list as $apply) {
            $merId = $apply->recruit->merchant->mer_id ?? null;
            if ($merId) $merIds[] = $merId;
        }
        if ($merIds) {
            $merchantNames = \app\common\model\system\merchant\Merchant::whereIn('mer_id', array_unique($merIds))
                ->field('mer_id,mer_name')
                ->select()
                ->column('mer_name', 'mer_id');
            foreach ($list as $apply) {
                $merId = $apply->recruit->merchant->mer_id ?? null;
                if ($merId && isset($merchantNames[$merId])) {
                    $apply->recruit->mer_name = $merchantNames[$merId];
                }
            }
        }

        return compact('count', 'list');
    }

    /**
     * 投递详情
     */
    public function applicationDetail(int $applyId, int $uid)
    {
        $applyDao = app()->make(CommunityRecruitApplyDao::class);
        $apply = $applyDao->search(['id' => $applyId, 'uid' => $uid])
            ->with(['recruit' => function ($query) {
                $query->with(['community']);
            }])
            ->find();
        if (!$apply) throw new ValidateException('投递记录不存在');
        return $apply;
    }

    /**
     * 商家查看应聘者简历（校验岗位归属）
     */
    public function getApplyResume(int $applyId, int $merUid)
    {
        $applyDao = app()->make(CommunityRecruitApplyDao::class);
        $apply = $applyDao->get($applyId);
        if (!$apply) throw new ValidateException('应聘记录不存在');

        $recruit = $this->dao->get($apply['recruit_id']);
        if (!$recruit || (int)$recruit['mer_uid'] !== $merUid) {
            throw new ValidateException('无权查看');
        }

        $resumeId = (int)($apply['resume_id'] ?? 0);
        if ($resumeId <= 0) throw new ValidateException('暂无简历信息');

        $resumeRepo = app()->make(CommunityResumeRepository::class);
        $resume = $resumeRepo->getDetailById($resumeId);

        $user = \app\common\model\user\User::getDB()
            ->where('uid', (int)$apply['uid'])
            ->field('uid,avatar,nickname')
            ->find();

        return [
            'apply_id' => (int)$apply['id'],
            'status' => (int)$apply['status'],
            'create_time' => $apply['create_time'] ?? '',
            'remark' => $apply['remark'] ?? '',
            'user' => $user ? $user->toArray() : null,
            'recruit' => [
                'id' => (int)$recruit['id'],
                'job_title' => $recruit['job_title'] ?? '',
                'salary_range' => $recruit['salary_range'] ?? '',
                'work_city' => $recruit['work_city'] ?? '',
            ],
            'resume' => $resume,
        ];
    }
}
