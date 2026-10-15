<?php

namespace crmeb\services\identity;

use crmeb\services\HttpService;

/**
 * 学信网在线验证报告（学籍 / 学历备案表 / 学位）解析。
 *
 * 学信网对机房 IP 有 WAF 拦截，正常由用户手机（App / 小程序 uni.request）取报告页 HTML 交给后端解析；
 * 服务器自取（fetch）只给 H5 兜底，随时可能 403。
 * 电脑版页面：#resultTable h4 + .report-info-item .label/.value；移动版：h3.res-h3 + .row .col-left/.col-right。
 */
class ChsiReportService
{
    public const REPORT_URL = 'https://www.chsi.com.cn/xlcx/bg.do';

    // 与学信网查询页前端校验一致（W 开头是微专业备案表，不算学历）
    private const VCODE_PATTERN = '/^(\d{12}|A[A-Z0-9]{15}|X[A-Z0-9]{15})$/';

    // 入库的报告字段（不存出生日期、民族、证件号、照片）
    private const KEEP_LABELS = [
        '学校名称', '学位授予单位', '层次', '获授学位', '所获学位', '学位', '学位名称', '学位类别',
        '专业', '学科/专业', '学科专业', '学制', '学历类别', '学习形式', '分院', '系所',
        '入学日期', '毕（结）业日期', '毕(结)业日期', '毕（结）业', '毕(结)业', '学籍状态', '离校日期',
        '预计毕业日期', '获学位日期', '授予学位日期',
    ];

    public function normalizeVcode(string $vcode): string
    {
        return strtoupper(preg_replace('/\s+/u', '', $vcode));
    }

    public function isValidVcode(string $vcode): bool
    {
        return (bool)preg_match(self::VCODE_PATTERN, $vcode);
    }

    public function reportUrl(string $vcode): string
    {
        return self::REPORT_URL . '?vcode=' . rawurlencode($vcode) . '&srcid=bgcx';
    }

