<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services\security\captcha;

use Fastknife\Domain\Factory;
use Fastknife\Domain\Vo\PointVo;
use Fastknife\Utils\RandomUtils;
use think\facade\Config;

/**
 * 滑块拼图生成器。
 *
 * 替代 aj-captcha 库的生成环节（验证环节仍复用库的一次/二次验证）。
 * 生成环节自建的原因：
 * - 库的拼图块直接复制背景纹理，AI 可通过“拼图块纹理 vs 背景各位置纹理”
 *   的相似度匹配定位缺口；
 * - 库的缺口形状来自公开的固定模板图（resources/.../slidingBlock/*.png），
 *   AI 可预先获取模板做形状匹配。
 *
 * 本生成器每次请求程序化生成随机形状（主体宽高、凸起、凹槽参数全随机），
 * 拼图块以半透明白色填充、不携带任何背景纹理信息；输出格式与库一致，
 * 场景数据以相同结构写入库缓存，验证链路（AES 凭证、缺口比对、一次性消费）
 * 完全复用 Fastknife 库，无需修改 vendor。
 *
 * @package crmeb\services\security\captcha
 */
class JigsawCaptchaGenerator
{
    /**
     * 形状画布尺寸（像素）。
     *
     * 正方形拼图块（47x47），长宽比适中便于用户对齐；前端组件
     * 按 “宽 47/310、高 47/155” 计算拼图块显示容器尺寸，与缺口
     * 显示比例一致。缺口垂直位置随机（0..108），通过响应字段
     * pointY 告知前端拼图块的对齐位置。
     */
    private const SHAPE_WIDTH = 47;

    private const SHAPE_HEIGHT = 47;

    /**
     * 拼图块半透明白填充的 GD alpha（0=不透明，127=全透明；28 ≈ 78% 不透明）。
     */
    private const PUZZLE_FILL_ALPHA = 28;

    /**
     * 判断形状掩码像素是否不透明（与库 isOpacity 的 0.5 阈值对应）。
     */
    private const OPAQUE_ALPHA_LIMIT = 64;

    /**
     * 生成一张完整的滑块验证场景。
     *
     * @param array|null $config ajcaptcha 配置，缺省从框架配置读取。
     * @return array 与 aj_captcha_create('blockPuzzle') 同构：originalImageBase64 / jigsawImageBase64 / secretKey / token。
     */
    public function generate(?array $config = null): array
    {
        $config = $config ?? Config::get('ajcaptcha');
        $puzzleConfig = $config['block_puzzle'] ?? [];

        // 随机背景图
        $bg = $this->loadBackground($puzzleConfig['backgrounds'] ?? []);
        $bgW = imagesx($bg);
        $bgH = imagesy($bg);

        // 真缺口：背景右半区随机位置，垂直位置随机（前端通过 pointY 对齐拼图块）
        $maxX = max(0, intval($bgW / 2));
        $gapX = RandomUtils::getRandomInt($maxX, max($maxX + 1, $bgW - self::SHAPE_WIDTH - 1));
        $gapY = RandomUtils::getRandomInt(0, max(0, $bgH - self::SHAPE_HEIGHT));
        $shape = $this->createRandomShape();
        // 形状实际最左不透明列：形状可在画布内居中布局（圆形完整不贴边），
        // 缺口左缘 = gapX + shapeMinX，与前端拼图块左缘对齐一致
        $shapeMinX = $this->getShapeMinX($shape);
        $puzzle = $this->carve($bg, $shape, $gapX, $gapY, true);

        // 干扰缺口：与真缺口使用同一形状（外观完全一致，AI 无法区分真伪），
        // 位置随机（含垂直位置）且与真缺口保持距离，不生成拼图块
        if (!empty($puzzleConfig['is_interfere'])) {
            $minDistance = intval(self::SHAPE_WIDTH / 2);
            $offset = null;
            for ($attempt = 0; $attempt < 60; $attempt++) {
                $candidate = RandomUtils::getRandomInt(0, max(0, $bgW - self::SHAPE_WIDTH - 1));
                if (abs($candidate - $gapX) > $minDistance) {
                    $offset = $candidate;
                    break;
                }
            }
            if ($offset !== null) {
                $interfereY = RandomUtils::getRandomInt(0, max(0, $bgH - self::SHAPE_HEIGHT));
                $this->carve($bg, $shape, $offset, $interfereY, false);
            }
        }

        // 水印
        $this->watermark($bg, $config);

        // 编码：背景按 JPEG（与原库一致），拼图块为带透明通道的 PNG
        ob_start();
        imagejpeg($bg, null, 95);
        $bgData = (string) ob_get_clean();
        ob_start();
        imagepng($puzzle, null, 9);
        $puzzleData = (string) ob_get_clean();
        imagedestroy($bg);
        imagedestroy($puzzle);

        $secretKey = RandomUtils::getRandomCode(16, 3);
        $token = RandomUtils::getUUID();

        // 场景写入库缓存（结构与 BlockPuzzleCaptchaService::get 一致），验证链路复用库
        (new Factory($config))->getCacheInstance()->set($token, [
            'secretKey' => $secretKey,
            'point' => new PointVo($gapX + $shapeMinX, 5),
        ], 7200);

        return [
            'originalImageBase64' => base64_encode($bgData),
            'jigsawImageBase64' => base64_encode($puzzleData),
            'secretKey' => $secretKey,
            'token' => $token,
            // 缺口垂直位置（0..108），前端用于对齐拼图块显示位置
            'pointY' => $gapY,
        ];
    }

