<?php
// +----------------------------------------------------------------------
// | 扫码下单 - 台号 Repository
// +----------------------------------------------------------------------

namespace app\common\repositories\store\scanOrder;

use app\common\dao\store\scanOrder\ScanOrderTableDao;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\scanOrder\ScanOrderTable;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\attachment\AttachmentRepository;
use crmeb\services\QrcodeService;
use think\exception\ValidateException;

class ScanOrderTableRepository extends BaseRepository
{
    public function __construct(ScanOrderTableDao $dao)
    {
        $this->dao = $dao;
    }

    public function getList(int $merId, int $page, int $limit): array
    {
        $limit = min(max($limit, 1), 100);
        $query = ScanOrderTable::getDB()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->order('id DESC');

        $count = (clone $query)->count();
        $list = $query->page($page, $limit)->select()->toArray();

        foreach ($list as &$item) {
            $item['jump_url'] = ScanOrderSign::jumpUrl((int)$item['mer_id'], (int)$item['id']);
            $item['sign'] = ScanOrderSign::make((int)$item['mer_id'], (int)$item['id']);
            try {
                $item['qrcode'] = $this->getQrcodeUrl((int)$item['mer_id'], (int)$item['id'], (string)$item['table_label'], false);
            } catch (\Throwable $e) {
                $item['qrcode'] = '';
            }
        }
        unset($item);

        return compact('count', 'list');
    }

    /**
     * C 端选桌：轻量列表（不含二维码）
     */
    public function getPublicTableList(int $merId): array
    {
        $list = ScanOrderTable::getDB()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->field('id,mer_id,table_label,sort')
            ->order('sort ASC, id ASC')
            ->select()
            ->toArray();

        foreach ($list as &$item) {
            $item['table_id'] = (int)$item['id'];
            $item['sign'] = ScanOrderSign::make((int)$item['mer_id'], (int)$item['id']);
        }
        unset($item);

        return $list;
    }

    public function createTable(int $merId, string $tableLabel): array
    {
        $tableLabel = trim($tableLabel);
        if ($tableLabel === '') {
            throw new ValidateException('请填写台号文案');
        }
        if (mb_strlen($tableLabel) > 20) {
            throw new ValidateException('台号文案最长20字符');
        }

        $id = (int)$this->dao->create([
            'mer_id' => $merId,
            'table_label' => $tableLabel,
            'qrcode_name' => '',
            'sort' => 0,
            'is_del' => 0,
            'create_time' => date('Y-m-d H:i:s'),
        ])->getKey();

        $qrcode = '';
        try {
            $qrcode = $this->getQrcodeUrl($merId, $id, $tableLabel, true);
            ScanOrderTable::getDB()->where('id', $id)->update([
                'qrcode_name' => md5('scan_order_jump_v1_' . $merId . '_' . $id) . '.jpg',
            ]);
        } catch (\Throwable $e) {
            // 二维码失败不阻断创建，下载时可重试
        }

        return [
            'id' => $id,
            'mer_id' => $merId,
            'table_label' => $tableLabel,
            'jump_url' => ScanOrderSign::jumpUrl($merId, $id),
            'sign' => ScanOrderSign::make($merId, $id),
            'qrcode' => $qrcode,
        ];
    }

    public function deleteTable(int $merId, int $tableId): void
    {
        if (!$this->dao->merHas($merId, $tableId, 0)) {
            throw new ValidateException('台号不存在');
        }
        if ($this->hasUnfinishedOrder($merId, $tableId)) {
            throw new ValidateException('该台号有进行中的订单，请处理后再删除');
        }
        $this->dao->update($tableId, ['is_del' => 1]);
    }

    public function getDetail(int $merId, int $tableId): array
    {
        $row = ScanOrderTable::getDB()
            ->where('id', $tableId)
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->find();
        if (!$row) {
            throw new ValidateException('台号不存在');
        }
        $data = $row->toArray();
        $data['jump_url'] = ScanOrderSign::jumpUrl($merId, $tableId);
        $data['sign'] = ScanOrderSign::make($merId, $tableId);
        return $data;
    }

    /**
     * 校验台号归属 + URL 签名
     */
    public function assertTableAccess(int $merId, int $tableId, string $sign = '', bool $requireSign = true): array
    {
        $row = ScanOrderTable::getDB()
            ->where('id', $tableId)
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->find();
        if (!$row) {
            throw new ValidateException('台号无效或不属于该商户');
        }
        if ($requireSign && !ScanOrderSign::check($merId, $tableId, $sign)) {
            throw new ValidateException('二维码签名校验失败，请重新扫码');
        }
        return $row->toArray();
    }

    public function hasUnfinishedOrder(int $merId, int $tableId): bool
    {
        try {
            // 未支付 / 未完成（status != 3）且未删除
            return StoreOrder::getDB()
                ->where('mer_id', $merId)
                ->where('is_scan_order', 1)
                ->where('scan_table_id', $tableId)
                ->where('is_del', 0)
                ->where(function ($q) {
                    $q->where('paid', 0)->whereOr('status', '<>', 3);
                })
                ->count() > 0;
        } catch (\Throwable $e) {
            // 字段未迁移时不拦截删除
            return false;
        }
    }