    /**
     * 服务器直接取报告页（H5 兜底）
     * @return array{ok:bool, html?:string, message?:string}
     */
    public function fetch(string $vcode): array
    {
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: zh-CN,zh;q=0.9',
        ];
        $html = HttpService::getRequest($this->reportUrl($vcode), [], $headers, 15);
        if ($html === false || $html === '') {
            $status = HttpService::getStatus();
            $code = is_array($status) ? (int)($status['http_code'] ?? 0) : 0;
            if ($code === 403) {
                return ['ok' => false, 'message' => '学信网暂时拒绝了服务器查询，请在 App 或小程序内核验'];
            }
            return ['ok' => false, 'message' => '学信网查询失败，请稍后重试'];
        }
        return ['ok' => true, 'html' => (string)$html];
    }

    /**
     * @return array{ok:bool, message:string, captcha:bool, title:string, update_time:string, vcode:string, fields:array}
     */
    public function parse(string $html): array
    {
        $ret = ['ok' => false, 'message' => '', 'captcha' => false, 'title' => '', 'update_time' => '', 'vcode' => '', 'fields' => []];
        if (trim($html) === '') {
            $ret['message'] = '未获取到学信网报告';
            return $ret;
        }

        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xp = new \DOMXPath($dom);

        $error = $this->firstText($xp, "//*[contains(@class,'result-error')]//h2 | //*[contains(@class,'error-page')]//h2 | //*[@id='msgDiv']");
        if ($error !== '') {
            $ret['message'] = $error;
            return $ret;
        }
        if ($xp->query("//input[contains(@class,'captchTag')]")->length > 0) {
            $ret['captcha'] = true;
            $ret['message'] = '学信网要求图形验证，请稍后再试或改用上传资料认证';
            return $ret;
        }

        $fields = [];
        // 电脑版
        foreach ($xp->query("//div[contains(@class,'report-info-item')]") as $item) {
            $label = $this->firstText($xp, ".//div[contains(@class,'label')]", $item);
            if ($label !== '') {
                $fields[$label] = $this->firstText($xp, ".//div[contains(@class,'value')]", $item);
            }
        }
        // 移动版
        foreach ($xp->query("//div[contains(concat(' ',normalize-space(@class),' '),' row ')]") as $row) {
            $label = $this->firstText($xp, "./div[contains(@class,'col-left')]", $row);
            if ($label !== '' && !isset($fields[$label])) {
                $fields[$label] = $this->firstText($xp, "./div[contains(@class,'col-right')]", $row);
            }
        }

        $title = $this->firstText($xp, "//*[@id='resultTable']//h4 | //h3[contains(@class,'res-h3')]");
        $updateTime = $fields['更新日期'] ?? '';
        if ($updateTime === '') {
            $updateTime = preg_replace('/^.*?[：:]/u', '', $this->firstText($xp, "//*[contains(@class,'update-time')]"));
        }
        $vcode = $fields['在线验证码'] ?? $this->firstText($xp, "//span[contains(@class,'yzm')]");

        if (!$fields || ($title === '' && !isset($fields['姓名']))) {
            $ret['message'] = '未能识别学信网报告内容，请确认验证码后重试';
            return $ret;
        }

        $ret['ok'] = true;
        $ret['title'] = $title;
        $ret['update_time'] = (string)$updateTime;
        $ret['vcode'] = $this->normalizeVcode((string)$vcode);
        $ret['fields'] = $fields;
        return $ret;
    }

    /**
     * 报告 → 学历资料
     * education 与资料字段一致：2大专 3本科 4硕士 5博士及以上；education_type：1全日制 2非全日制 0未知
     */
    public function toEducation(array $report): array
    {
        $f = $report['fields'] ?? [];
        $title = (string)($report['title'] ?? '');
        if (mb_strpos($title, '学位') !== false) {
            $reportType = 'xuewei';
        } elseif (mb_strpos($title, '学籍') !== false) {
            $reportType = 'xueji';
        } else {
            $reportType = 'xueli';
        }

        $level = $this->pick($f, $reportType === 'xuewei'
            ? ['获授学位', '所获学位', '学位', '学位名称', '学位类别']
            : ['层次']);
        $studyForm = $this->pick($f, ['学习形式']);
        $status = $this->pick($f, ['学籍状态']);
        // 学籍报告：在籍 = 在读；退学/开除/取消学籍 不予认证
        $inSchool = $reportType === 'xueji' && $status !== ''
            && mb_strpos($status, '在籍') !== false && mb_strpos($status, '不在籍') === false;
        $invalidStatus = $status !== '' && (bool)preg_match('/退学|开除|取消/u', $status);

        $kept = [];
        foreach (self::KEEP_LABELS as $label) {
            if (isset($f[$label]) && $f[$label] !== '') {
                $kept[$label] = $f[$label];
            }
        }

        return [
            'report_type'    => $reportType,
            'title'          => $title,
            'update_time'    => (string)($report['update_time'] ?? ''),
            'name'           => $this->pick($f, ['姓名']),
            'school'         => $this->pick($f, ['学校名称', '学位授予单位', '院校名称']),
            'major'          => $this->pick($f, ['专业', '学科/专业', '学科专业']),
            'level'          => $level,
            'level_text'     => $this->levelText($level, $reportType) . ($inSchool ? '在读' : ''),
            'education'      => $this->levelToEducation($level),
            'study_form'     => $studyForm,
            'education_type' => $this->studyFormToType($studyForm),
            'status'         => $status,
            'in_school'      => $inSchool,
            'invalid_status' => $invalidStatus,
            'fields'         => $kept,
        ];
    }

    /** 报告姓名与实名姓名比对（忽略空格和少数民族姓名间隔点写法差异） */
    public function sameName(string $a, string $b): bool
    {
        $norm = static function (string $s): string {
            return preg_replace('/[\s·•．.‧・]+/u', '', $s);
        };
        $a = $norm($a);
        return $a !== '' && $a === $norm($b);
    }

    private function levelToEducation(string $level): int
    {
        if ($level === '') {
            return 0;
        }
        if (mb_strpos($level, '博士') !== false) {
            return 5;
        }
        if (mb_strpos($level, '硕士') !== false) {
            return 4;
        }
        if (preg_match('/本科|学士|专升本/u', $level)) {
            return 3;
        }
        if (preg_match('/专科|高职|大专/u', $level)) {
            return 2;
        }
        return 0;
    }

    private function levelText(string $level, string $reportType): string
    {
        $map = [5 => '博士', 4 => '硕士', 3 => '本科', 2 => '大专'];
        $edu = $this->levelToEducation($level);
        if ($reportType === 'xuewei' || !isset($map[$edu])) {
            return $level;
        }
        return $map[$edu];
    }

    private function studyFormToType(string $form): int
    {
        if ($form === '') {
            return 0;
        }
        if (mb_strpos($form, '非全日制') !== false || mb_strpos($form, '不脱产') !== false) {
            return 2;
        }
        if (mb_strpos($form, '全日制') !== false || mb_strpos($form, '脱产') !== false) {
            return 1;
        }
        return 2;
    }

    private function pick(array $fields, array $labels): string
    {
        foreach ($labels as $label) {
            if (isset($fields[$label]) && $fields[$label] !== '') {
                return $fields[$label];
            }
        }
        return '';
    }

    private function firstText(\DOMXPath $xp, string $query, ?\DOMNode $ctx = null): string
    {
        $nodes = $ctx ? $xp->query($query, $ctx) : $xp->query($query);
        if (!$nodes || $nodes->length === 0) {
            return '';
        }
        return trim(preg_replace('/\s+/u', ' ', $nodes->item(0)->textContent));
    }
}
