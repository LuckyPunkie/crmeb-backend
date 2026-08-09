<?php

namespace crmeb\services;

/**
 * 视频兼容处理：截封面 + HEVC 转 H.264（小程序可播）
 */
class VideoCoverService
{
    /**
     * @param string $videoPath 本地绝对路径或站点相对/完整 URL
     * @return string|null 封面完整 URL
     */
    public static function extract(string $videoPath): ?string
    {
        $local = self::resolveLocalPath($videoPath);
        if (!$local || !is_file($local)) {
            return null;
        }

        $dateDir = date('Ymd');
        $relDir = 'uploads/def/' . $dateDir;
        $absDir = rtrim(public_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0755, true);
        }

        $name = 'vc_' . date('His') . '_' . substr(md5($local . microtime(true)), 0, 8) . '.jpg';
        $absCover = $absDir . DIRECTORY_SEPARATOR . $name;

        $ok = self::extractByFfmpeg($local, $absCover) || self::extractByPython($local, $absCover);
        if (!$ok || !is_file($absCover) || filesize($absCover) < 100) {
            return null;
        }

        $site = rtrim((string)systemConfig('site_url'), '/');
        return $site . '/' . $relDir . '/' . $name;
    }

    /**
     * 从视频 URL 截取封面（仅本站 uploads）
     */
    public static function extractFromUrl(string $videoUrl): ?string
    {
        return self::extract($videoUrl);
    }

    /**
     * 若视频为 HEVC/H.265，转码为 H.264，返回可播放的本地路径；否则返回原路径
     * @return array{path:string,changed:bool,url:?string}
     */
    public static function ensureH264(string $videoPath): array
    {
        $local = self::resolveLocalPath($videoPath);
        if (!$local || !is_file($local)) {
            return ['path' => $videoPath, 'changed' => false, 'url' => null];
        }

        $codec = self::probeVideoCodec($local);
        if (!$codec || !preg_match('/hevc|h265|hev1|hvc1/i', $codec)) {
            return ['path' => $local, 'changed' => false, 'url' => null];
        }

        $ffmpeg = self::findFfmpeg();
        if (!$ffmpeg) {
            return ['path' => $local, 'changed' => false, 'url' => null];
        }

        $dir = dirname($local);
        $base = pathinfo($local, PATHINFO_FILENAME);
        $out = $dir . DIRECTORY_SEPARATOR . $base . '_h264.mp4';
        // faststart 利于边下边播；yuv420p 兼容小程序
        $cmd = sprintf(
            '%s -y -i %s -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -movflags +faststart %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($local),
            escapeshellarg($out)
        );
        @exec($cmd, $output, $code);
        if ($code !== 0 || !is_file($out) || filesize($out) < 1000) {
            return ['path' => $local, 'changed' => false, 'url' => null];
        }

        // 用转码结果替换原文件，保持 URL 不变
        if (!@rename($out, $local)) {
            @copy($out, $local);
            @unlink($out);
        }

        $site = rtrim((string)systemConfig('site_url'), '/');
        $public = rtrim(str_replace('\\', '/', public_path()), '/');
        $normLocal = str_replace('\\', '/', $local);
        $rel = (strpos($normLocal, $public) === 0) ? substr($normLocal, strlen($public)) : '';
        $url = $rel ? ($site . '/' . ltrim($rel, '/')) : null;

        return ['path' => $local, 'changed' => true, 'url' => $url];
    }

    protected static function probeVideoCodec(string $local): ?string
    {
        $ffprobe = self::findFfprobe();
        $ffmpeg = self::findFfmpeg();
        if ($ffprobe) {
            $cmd = sprintf(
                '%s -v error -select_streams v:0 -show_entries stream=codec_name -of csv=p=0 %s 2>&1',
                escapeshellarg($ffprobe),
                escapeshellarg($local)
            );
            @exec($cmd, $out, $code);
            if ($code === 0 && !empty($out[0])) {
                return trim($out[0]);
            }
        }
        if ($ffmpeg) {
            $cmd = sprintf('%s -i %s 2>&1', escapeshellarg($ffmpeg), escapeshellarg($local));
            @exec($cmd, $out, $code);
            $text = implode("\n", $out);
            if (preg_match('/Video:\s*([a-zA-Z0-9_]+)/', $text, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    protected static function extractByFfmpeg(string $local, string $absCover): bool
    {
        $ffmpeg = self::findFfmpeg();
        if (!$ffmpeg) {
            return false;
        }
        $cmd = sprintf(
            '%s -y -ss 0.1 -i %s -frames:v 1 -q:v 2 -update 1 %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($local),
            escapeshellarg($absCover)
        );
        @exec($cmd, $output, $code);
        return is_file($absCover) && filesize($absCover) >= 100;
    }

    protected static function extractByPython(string $local, string $absCover): bool
    {
        $py = self::findPython();
        if (!$py) {
            return false;
        }
        $script = <<<'PY'
import sys
try:
    import cv2
except Exception:
    sys.exit(2)
local, out = sys.argv[1], sys.argv[2]
cap = cv2.VideoCapture(local)
if not cap.isOpened():
    sys.exit(3)
cap.set(cv2.CAP_PROP_POS_MSEC, 200)
ok, frame = cap.read()
if not ok:
    cap.set(cv2.CAP_PROP_POS_FRAMES, 0)
    ok, frame = cap.read()
cap.release()
if not ok:
    sys.exit(4)
cv2.imwrite(out, frame, [int(cv2.IMWRITE_JPEG_QUALITY), 90])
sys.exit(0 if __import__('os').path.isfile(out) else 5)
PY;
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vc_extract_' . md5($local . microtime(true)) . '.py';
        @file_put_contents($tmp, $script);
        $cmd = sprintf('%s %s %s %s 2>&1', escapeshellarg($py), escapeshellarg($tmp), escapeshellarg($local), escapeshellarg($absCover));
        @exec($cmd, $output, $code);
        @unlink($tmp);
        return $code === 0 && is_file($absCover) && filesize($absCover) >= 100;
    }

    protected static function findPython(): ?string
    {
        foreach (['/usr/bin/python3', '/usr/local/bin/python3', 'python3'] as $bin) {
            if ($bin === 'python3') {
                @exec('command -v python3 2>/dev/null', $out, $code);
                if ($code === 0 && !empty($out[0])) {
                    return $out[0];
                }
                continue;
            }
            if (is_executable($bin)) {
                return $bin;
            }
        }
        return null;
    }

    protected static function resolveLocalPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        if (is_file($path)) {
            return $path;
        }

        $site = rtrim((string)systemConfig('site_url'), '/');
        if ($site && strpos($path, $site) === 0) {
            $path = substr($path, strlen($site));
        }
        $path = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
        $public = rtrim(public_path(), DIRECTORY_SEPARATOR);
        $candidate = $public . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($candidate)) {
            return $candidate;
        }
        $candidate2 = $public . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        return is_file($candidate2) ? $candidate2 : null;
    }

    protected static function findFfmpeg(): ?string
    {
        foreach (['/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg', 'ffmpeg'] as $bin) {
            if ($bin === 'ffmpeg') {
                @exec('command -v ffmpeg 2>/dev/null', $out, $code);
                if ($code === 0 && !empty($out[0]) && is_executable($out[0])) {
                    return $out[0];
                }
                continue;
            }
            if (is_executable($bin)) {
                return $bin;
            }
        }
        return null;
    }

    protected static function findFfprobe(): ?string
    {
        foreach (['/usr/local/bin/ffprobe', '/usr/bin/ffprobe', 'ffprobe'] as $bin) {
            if ($bin === 'ffprobe') {
                @exec('command -v ffprobe 2>/dev/null', $out, $code);
                if ($code === 0 && !empty($out[0]) && is_executable($out[0])) {
                    return $out[0];
                }
                continue;
            }
            if (is_executable($bin)) {
                return $bin;
            }
        }
        return null;
    }
}