    /**
     * 程序化生成随机缺口形状（带 alpha 的形状掩码图）。
     *
     * 五种缺口风格等概率随机：三角形、圆形、菱形、五边形，以及“矩形主体+
     * 右侧凸起+左侧凹槽”的现款。每种风格的参数（尺寸/位置）仍带随机性，
     * 形状先验更难建立。
     *
     * 核心不变式：所有形状的最左不透明范围必须触及掩码 x=0 ——
     * generate() 以 point.x = gapX 记录缺口最左缘，前端用户按拼图块左缘
     * 与缺口左缘对齐，形状最左像素在 x=0 保证对齐目标与坐标校验完全一致。
     *
     * @return \GdImage
     */
    private function createRandomShape()
    {
        $image = imagecreatetruecolor(self::SHAPE_WIDTH, self::SHAPE_HEIGHT);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $opaque = imagecolorallocatealpha($image, 255, 255, 255, 0);
        imagefill($image, 0, 0, $transparent);

        switch (random_int(0, 4)) {
            case 0:
                $this->drawBlockShape($image, $opaque, $transparent);
                break;
            case 1:
                $this->drawTriangleShape($image, $opaque);
                break;
            case 2:
                $this->drawCircleShape($image, $opaque);
                break;
            case 3:
                $this->drawDiamondShape($image, $opaque);
                break;
            default:
                $this->drawPentagonShape($image, $opaque);
                break;
        }

        return $image;
    }

    /**
     * 绘制填充多边形，兼容不同 GD 版本。
     *
     * PHP 8.0 及更早的 GD 要求传入点数参数（$num_points）；
     * PHP 8.1+ 的 GD 已弃用该参数（省略可消除弃用警告，但旧版会直接报错）。
     * 按 PHP 版本分派，保证两个环境都正常。
     *
     * @param \GdImage $image 目标图。
     * @param array $points 顶点坐标扁平数组 [x1, y1, x2, y2, ...]。
     * @param int $color 颜色。
     * @return void
     */
    private function fillPolygon($image, array $points, $color): void
    {
        if (PHP_VERSION_ID >= 80100) {
            imagefilledpolygon($image, $points, $color);
        } else {
            imagefilledpolygon($image, $points, intdiv(count($points), 2), $color);
        }
    }

    /**
     * 计算形状掩码最左不透明列（x 坐标）。
     *
     * 形状可在画布内自由布局（不强制贴 x=0），缺口最左缘 = gapX + 该值。
     *
     * @param \GdImage $shape 形状掩码图。
     * @return int
     */
    private function getShapeMinX($shape): int
    {
        for ($x = 0; $x < self::SHAPE_WIDTH; $x++) {
            for ($y = 0; $y < self::SHAPE_HEIGHT; $y++) {
                if ($this->isShapeOpaque($shape, $x, $y)) {
                    return $x;
                }
            }
        }

        return 0;
    }