    public function getQrcodeUrl(int $merId, int $tableId, string $tableLabel = '', bool $forceRefresh = false): string
    {
        $siteUrl = rtrim((string)systemConfig('site_url'), '/');
        // v3：台号文案画在二维码下方（不遮挡码区）
        $name = md5('scan_order_jump_v3_' . $merId . '_' . $tableId . '_' . $tableLabel) . '.png';
        $codeUrl = ScanOrderSign::jumpUrl($merId, $tableId);

        $attachmentRepository = app()->make(AttachmentRepository::class);
        $imageInfo = $attachmentRepository->getWhere(['attachment_name' => $name]);
        if ($forceRefresh && $imageInfo) {
            $imageInfo->delete();
            $imageInfo = null;
        }
        if (isset($imageInfo['attachment_src']) && strstr($imageInfo['attachment_src'], 'http') !== false && curl_file_exist($imageInfo['attachment_src']) === false) {
            $imageInfo->delete();
            $imageInfo = null;
        }
        if (!$imageInfo) {
            $imageInfo = app()->make(QrcodeService::class)->getQRCodePath($codeUrl, $name);
            if (is_string($imageInfo)) {
                throw new ValidateException('二维码生成失败');
            }
            $imageInfo['dir'] = tidy_url($imageInfo['dir'], null, $siteUrl);
            // 本地叠加台号水印（≥300x300）
            $watermarked = $this->overlayTableLabel($imageInfo, $tableLabel);
            if ($watermarked) {
                $imageInfo['dir'] = $watermarked;
            }
            $attachmentRepository->create(systemConfig('upload_type') ?: 1, -2, $merId, [
                'attachment_category_id' => 0,
                'attachment_name' => $imageInfo['name'],
                'attachment_src' => $imageInfo['dir'],
            ]);
            return $imageInfo['dir'];
        }
        return $imageInfo['attachment_src'];
    }

    /**
     * 台号文案画在二维码下方（不遮挡码区，避免扫不出）
     */
    protected function overlayTableLabel(array $imageInfo, string $tableLabel): string
    {
        $tableLabel = trim($tableLabel);
        if ($tableLabel === '' || !function_exists('imagecreatefromstring')) {
            return '';
        }
        try {
            $src = (string)($imageInfo['dir'] ?? '');
            $bin = '';
            if ($src && strpos($src, 'http') === 0) {
                $bin = (string)@file_get_contents($src);
            }
            $local = './public/' . ltrim((string)config('qrcode.cache_dir'), '/') . '/' . ($imageInfo['name'] ?? '');
            if ($bin === '' && is_file($local)) {
                $bin = (string)file_get_contents($local);
            }
            if ($bin === '') {
                return '';
            }
            $qr = @imagecreatefromstring($bin);
            if (!$qr) {
                return '';
            }
            $qw = imagesx($qr);
            $qh = imagesy($qr);
            // 码区至少 300
            $side = max(300, $qw, $qh);
            $labelH = 56;
            $canvasW = $side;
            $canvasH = $side + $labelH;
            $im = imagecreatetruecolor($canvasW, $canvasH);
            $white = imagecolorallocate($im, 255, 255, 255);
            $black = imagecolorallocate($im, 32, 32, 32);
            imagefill($im, 0, 0, $white);
            // 二维码居中贴上半部分，码区完整不被遮挡
            $dx = (int)(($side - $qw) / 2);
            $dy = (int)(($side - $qh) / 2);
            imagecopy($im, $qr, $dx, $dy, 0, 0, $qw, $qh);
            imagedestroy($qr);

            $fontFile = $this->findCjkFont();
            if ($fontFile && function_exists('imagettftext')) {
                $size = 18;
                $box = @imagettfbbox($size, 0, $fontFile, $tableLabel);
                $tw = $box ? abs(($box[2] ?? 0) - ($box[0] ?? 0)) : 0;
                $tx = (int)max(4, ($canvasW - $tw) / 2);
                $ty = $side + 36;
                imagettftext($im, $size, 0, $tx, $ty, $black, $fontFile, $tableLabel);
            } else {
                // 无中文字体时用内置字体（仅 ASCII 清晰；中文请装字体）
                $font = 5;
                $tw = imagefontwidth($font) * strlen($tableLabel);
                $tx = (int)max(4, ($canvasW - $tw) / 2);
                $ty = $side + (int)(($labelH - imagefontheight($font)) / 2);
                imagestring($im, $font, $tx, $ty, $tableLabel, $black);
            }

            $outfileDir = './public/' . ltrim((string)config('qrcode.cache_dir'), '/');
            if (!is_dir($outfileDir)) {
                @mkdir($outfileDir, 0777, true);
            }
            $file = $outfileDir . '/' . ($imageInfo['name'] ?? ('scan_' . time() . '.png'));
            imagepng($im, $file);
            imagedestroy($im);
            $siteUrl = rtrim((string)systemConfig('site_url'), '/');
            return $siteUrl . '/' . ltrim((string)config('qrcode.cache_dir'), '/') . '/' . basename($file);
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function findCjkFont(): string
    {
        $candidates = [
            public_path() . 'static/fonts/SourceHanSansCN-Regular.otf',
            public_path() . 'static/fonts/msyh.ttf',
            '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
            '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            'C:/Windows/Fonts/msyh.ttc',
            'C:/Windows/Fonts/simhei.ttf',
        ];
        foreach ($candidates as $f) {
            if ($f && is_file($f)) {
                return $f;
            }
        }
        return '';
    }
}
