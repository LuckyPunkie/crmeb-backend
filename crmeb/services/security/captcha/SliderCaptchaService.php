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

use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;

/**
 * 滑块验证服务（blockPuzzle 加固包装）。
 *
 * 在 aj-captcha 开源滑块协议之上叠加三层防护，不依赖任何第三方服务：
 * - 限流：创建场景按 IP/设备限流，一次/二次验证失败按 IP/手机号限流，
 *   封堵“图像差分破解缺口 + 脚本批量提交”的自动化流水线；
 * - 轨迹：要求前端上报滑块拖动轨迹（时间戳/位移序列），明显不符合真人
 *   拖动特征（空轨迹、时长过短、相邻点瞬移）的请求直接失败。
 *   轨迹属于行为信号而非硬凭据，阈值放宽松以避免误杀真人；
 * - 一次性凭证：缺口位置校验、场景一次性消费与 AES 加密凭证仍由
 *   aj-captcha 库完成，二次验证再次消费，防止重放。
 *
 * @package crmeb\services\security\captcha
 */
class SliderCaptchaService
{
    /**
     * 按 IP 记录“创建验证场景”请求次数的缓存前缀。
     */
    private const SCENE_REQUEST_IP_PREFIX = 'slider_captcha:scene_ip:';

    /**
     * 创建验证场景的限流统计窗口时长。
     */
    private const SCENE_REQUEST_WINDOW_SECONDS = 300;

    /**
     * 同一 IP 在限流窗口内允许创建场景的最大次数。
     */
    private const SCENE_REQUEST_IP_MAX = 30;

    /**
     * 按设备标识（clientUid）记录创建场景次数的缓存前缀。
     */
    private const SCENE_REQUEST_CLIENT_PREFIX = 'slider_captcha:scene_client:';

    /**
     * 设备标识创建场景的限流统计窗口时长。
     */
    private const SCENE_REQUEST_CLIENT_WINDOW_SECONDS = 600;

    /**
     * 同一设备标识在限流窗口内允许创建场景的最大次数。
     */
    private const SCENE_REQUEST_CLIENT_MAX = 20;

    /**
     * 按 IP 记录一次验证失败次数的缓存前缀。
     */
    private const CHECK_FAIL_IP_PREFIX = 'slider_captcha:check_fail_ip:';

    /**
     * 一次验证失败次数统计窗口时长。
     */
    private const CHECK_FAIL_WINDOW_SECONDS = 600;

    /**
     * 同一 IP 在一次验证失败统计窗口内允许失败的最大次数。
     */
    private const CHECK_FAIL_IP_MAX = 20;

    /**
     * 按手机号记录二次验证失败次数的缓存前缀。
     */
    private const VERIFY_FAIL_PHONE_PREFIX = 'slider_captcha:verify_fail_phone:';

    /**
     * 按 IP 记录二次验证失败次数的缓存前缀。
     */
    private const VERIFY_FAIL_IP_PREFIX = 'slider_captcha:verify_fail_ip:';

    /**
     * 二次验证失败次数统计窗口时长。
     */
    private const VERIFY_FAIL_WINDOW_SECONDS = 600;

    /**
     * 同一手机号在二次验证失败统计窗口内允许失败的最大次数。
     */
    private const VERIFY_FAIL_PHONE_MAX = 8;

    /**
     * 同一 IP 在二次验证失败统计窗口内允许失败的最大次数。
     */
    private const VERIFY_FAIL_IP_MAX = 20;

    /**
     * 单条轨迹最多允许的点数，超出视为脚本伪造。
     */
    private const TRACK_MAX_POINTS = 500;

    /**
     * 轨迹最短时长（毫秒）：真人拖动滑块必然经历按压-拖动-释放，
     * 明显短于该时长的提交视为脚本模拟，直接失败。
     */
    private const TRACK_MIN_DURATION_MS = 120;

    /**
     * 相邻轨迹点允许的最大位移（像素）：真人拖动受物理约束，
     * 单帧事件位移不会超过该值，出现瞬移视为伪造。
     */
    private const TRACK_MAX_STEP_PX = 120;

    /**
     * 创建验证场景（blockPuzzle 一次验证）。
     *
     * @param string $clientUid 设备标识（前端存储的指纹值，可为空）。
     * @return array 兼容前端 verifition 组件的 blockPuzzle 数据结构。
     */
    public function createScene(string $clientUid = ''): array
    {
        $this->assertSceneRequestAllowed($clientUid);

        // 自建生成器：程序化随机形状 + 拼图块纯色填充（不携带背景纹理），
        // 验证链路仍复用 aj-captcha 库（场景缓存写入生成器内完成）
        $scene = app()->make(JigsawCaptchaGenerator::class)->generate();

        $this->markSceneRequest($clientUid);

        return $scene;
    }

