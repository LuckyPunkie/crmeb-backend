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
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 岗位应聘列表
     */
    public function getApplications(int $recruitId, int $merUid, $status, int $page, int $limit)
    {
        $recruit = $this->dao->get($recruitId);
        if (!$recruit) throw new ValidateException('岗位不存在');
        if ($recruit['mer_uid'] != $merUid) throw new ValidateException('无权查看');

        $applyDao = app()->make(CommunityRecruitApplyDao::class);
        $where = ['recruit_id' => $recruitId];
        if ($status !== null && $status !== '') {
            $where['status'] = (int)$status;
        }
        $query = $applyDao->search($where)
            ->with(['user' => function ($query) {
                $query->field('uid,avatar,nickname');
            }, 'resume'])
            ->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
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
                    $q->field('uid,nickname');
                }]);
            }])
            ->order('create_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
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
}
