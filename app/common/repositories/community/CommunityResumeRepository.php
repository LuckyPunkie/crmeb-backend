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
    public function saveFile(int $id, int $uid, string $filePath, string $fileName = '')
    {
        $resume = $this->dao->search(['id' => $id, 'uid' => $uid])->find();
        if (!$resume) throw new ValidateException('简历不存在或不属于您');
        $data = $resume->toArray();
        $data['resume_file'] = $filePath;
        $this->dao->update($id, [
            'resume_file'      => $filePath,
            'resume_file_name' => $fileName,
            'parse_status'     => 0,
            'completeness'     => $this->calculateCompleteness($data)
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
        unset($data['education_list'], $data['work_list'], $data['introduction'], $data['work_summary']);
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
        // 只按编辑表单中实际存在的字段计算
        $score = 0;

        $score += !empty($data['real_name']) ? 15 : 0;
        $score += !empty($data['phone'])     ? 15 : 0;
        $score += !empty($data['email'])     ? 10 : 0;
        $score += !empty($data['gender'])    ? 5  : 0;
        $score += !empty($data['birthday'])  ? 5  : 0;

        $educationList = $this->decodeJsonList($data['education_history'] ?? []);
        $workList      = $this->decodeJsonList($data['work_history']      ?? []);
        $skills        = $this->decodeJsonList($data['skills']            ?? []);

        $score += $this->hasFilledList($educationList) ? 20 : 0;
        $score += $this->hasFilledList($workList)      ? 20 : 0;
        $score += count(array_filter($skills, function ($s) { return $s !== null && $s !== ''; })) > 0 ? 5 : 0;
        $score += !empty($data['self_evaluation']) ? 5 : 0;

        return min(100, $score);
    }

    /**
     * 解析简历文件，同步返回解析后的简历数据
     */
    public function parseResume(int $id, int $uid): array
    {
        $resume = $this->dao->search(['id' => $id, 'uid' => $uid])->find();
        if (!$resume) throw new ValidateException('简历不存在或不属于您');
        if (empty($resume['resume_file'])) throw new ValidateException('没有可解析的简历文件');

        // 解析物理文件路径：resume_file 存的是 /storage/xxx，通过 Filesystem 反推磁盘绝对路径
        $fileUrl      = $resume['resume_file'];
        $relativePath = ltrim(str_replace('/storage/', '', $fileUrl), '/');
        $localPath    = \think\facade\Filesystem::disk('public')->path($relativePath);

        if (!file_exists($localPath)) {
            throw new ValidateException('简历文件不存在，请重新上传');
        }

        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $text = $this->extractPdfText($localPath);
        } elseif (in_array($ext, ['doc', 'docx'])) {
            $text = $this->extractWordText($localPath);
        } else {
            throw new ValidateException('不支持的文件格式');
        }

        if (empty(trim($text))) {
            throw new ValidateException('无法从文件中提取文字，请确认文件内容不为空');
        }

        $parsed = $this->parseResumeText($text);

        // 只用解析到的非空字段覆盖（保留用户已填写的）
        $updateData = ['parse_status' => 1];
        foreach ($parsed as $field => $value) {
            if ($value !== null && $value !== '' && $value !== '[]') {
                $updateData[$field] = $value;
            }
        }

        $merged = array_merge($resume->toArray(), $updateData);
        $updateData['completeness'] = $this->calculateCompleteness($merged);

        $this->dao->update($id, $updateData);

        return $this->formatResume($this->dao->get($id));
    }

    protected function extractPdfText(string $path): string
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        return $pdf->getText();
    }

    protected function extractWordText(string $path): string
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($path);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            $text .= $this->collectWordElementText($section->getElements());
        }
        return $text;
    }

    protected function collectWordElementText(array $elements): string
    {
        $text = '';
        foreach ($elements as $element) {
            if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $text .= $this->collectWordElementText($cell->getElements()) . ' ';
                    }
                    $text .= "\n";
                }
            } elseif (method_exists($element, 'getElements')) {
                $text .= $this->collectWordElementText($element->getElements());
                $text .= "\n";
            } elseif (method_exists($element, 'getText')) {
                $t = $element->getText();
                if ($t) $text .= $t;
            }
        }
        return $text;
    }

    protected function parseResumeText(string $text): array
    {
        // 统一空白与破折号，简化后续正则
        $text = preg_replace('/\x{00a0}/u', ' ', $text);                   // 不间断空格→普通空格
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // &amp; 等实体
        $text = preg_replace('/\x{2013}|\x{2014}/u', '-', $text);          // en/em dash→普通连字符

        $result = [
            'real_name'        => null,
            'gender'           => null,
            'birthday'         => null,
            'phone'            => null,
            'email'            => null,
            'education'        => null,
            'work_years'       => null,
            'city'             => null,
            'expect_job'       => null,
            'expect_salary'    => null,
            'self_evaluation'  => null,
            'skills'           => '[]',
            'education_history'=> '[]',
            'work_history'     => '[]',
        ];

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $text))));

        // 手机号
        if (preg_match('/1[3-9]\d{9}/', $text, $m)) {
            $result['phone'] = $m[0];
        }

        // 邮箱
        if (preg_match('/[\w.+\-]+@[\w\-]+\.[a-zA-Z]{2,}/i', $text, $m)) {
            $result['email'] = $m[0];
        }

        // 性别：只接受 "性别" 后面跟冒号再跟 男/女（允许冒号前后有任意空白）
        if (preg_match('/性\s*别\s*[：:]\s*([男女])/u', $text, $m)) {
            $result['gender'] = $m[1];
        }

        // 出生日期（支持多种格式：YYYY-MM-DD / YYYY年MM月DD日 / YYYY.MM）
        if (preg_match('/出生[日期年月\s]*[：:]?\s*(\d{4})\s*[年.\-]\s*(\d{1,2})\s*[月.\-]\s*(\d{1,2})/u', $text, $m)) {
            $result['birthday'] = $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[3], 2, '0', STR_PAD_LEFT);
        } elseif (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日/', $text, $m)) {
            $result['birthday'] = $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[3], 2, '0', STR_PAD_LEFT);
        } elseif (preg_match('/出生[年月\s]*[：:]?\s*(\d{4})[年.]\s*(\d{1,2})/u', $text, $m)) {
            // 只有年月（Word 常见格式 1997.11）
            $result['birthday'] = $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-01';
        }

        // 最高学历
        $eduLevels = ['博士研究生', '硕士研究生', '博士', '硕士', '本科', '大专', '专科', '高中', '中专'];
        foreach ($eduLevels as $level) {
            if (strpos($text, $level) !== false) {
                $result['education'] = $level;
                break;
            }
        }

        // 工作年限
        if (preg_match('/工作年限\s*[：:]\s*(\d+)\s*年?/u', $text, $m) ||
            preg_match('/(\d+)\s*年\s*(?:以上)?工作经验/u', $text, $m)) {
            $result['work_years'] = $m[1] . '年';
        }

        // 城市
        $cities = ['北京', '上海', '广州', '深圳', '杭州', '成都', '武汉', '南京', '西安', '苏州',
                   '天津', '重庆', '长沙', '郑州', '东莞', '佛山', '青岛', '宁波', '福州', '厦门',
                   '合肥', '昆明', '哈尔滨', '济南', '沈阳', '大连', '温州', '无锡', '南昌', '石家庄'];
        if (preg_match('/(?:工作地[点城]|期望城市|所在城市|居住城市)\s*[：:]\s*([\x{4e00}-\x{9fa5}]{2,6})/u', $text, $m)) {
            $result['city'] = $m[1];
        } else {
            foreach ($cities as $city) {
                if (strpos($text, $city) !== false) { $result['city'] = $city; break; }
            }
        }

        // 求职意向
        if (preg_match('/(?:求职意向|期望职位|应聘职位|目标职位)\s*[：:]\s*([^\n,，。]{2,30})/u', $text, $m)) {
            $result['expect_job'] = trim($m[1]);
        }

        // 期望薪资
        if (preg_match('/(?:期望薪资|期望月薪|薪资期望)\s*[：:]\s*([^\n,，。]{2,20})/u', $text, $m)) {
            $result['expect_salary'] = trim($m[1]);
        } elseif (preg_match('/(\d+)[Kk千]\s*[-—~至]\s*(\d+)[Kk千]/u', $text, $m)) {
            $result['expect_salary'] = $m[1] . 'K-' . $m[2] . 'K';
        }

        // 姓名：优先 "姓名: XXX" 标签（遇到籍/贯/性/别/年/龄等停止，避免多捞）
        if (preg_match('/姓\s*名\s*[：:]\s*([\x{4e00}-\x{9fa5}]{2,4})(?=\s*(?:籍|贯|性|别|年|龄|政|民|联|手|邮)|[\s\t]|$)/u', $text, $m)) {
            $result['real_name'] = trim($m[1]);
        } elseif (preg_match('/姓\s*名\s*[：:]\s*([\x{4e00}-\x{9fa5}]{2,4})/u', $text, $m)) {
            $result['real_name'] = trim($m[1]);
        }
        if (!$result['real_name']) {
            $nameBlacklist = ['基本信息', '个人信息', '简历', '求职', '教育', '工作', '技能',
                              '经历', '联系方式', '在校经历', '个人特点', '教育背景', '工作经历'];
            foreach (array_slice($lines, 0, 15) as $line) {
                if (preg_match('/^[\x{4e00}-\x{9fa5}]{2,5}$/u', $line) && !in_array($line, $nameBlacklist)) {
                    $result['real_name'] = $line;
                    break;
                }
            }
        }

        // 自我评价
        if (preg_match('/(?:自我评价|个人简介|个人介绍|自我介绍)\s*[：:\n]\s*([\s\S]{10,600}?)(?=\n\n|$)/u', $text, $m)) {
            $result['self_evaluation'] = trim($m[1]);
        }

        // -------------------------------------------------------
        // 技能：PDF 文字可能乱序，分两步取
        // 步骤1：找 "技术技能/前端开发/后端开发/工具软件" 等分类行的逗号列表
        // 步骤2：如上面没找到，再找独立"技能"节标题后的内容
        // -------------------------------------------------------
        $skills = [];
        // 步骤1：找技能分类行（适合多列 PDF 乱序提取）
        $skillCategoryPat = '/(?:前端开发|后端开发|工具软件|技术技能|编程语言|框架|数据库|开发工具)\s*[：:]\s*([^\n]{5,150})/u';
        if (preg_match_all($skillCategoryPat, $text, $catMatches)) {
            foreach ($catMatches[1] as $skillLine) {
                foreach (preg_split('/[,，、&]+/u', $skillLine) as $item) {
                    $item = trim(preg_replace('/\s+/', ' ', $item));
                    if (mb_strlen($item) >= 2 && mb_strlen($item) <= 20 && !preg_match('/[。！？]/', $item)) {
                        $skills[] = $item;
                    }
                }
            }
        }
        // 步骤2：Word 常见内联格式 "技能：..."
        if (!$skills && preg_match('/技\s*能\s*[：:]\s*([^\n]{5,300})/u', $text, $m)) {
            $raw = $m[1];
            foreach (preg_split('/[,，、&]+/u', $raw) as $item) {
                $item = trim(preg_replace('/\s+/', ' ', $item));
                if (mb_strlen($item) >= 2 && mb_strlen($item) <= 20 && !preg_match('/[。！？]/', $item)) {
                    $skills[] = $item;
                }
            }
        }
        // 步骤3：如上面仍空，再找独立节标题（PDF 有序格式）
        if (!$skills) {
            foreach ($lines as $i => $line) {
                if (in_array($line, ['专业技能', '核心技能', '技能特长', '个人技能', '技能'])) {
                    $secContent = '';
                    for ($j = $i + 1; $j < count($lines) && $j < $i + 30; $j++) {
                        $nl = mb_strlen($lines[$j]);
                        if ($nl <= 8 && preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $lines[$j])) break;
                        $secContent .= $lines[$j] . ',';
                    }
                    foreach (preg_split('/[,，、]+/u', $secContent) as $item) {
                        $item = trim($item);
                        if (mb_strlen($item) >= 2 && mb_strlen($item) <= 15 && !preg_match('/[。！？负责掌握]/', $item)) {
                            $skills[] = $item;
                        }
                    }
                    break;
                }
            }
        }
        if ($skills) {
            $result['skills'] = json_encode(array_values(array_unique($skills)), JSON_UNESCAPED_UNICODE);
        }

        // -------------------------------------------------------
        // 教育经历：不依赖段落，直接从全文按行扫描
        // 找 "YYYY-YYYY" 或 "YYYY.M" 附近含"专业/学院/大学"的行组
        // -------------------------------------------------------
        $eduList  = [];
        $workList = [];

        // 全文按行扫描，对每个日期范围判断上下文（支持独立行和行内格式）
        $datePat = '/(\d{4}(?:[年.]\d{1,2}[月]?)?)\s*[-—~至]\s*(\d{4}(?:[年.]\d{1,2}[月]?)?|至今)/u';
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            // 找日期：独立整行 OR 行内包含日期范围
            $isStandaloneLine = preg_match('/^' . substr($datePat, 1, -2) . '$/', $line, $dm);
            $isInlineLine     = !$isStandaloneLine && preg_match($datePat, $line, $dm);
            if (!$isStandaloneLine && !$isInlineLine) continue;
            $period = $dm[1] . '-' . $dm[2];

            // 取前后各5行作为上下文
            $ctx = implode(' ', array_slice($lines, max(0, $i - 3), 9));

            $isEdu  = (bool)preg_match('/(?:大学|学院|学校|专业|学位|荣誉|在读|毕业)/u', $ctx);
            $isWork = (bool)preg_match('/(?:公司|集团|有限|股份|科技|网络|软件|机构|负责|开发|设计|管理)/u', $ctx);

            if ($isEdu && !$isWork) {
                $edu = ['period' => $period];
                if (preg_match('/([\x{4e00}-\x{9fa5}]{2,15}(?:大学|学院|学校))/u', $ctx, $sm)) {
                    $edu['school'] = $sm[1];
                }
                foreach ($eduLevels as $lv) {
                    if (strpos($ctx, $lv) !== false) { $edu['degree'] = $lv; break; }
                }
                if (preg_match('/专业\s*[：:]?\s*([\x{4e00}-\x{9fa5}a-zA-Z\d\s与]{2,25})/u', $ctx, $mm)) {
                    $edu['major'] = trim($mm[1]);
                }
                $eduList[] = $edu;
            } elseif ($isWork) {
                $work = ['period' => $period];
                if (preg_match('/([\x{4e00}-\x{9fa5}a-zA-Z]{2,20}(?:公司|集团|有限|股份|科技|网络|软件|机构))/u', $ctx, $cm)) {
                    $work['company'] = $cm[1];
                }
                if (preg_match('/(?:职位|岗位|担任|职务)\s*[：:]?\s*([\x{4e00}-\x{9fa5}a-zA-Z]{2,20})/u', $ctx, $pm)) {
                    $work['position'] = $pm[1];
                }
                // 取日期后的描述段
                $descLines = [];
                for ($j = $i + 1; $j < count($lines) && $j < $i + 6; $j++) {
                    if (preg_match('/^\d{4}/', $lines[$j])) break;
                    if (mb_strlen($lines[$j]) > 10) $descLines[] = $lines[$j];
                }
                if ($descLines) $work['description'] = mb_substr(implode(' ', $descLines), 0, 300);
                $workList[] = $work;
            }
        }

        if ($eduList)  $result['education_history'] = json_encode($eduList, JSON_UNESCAPED_UNICODE);
        if ($workList) $result['work_history']       = json_encode($workList, JSON_UNESCAPED_UNICODE);

        return $result;
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