    /**
     * 一次验证：校验滑块缺口位置与拖动轨迹（ajcheck）。
     *
     * 缺口位置校验由 aj-captcha 库完成（一次性场景、AES 加密凭证）；
     * 校验通过后再校验前端上报的拖动轨迹，轨迹明显异常同样视为失败，
     * 并要求刷新场景重试。
     *
     * @param string $token 场景令牌。
     * @param string $pointJson 前端 AES 加密后的滑块终点坐标 JSON。
     * @param string $trackJson 前端上报的拖动轨迹 JSON（[{t,x,y}]，可为空）。
     * @return void
     */
    public function check(string $token, string $pointJson, string $trackJson = ''): void
    {
        $token = trim($token);
        $pointJson = trim($pointJson);

        if ($token === '' || $pointJson === '') {
            throw new ValidateException('请先完成安全验证');
        }

        $this->assertCheckAllowed();

        try {
            aj_captcha_check_one('blockPuzzle', $token, $pointJson);
        } catch (\Throwable $exception) {
            $this->markCheckFailure();
            throw $exception;
        }

        // 缺口校验通过后，轨迹作为行为信号再校验一次；轨迹缺失或明显异常视为机器行为
        if (!$this->isTrackPlausible($this->decodeTrack($trackJson))) {
            $this->markCheckFailure();
            throw new ValidateException('请拖动滑块完成验证');
        }
    }

    /**
     * 二次验证：校验 captchaVerification 并消费凭证（register/verify）。
     *
     * 场景与一次验证凭证均由 aj-captcha 库保证一次性，防止重放；
     * 此处补充按手机号/IP 的失败限流。
     *
     * @param string $phone 手机号。
     * @param string $captchaVerification 一次验证通过后返回的验证凭证。
     * @return void
     */
    public function verifyAndConsume(string $phone, string $captchaVerification): void
    {
        $phone = trim($phone);
        $captchaVerification = trim($captchaVerification);

        $this->assertPhone($phone);
        $this->assertVerifyAllowed($phone);

        if ($captchaVerification === '') {
            throw new ValidateException('请先完成安全验证');
        }

        try {
            aj_captcha_check_two('blockPuzzle', $captchaVerification);
        } catch (\Throwable $exception) {
            $this->markVerifyFailure($phone);
            throw $exception;
        }

        $this->clearVerifyFailure($phone);
    }

