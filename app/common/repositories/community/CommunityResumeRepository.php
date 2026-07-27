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

use app\common\dao\community\CommunityResumeDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 社区简历
 */
class CommunityResumeRepository extends BaseRepository
{
    /**
     * @var CommunityResumeDao
     */
    protected $dao;

    public function __construct(CommunityResumeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建简历
     */
    public function create(array $data, int $uid)
    {
        $data['uid'] = $uid;
        $data['completeness'] = $this->calculateCompleteness($data);
        return Db::transaction(function () use ($data) {
            // 如果设为默认，先取消其他默认
            if (isset($data['is_default']) && $data['is_default'] == 1) {
                $this->dao->getSearch(['uid' => $data['uid'], 'is_default' => 1])
                    ->update(['is_default' => 0]);
            }
            $resume = $this->dao->create($data);
            return $resume;
        });
    }

    /**
     * 更新简历
     */
    public function updateResume(int $id, int $uid, array $data)
    {
        $resume = $this->dao->search(['id' => $id, 'uid' => $uid])->find();
        if (!$resume) throw new ValidateException('简历不存在或不属于您');
        unset($data['uid']);
        $merged = array_merge($resume->toArray(), $data);
        $data['completeness'] = $this->calculateCompleteness($merged);

        return Db::transaction(function () use ($id, $data) {
            if (isset($data['is_default']) && $data['is_default'] == 1) {
                $this->dao->getSearch(['uid' => $this->dao->get($id)['uid'], 'is_default' => 1])
                    ->where('id', '<>', $id)
                    ->update(['is_default' => 0]);
            }
            $this->dao->update($id, $data);
        });
    }

    /**
     * 获取简历详情
     */
    public function getDetail(int $id, int $uid)
    {
        $resume = $this->dao->search(['id' => $id, 'uid' => $uid])->find();
        if (!$resume) throw new ValidateException('简历不存在或不属于您');
        return $this->formatResume($resume);
    }

    /**
     * 按 ID 获取简历详情（不校验归属，供商家/管理员预览）
     */
    public function getDetailById(int $id)
    {
        $resume = $this->dao->get($id);
        if (!$resume) throw new ValidateException('简历不存在');
        return $this->formatResume($resume);
    }

    /**
     * 我的简历列表
     */
    public function myList(int $uid)
    {
        $list = $this->dao->search(['uid' => $uid])->order('is_default DESC, id DESC')->select();
        foreach ($list as $item) {
            $item->completeness = $this->calculateCompleteness($item->toArray());
        }
        return $list;
    }

    /**
     * 删除简历
     */
    public function deleteResume(int $id, int $uid)
    {
        $resume = $this->dao->search(['id' => $id, 'uid' => $uid])->find();
        if (!$resume) throw new ValidateException('简历不存在或不属于您');
        $this->dao->delete($id);
    }

    /**
     * 设为默认简历
     */
    public function setDefault(int $id, int $uid)
    {
        $resume = $this->dao->search(['id' => $id, 'uid' => $uid])->find();
        if (!$resume) throw new ValidateException('简历不存在或不属于您');

        return Db::transaction(function () use ($uid, $id) {
            $this->dao->getSearch(['uid' => $uid])->update(['is_default' => 0]);
            $this->dao->update($id, ['is_default' => 1]);
        });
    }

    /**
     * 保存上传的简历文件
     */
    public function saveFile(int $id, int $uid, string $filePath)
    {
        $resume = $this->dao->search(['id' => $id, 'uid' => $uid])->find();
        if (!$resume) throw new ValidateException('简历不存在或不属于您');
        $data = $resume->toArray();
        $data['resume_file'] = $filePath;
        $this->dao->update($id, [
            'resume_file' => $filePath,
            'completeness' => $this->calculateCompleteness($data)
        ]);
    }

    public function normalizeResumePayload(array $data): array
    {
        if (isset($data['education_list']) && !isset($data['education_history'])) {
            $data['education_history'] = $data['education_list'];
        }
        if (isset($data['work_list']) && !isset($data['work_history'])) {
            $data['work_history'] = $data['work_list'];
        }
        if (isset($data['skills']) && is_array($data['skills'])) {
            $data['skills'] = json_encode(array_values(array_filter($data['skills'], function ($skill) {
                return $skill !== null && $skill !== '';
            })), JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['education_history']) && is_array($data['education_history'])) {
            $data['education_history'] = json_encode(array_values($data['education_history']), JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['work_history']) && is_array($data['work_history'])) {
            $data['work_history'] = json_encode(array_values($data['work_history']), JSON_UNESCAPED_UNICODE);
        }
        unset($data['education_list'], $data['work_list']);
        return $data;
    }

    protected function formatResume($resume)
    {
        $data = $resume->toArray();
        $data['education_list'] = $this->decodeJsonList($data['education_history'] ?? []);
        $data['work_list'] = $this->decodeJsonList($data['work_history'] ?? []);
        $data['skills'] = $this->decodeJsonList($data['skills'] ?? []);
        $data['completeness'] = $this->calculateCompleteness($data);
        return $data;
    }

    protected function decodeJsonList($value): array
    {
        if (!$value) return [];
        if (is_array($value)) return $value;
        if (!is_string($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function hasFilledList(array $list, array $keys = []): bool
    {
        foreach ($list as $row) {
            if (!is_array($row)) continue;
            if (!$keys) {
                foreach ($row as $value) {
                    if ($value !== null && $value !== '') return true;
                }
                continue;
            }
            foreach ($keys as $key) {
                if (!empty($row[$key])) return true;
            }
        }
        return false;
    }

    protected function calculateCompleteness(array $data): int
    {
        $score = 0;

        $score += !empty($data['real_name']) ? 10 : 0;
        $score += !empty($data['phone']) ? 10 : 0;
        $score += !empty($data['email']) ? 10 : 0;
        $score += !empty($data['birthday']) ? 5 : 0;
        $score += !empty($data['gender']) ? 5 : 0;
        $score += !empty($data['education']) ? 10 : 0;
        $score += !empty($data['work_years']) ? 10 : 0;
        $score += !empty($data['city']) ? 5 : 0;
        $score += !empty($data['expect_job']) ? 10 : 0;
        $score += !empty($data['expect_salary']) ? 5 : 0;

        $educationList = $this->decodeJsonList($data['education_history'] ?? []);
        $workList = $this->decodeJsonList($data['work_history'] ?? []);
        $skills = $this->decodeJsonList($data['skills'] ?? []);

        $score += $this->hasFilledList($educationList, ['school', 'degree', 'major', 'graduation_year']) ? 10 : 0;
        $score += $this->hasFilledList($workList, ['company', 'position', 'period', 'description']) ? 10 : 0;
        $score += count(array_filter($skills, function ($skill) {
            return $skill !== null && $skill !== '';
        })) > 0 ? 5 : 0;
        $score += (!empty($data['self_evaluation']) || !empty($data['introduction']) || !empty($data['work_summary'])) ? 5 : 0;

        return min(100, $score);
    }

    /**
     * 解析简历文件（异步任务提交）
     */
    public function parseResume(int $id, int $uid)
    {
        $resume = $this->dao->search(['id' => $id, 'uid' => $uid])->find();
        if (!$resume) throw new ValidateException('简历不存在或不属于您');
        if (empty($resume['resume_file'])) throw new ValidateException('没有可解析的简历文件');

        // 提交异步解析任务
        $taskId = uniqid('resume_parse_', true);
        // TODO: 接入实际的Job Queue系统
        return $taskId;
    }

    // ============================
    //  管理端方法
    // ============================

    /**
     * 管理员：简历列表（按投递岗位分组）
     */
    public function adminList($positionId, int $page, int $limit)
    {
        $query = \app\common\model\community\CommunityRecruitApply::getDB()
            ->alias('a')
            ->join('community_resume r', 'a.resume_id = r.id')
            ->join('community_recruit t', 'a.recruit_id = t.id')
            ->field([
                'r.id', 'r.real_name', 'r.gender', 'r.birthday', 'r.phone', 'r.email',
                'r.education', 'r.work_years', 'r.city', 'r.expect_job',
                'a.id as apply_id', 'a.recruit_id', 'a.create_time as apply_time', 'a.status as apply_status',
                't.job_title as position_name'
            ])
            ->when($positionId && $positionId !== '', function ($query) use ($positionId) {
                $query->where('a.recruit_id', $positionId);
            })
            ->order('a.create_time DESC');

        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('count', 'list');
    }

    /**
     * 管理员：简历详情
     */
    public function adminDetail(int $resumeId)
    {
        $resume = $this->dao->get($resumeId);
        if (!$resume) throw new ValidateException('简历不存在');

        // 关联查询投递记录
        $applyInfo = \app\common\model\community\CommunityRecruitApply::getDB()
            ->alias('a')
            ->join('community_recruit t', 'a.recruit_id = t.id')
            ->where('a.resume_id', $resumeId)
            ->field('t.job_title as position_name, a.create_time as apply_time, a.status as apply_status')
            ->order('a.create_time DESC')
            ->select();

        $resume['apply_records'] = $applyInfo;
        return $resume;
    }

    /**
     * 管理员：按岗位批量导出简历数据
     */
    public function adminExport($positionId)
    {
        $list = \app\common\model\community\CommunityRecruitApply::getDB()
            ->alias('a')
            ->join('community_resume r', 'a.resume_id = r.id')
            ->join('community_recruit t', 'a.recruit_id = t.id')
            ->field([
                'r.real_name', 'r.gender', 'r.birthday', 'r.education', 'r.work_years',
                'r.phone', 'r.email', 't.job_title as position_name', 'a.create_time as apply_time'
            ])
            ->where('a.recruit_id', $positionId)
            ->order('a.create_time DESC')
            ->select()
            ->toArray();

        return $list;
    }
}