    /**
     * 绘制“矩形主体 + 右侧凸起 + 左侧凹槽”款（现款）。
     *
     * 主体全高（0..46）左缘贴 x=0 直边；凸起半径与垂直位置随机；
     * 凹槽在左缘镂空弧形凹口。全高设计避免拼图块画布透明区域
     * 在白色弹窗上透出白色，形成“白色长方形”观感。
     *
     * @param \GdImage $image 掩码图。
     * @param int $opaque 不透明色。
     * @param int $transparent 透明色。
     * @return void
     */
    private function drawBlockShape($image, $opaque, $transparent): void
    {
        $bodyWidth = RandomUtils::getRandomInt(26, 34);
        imagefilledrectangle($image, 0, 0, $bodyWidth - 1, self::SHAPE_HEIGHT - 1, $opaque);

        $bumpRadius = RandomUtils::getRandomInt(9, 12);
        $bumpY = RandomUtils::getRandomInt($bumpRadius + 6, self::SHAPE_HEIGHT - $bumpRadius - 6);
        imagefilledellipse($image, $bodyWidth - 1, $bumpY, $bumpRadius * 2, $bumpRadius * 2, $opaque);

        $notchRadius = RandomUtils::getRandomInt(5, 9);
        $notchY = RandomUtils::getRandomInt($notchRadius + 6, self::SHAPE_HEIGHT - $notchRadius - 6);
        imagefilledellipse($image, 0, $notchY, $notchRadius * 2, $notchRadius * 2, $transparent);
    }

    /**
     * 绘制整个三角形缺口（严格等腰）：底边竖直贴左缘，顶点在中垂线上。
     *
     * @param \GdImage $image 掩码图。
     * @param int $opaque 不透明色。
     * @return void
     */
    private function drawTriangleShape($image, $opaque): void
    {
        $y1 = RandomUtils::getRandomInt(8, 14);
        $y2 = min(self::SHAPE_HEIGHT - 6, $y1 + RandomUtils::getRandomInt(24, 30));
        $apexX = RandomUtils::getRandomInt(40, 44);
        // 顶点 y 严格取底边中点（intdiv），保证等腰
        $this->fillPolygon($image, [0, $y1, 0, $y2, $apexX, intdiv($y1 + $y2, 2)], $opaque);
    }

    /**
     * 绘制整个圆形缺口（完整圆）：画布内居中，四周留边，描边完整。
     *
     * @param \GdImage $image 掩码图。
     * @param int $opaque 不透明色。
     * @return void
     */
    private function drawCircleShape($image, $opaque): void
    {
        $radius = RandomUtils::getRandomInt(19, 21);
        imagefilledellipse($image, 23, 23, $radius * 2, $radius * 2, $opaque);
    }

    /**
     * 绘制整个菱形缺口：画布内居中，左顶点 x=3 四周留边。
     *
     * @param \GdImage $image 掩码图。
     * @param int $opaque 不透明色。
     * @return void
     */
    private function drawDiamondShape($image, $opaque): void
    {
        $centerY = RandomUtils::getRandomInt(22, 24);
        $halfWidth = RandomUtils::getRandomInt(19, 20);
        $this->fillPolygon($image, [
            3, $centerY,
            3 + $halfWidth, $centerY - $halfWidth,
            3 + $halfWidth * 2, $centerY,
            3 + $halfWidth, $centerY + $halfWidth,
        ], $opaque);
    }

    /**
     * 绘制整个五边形缺口（房形）：左侧直边段 x=2，四周留边。
     *
     * @param \GdImage $image 掩码图。
     * @param int $opaque 不透明色。
     * @return void
     */
    private function drawPentagonShape($image, $opaque): void
    {
        $apexY = RandomUtils::getRandomInt(8, 12);
        $this->fillPolygon($image, [
            2, self::SHAPE_HEIGHT - 1,
            2, $apexY + 14,
            23, $apexY,
            44, $apexY + 14,
            44, self::SHAPE_HEIGHT - 1,
        ], $opaque);
    }