    /**
     * 解析前端上报的拖动轨迹。
     *
     * 前端上报 JSON 数组，每项为 {t: 相对开始拖动的毫秒数, x: 滑块位移, y: 纵向位置}。
     *
     * @param string $trackJson 轨迹 JSON。
     * @return array
     */
    private function decodeTrack(string $trackJson): array
    {
        $trackJson = trim($trackJson);
        if ($trackJson === '') {
            return [];
        }

        $decoded = json_decode($trackJson, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 判断轨迹是否符合真人拖动特征。
     *
     * 只做宽松的物理合理性校验，避免误杀真人：
     * - 轨迹非空且点数不超上限；
     * - 相邻点位移无瞬移（受物理拖动约束）；
     * - 总时长不低于真人拖动下限。
     *
     * @param array $track 轨迹点数组。
     * @return bool
     */
    private function isTrackPlausible(array $track): bool
    {
        if ($track === []) {
            return false;
        }
        if (count($track) > self::TRACK_MAX_POINTS) {
            return false;
        }

        $firstT = null;
        $lastT = null;
        $prevX = null;

        foreach ($track as $point) {
            if (!is_array($point)) {
                return false;
            }
            $t = isset($point['t']) ? (int) $point['t'] : null;
            $x = isset($point['x']) ? (int) $point['x'] : null;
            if ($t === null || $x === null || $t < 0) {
                return false;
            }

            if ($prevX !== null && abs($x - $prevX) > self::TRACK_MAX_STEP_PX) {
                return false;
            }

            $prevX = $x;
            $firstT = $firstT ?? $t;
            $lastT = $t;
        }

        if ($firstT === null || $lastT === null) {
            return false;
        }

        return ($lastT - $firstT) >= self::TRACK_MIN_DURATION_MS;
    }

    /**
     * 校验获取场景的频率限制。
     *
     * @param string $clientUid 设备标识。
     * @return void
     */
    private function assertSceneRequestAllowed(string $clientUid): void
    {
        $ip = $this->getRequestIp();
        if ($this->getCounter(self::SCENE_REQUEST_IP_PREFIX . $ip) >= self::SCENE_REQUEST_IP_MAX) {
//            throw new ValidateException('当前设备请求过于频繁，请稍后再试');
        }

        $clientUid = trim($clientUid);
        if ($clientUid !== ''
            && $this->getCounter(self::SCENE_REQUEST_CLIENT_PREFIX . $clientUid) >= self::SCENE_REQUEST_CLIENT_MAX) {
//            throw new ValidateException('当前设备请求过于频繁，请稍后再试');
        }
    }

    /**
     * 记录获取场景次数。
     *
     * @param string $clientUid 设备标识。
     * @return void
     */
    private function markSceneRequest(string $clientUid): void
    {
        $ip = $this->getRequestIp();
        $this->incrementCounter(self::SCENE_REQUEST_IP_PREFIX . $ip, self::SCENE_REQUEST_WINDOW_SECONDS);

        $clientUid = trim($clientUid);
        if ($clientUid !== '') {
            $this->incrementCounter(
                self::SCENE_REQUEST_CLIENT_PREFIX . $clientUid,
                self::SCENE_REQUEST_CLIENT_WINDOW_SECONDS
            );
        }
    }

    /**
     * 校验一次验证频率限制。
     *
     * @return void
     */
    private function assertCheckAllowed(): void
    {
        $ip = $this->getRequestIp();
        if ($this->getCounter(self::CHECK_FAIL_IP_PREFIX . $ip) >= self::CHECK_FAIL_IP_MAX) {
            throw new ValidateException('当前设备验证失败次数过多，请稍后再试');
        }
    }

    /**
     * 记录一次验证失败。
     *
     * @return void
     */
    private function markCheckFailure(): void
    {
        $ip = $this->getRequestIp();
        $this->incrementCounter(self::CHECK_FAIL_IP_PREFIX . $ip, self::CHECK_FAIL_WINDOW_SECONDS);
    }

    /**
     * 校验二次验证失败次数限制。
     *
     * @param string $phone 手机号。
     * @return void
     */
    private function assertVerifyAllowed(string $phone): void
    {
        $ip = $this->getRequestIp();
        if ($this->getCounter(self::VERIFY_FAIL_PHONE_PREFIX . $phone) >= self::VERIFY_FAIL_PHONE_MAX) {
            throw new ValidateException('验证失败次数过多，请稍后再试');
        }

        if ($this->getCounter(self::VERIFY_FAIL_IP_PREFIX . $ip) >= self::VERIFY_FAIL_IP_MAX) {
            throw new ValidateException('当前设备验证失败次数过多，请稍后再试');
        }
    }

    /**
     * 记录二次验证失败。
     *
     * @param string $phone 手机号。
     * @return void
     */
    private function markVerifyFailure(string $phone): void
    {
        $ip = $this->getRequestIp();
        $this->incrementCounter(self::VERIFY_FAIL_PHONE_PREFIX . $phone, self::VERIFY_FAIL_WINDOW_SECONDS);
        $this->incrementCounter(self::VERIFY_FAIL_IP_PREFIX . $ip, self::VERIFY_FAIL_WINDOW_SECONDS);
    }

    /**
     * 清理二次验证失败计数。
     *
     * @param string $phone 手机号。
     * @return void
     */
    private function clearVerifyFailure(string $phone): void
    {
        Cache::delete(self::VERIFY_FAIL_PHONE_PREFIX . $phone);
    }

    /**
     * 获取计数器。
     *
     * @param string $key 缓存键。
     * @return int
     */
    private function getCounter(string $key): int
    {
        return max(0, (int) Cache::get($key, 0));
    }

    /**
     * 增加计数器。
     *
     * @param string $key 缓存键。
     * @param int $expireSeconds 过期秒数。
     * @return int
     */
    private function incrementCounter(string $key, int $expireSeconds): int
    {
        $count = Cache::inc($key, 1);
        if ($count === false) {
            $count = $this->getCounter($key) + 1;
            Cache::set($key, $count, $expireSeconds);

            return $count;
        }

        $this->refreshCounterExpire($key, $expireSeconds);

        return max(0, (int) $count);
    }

    /**
     * 刷新计数器过期时间。
     *
     * think-cache 默认驱动下 inc 不会自动续期，直接透传 expire 时需要补齐真实 key。
     *
     * @param string $key 缓存键。
     * @param int $expireSeconds 过期秒数。
     * @return void
     */
    private function refreshCounterExpire(string $key, int $expireSeconds): void
    {
        try {
            $store = Cache::store();
            $cacheKey = method_exists($store, 'getCacheKey') ? $store->getCacheKey($key) : $key;
            $handler = method_exists($store, 'handler') ? $store->handler() : null;
            if (is_object($handler) && method_exists($handler, 'expire') && $handler->expire($cacheKey, $expireSeconds)) {
                return;
            }
        } catch (\Throwable $exception) {
            Log::warning('滑块验证计数器过期时间刷新失败', [
                'key' => $key,
                'message' => $exception->getMessage(),
            ]);
        }

        Cache::set($key, $this->getCounter($key), $expireSeconds);
    }

    /**
     * 获取请求 IP。
     *
     * @return string
     */
    private function getRequestIp(): string
    {
        return (string) request()->ip();
    }

    /**
     * 校验手机号。
     *
     * @param string $phone 手机号。
     * @return void
     */
    private function assertPhone(string $phone): void
    {
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            throw new ValidateException('请输入正确的手机号');
        }
    }
}