    /**
     * 在背景上抠出缺口：模糊选区 + 白描边，并生成对应的拼图块。
     *
     * @param \GdImage $bg 背景图。
     * @param \GdImage $shape 形状掩码图。
     * @param int $gapX 缺口左缘在背景上的 x 坐标。
     * @param int $gapY 缺口顶缘在背景上的 y 坐标（垂直位置随机，y 不参与坐标校验）。
     * @param bool $withPuzzle 是否生成拼图块（真缺口 true，干扰缺口 false）。
     * @return \GdImage|null 拼图块图（带 alpha），不生成时为 null。
     */
    private function carve($bg, $shape, int $gapX, int $gapY, bool $withPuzzle)
    {
        $width = self::SHAPE_WIDTH;
        $height = self::SHAPE_HEIGHT;
        $bgW = imagesx($bg);
        $bgH = imagesy($bg);

        $puzzle = null;
        $fillColor = null;
        $boundaryColor = null;
        if ($withPuzzle) {
            $puzzle = imagecreatetruecolor($width, $height);
            imagealphablending($puzzle, true);
            imagesavealpha($puzzle, true);
            imagefill($puzzle, 0, 0, imagecolorallocatealpha($puzzle, 0, 0, 0, 127));
            $fillColor = imagecolorallocatealpha($puzzle, 255, 255, 255, self::PUZZLE_FILL_ALPHA);
            $boundaryColor = imagecolorallocatealpha($puzzle, 255, 255, 255, 0);
        }

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $opaque = $this->isShapeOpaque($shape, $x, $y);
                $boundary = $this->isShapeBoundary($shape, $x, $y, $opaque);

                $bgX = $x + $gapX;
                $bgY = $y + $gapY;
                if ($bgX >= $bgW || $bgY >= $bgH) {
                    continue;
                }

                if ($opaque || $boundary) {
                    [$r, $g, $b] = $this->blurPixel($bg, $bgX, $bgY, $bgW, $bgH);
                    imagesetpixel($bg, $bgX, $bgY, imagecolorallocate($bg, $r, $g, $b));
                }

                if ($boundary) {
                    imagesetpixel($bg, $bgX, $bgY, imagecolorallocate($bg, 255, 255, 255));
                    if ($withPuzzle) {
                        imagesetpixel($puzzle, $x, $y, $boundaryColor);
                    }
                } elseif ($opaque && $withPuzzle) {
                    // 拼图块纯色填充，不复制背景纹理（破坏 AI 纹理匹配）
                    imagesetpixel($puzzle, $x, $y, $fillColor);
                }
            }
        }

        return $puzzle;
    }

    /**
     * 判断形状掩码像素是否不透明。
     *
     * @param \GdImage $shape 形状掩码图。
     * @param int $x 横坐标。
     * @param int $y 纵坐标。
     * @return bool
     */
    private function isShapeOpaque($shape, int $x, int $y): bool
    {
        $rgba = imagecolorat($shape, $x, $y);

        return (($rgba >> 24) & 0x7F) < self::OPAQUE_ALPHA_LIMIT;
    }

    /**
     * 判断形状掩码像素是否为边界（四方向检测）。
     *
     * 原库只比较右/下邻居，导致贴画布左/上边缘的形状（如圆在 x=0 的切点、
     * 三角形贴边底边）没有描边，视觉上“缺口/缺边”。改为四方向检测后，
     * 所有边缘（含贴边与凹槽内缘）都有完整白描边。
     *
     * @param \GdImage $shape 形状掩码图。
     * @param int $x 横坐标。
     * @param int $y 纵坐标。
     * @param bool $opaque 当前像素是否不透明。
     * @return bool
     */
    private function isShapeBoundary($shape, int $x, int $y, bool $opaque): bool
    {
        $left = $x > 0 ? $this->isShapeOpaque($shape, $x - 1, $y) : false;
        $right = $x < self::SHAPE_WIDTH - 1 ? $this->isShapeOpaque($shape, $x + 1, $y) : false;
        $up = $y > 0 ? $this->isShapeOpaque($shape, $x, $y - 1) : false;
        $down = $y < self::SHAPE_HEIGHT - 1 ? $this->isShapeOpaque($shape, $x, $y + 1) : false;

        return ($opaque && (!$left || !$right || !$up || !$down))
            || (!$opaque && ($left || $right || $up || $down));
    }

    /**
     * 8 邻域平均模糊（与原库 vagueImage 行为一致）。
     *
     * @param \GdImage $image 目标图。
     * @param int $x 横坐标。
     * @param int $y 纵坐标。
     * @param int $width 图宽。
     * @param int $height 图高。
     * @return array{r: int, g: int, b: int}
     */
    private function blurPixel($image, int $x, int $y, int $width, int $height): array
    {
        $r = $g = $b = $count = 0;
        for ($dy = -1; $dy <= 1; $dy++) {
            for ($dx = -1; $dx <= 1; $dx++) {
                $px = $x + $dx;
                $py = $y + $dy;
                if ($px < 0 || $px >= $width || $py < 0 || $py >= $height) {
                    continue;
                }
                $rgb = imagecolorat($image, $px, $py);
                $r += ($rgb >> 16) & 0xFF;
                $g += ($rgb >> 8) & 0xFF;
                $b += $rgb & 0xFF;
                $count++;
            }
        }

        return [
            (int) round($r / max(1, $count)),
            (int) round($g / max(1, $count)),
            (int) round($b / max(1, $count)),
        ];
    }

    /**
     * 加载随机背景图并统一缩放到前端 310x155 虚拟坐标空间。
     *
     * 前端 verifition 组件把用户点击/拖动的坐标按 “× 310 / 显示宽度” 换算到
     * 310x155 虚拟空间后上报；若背景图原始宽度不是 310，缺口坐标会产生
     * 系统性偏差（如 315px 背景在缺口 x≈250 处偏差约 4px，收紧容错后会
     * 频繁误判）。统一缩放后图片像素空间与前端虚拟空间一致，坐标零偏差。
     *
     * @param mixed $backgrounds 背景配置（路径数组或目录字符串）。
     * @return \GdImage
     */
    private function loadBackground($backgrounds)
    {
        if (is_string($backgrounds) && $backgrounds !== '') {
            $files = glob($backgrounds . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
            $backgrounds = is_array($files) ? $files : [];
        }
        $backgrounds = array_values(array_filter((array) $backgrounds, 'is_file'));
        if ($backgrounds === []) {
            throw new \RuntimeException('滑块验证码背景图资源不存在，请联系管理员');
        }

        $image = @imagecreatefromstring((string) file_get_contents($backgrounds[array_rand($backgrounds)]));
        if ($image === false) {
            throw new \RuntimeException('滑块验证码背景图解析失败，请联系管理员');
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        if ($srcW === 310 && $srcH === 155) {
            return $image;
        }

        $canvas = imagecreatetruecolor(310, 155);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, 310, 155, $srcW, $srcH);
        imagedestroy($image);

        return $canvas;
    }

    /**
     * 绘制右下角水印。
     *
     * @param \GdImage $bg 背景图。
     * @param array $config ajcaptcha 配置。
     * @return void
     */
    private function watermark($bg, array $config): void
    {
        $watermark = $config['watermark'] ?? [];
        $text = (string) ($watermark['text'] ?? 'CRMEB');
        if ($text === '') {
            return;
        }

        $fontSize = (int) ($watermark['fontsize'] ?? 12);
        $fontFile = trim((string) ($config['font_file'] ?? ''));
        if ($fontFile === '' || !is_file($fontFile)) {
            $fontFile = dirname(__DIR__, 4) . '/vendor/fastknife/ajcaptcha/resources/fonts/WenQuanZhengHei.ttf';
        }
        if (!is_file($fontFile)) {
            return;
        }

        $color = (string) ($watermark['color'] ?? '#FFFFFF');
        $r = (int) hexdec(substr($color, 1, 2));
        $g = (int) hexdec(substr($color, 3, 2));
        $b = (int) hexdec(substr($color, 5, 2));

        $box = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth = max($box[2], $box[4]) - min($box[0], $box[6]);
        $textHeight = max($box[1], $box[3]) - min($box[5], $box[7]);

        imagettftext(
            $bg,
            $fontSize,
            0,
            imagesx($bg) - $textWidth - 4,
            imagesy($bg) - 4,
            imagecolorallocate($bg, $r, $g, $b),
            $fontFile,
            $text
        );
    }
}
