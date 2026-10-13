<?php
/**
 *  +----------------------------------------------------------------------
 *  | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
 *  +----------------------------------------------------------------------
 *  | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
 *  +----------------------------------------------------------------------
 *  | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
 *  +----------------------------------------------------------------------
 *  | Author: CRMEB Team <admin@crmeb.com>
 *  +----------------------------------------------------------------------
 */

namespace crmeb\services\wechat\client\miniprogram;


use crmeb\services\wechat\client\BaseClient;
use crmeb\services\wechat\config\MiniProgramConfig;
use crmeb\services\wechat\util\AES;
use crmeb\services\wechat\WechatException;
use EasyWeChat\Kernel\HttpClient\AccessTokenAwareClient;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use think\annotation\Inject;

/**
 * 授权
 * Class AuthClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/15
 * @package crmeb\services\wechat\client\miniprogram
 */
class AuthClient extends BaseClient
{

    /**
     * @var MiniProgramConfig
     */
    protected MiniProgramConfig $config;

    /**
     * AuthClient constructor.
     * @param AccessTokenAwareClient $api
     */
    public function __construct(AccessTokenAwareClient $api)
    {
        parent::__construct($api);
        $this->config = app()->make(MiniProgramConfig::class);
    }

    /**
     * 使用code换取用户信息
     * @param string $code
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function session(string $code): ResponseInterface|Response
    {
        $params = [
            'appid' => $this->config->appId,
            'secret' => $this->config->secret,
            'js_code' => $code,
            'grant_type' => 'authorization_code',
        ];
        return $this->api->get('sns/jscode2session', $params);
    }

    /**
     * 解密数据
     * @param string $sessionKey
     * @param string $iv
     * @param string $encrypted
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function decryptData(string $sessionKey, string $iv, string $encrypted): array
    {
        $decrypted = AES::decrypt(
            base64_decode($encrypted, false),
            base64_decode($sessionKey, false),
            base64_decode($iv, false)
        );

        $decrypted = json_decode($decrypted, true);

        if (!$decrypted) {
            throw new WechatException('The given payload is invalid.');
        }

        return $decrypted;
    }

    /**
     * 获取小程序码:适用于需要的码数量极多，或仅临时使用的业务场景
     * @param string $scene
     * @param array $optional
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getUnlimit(string $scene, array $optional = []): Response
    {
        $params = array_merge([
            'scene' => $scene,
        ], $optional);

        return $this->getStream('wxa/getwxacodeunlimit', $params);
    }

    /**
     * 获取流文件
     * @param string $endpoint
     * @param array $params
     * @return Response
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function getStream(string $endpoint, array $params): Response
    {
        return $this->api->request('POST', $endpoint, ['json' => $params]);
    }

    /**
     * 获取小程序短链
     * @param string $path 通过 URL Link 进入的小程序页面路径，必须是已经发布的小程序存在的页面，不可携带 query 。path 为空时会跳转小程序主页
     * @param string $query 通过 URL Link 进入小程序时的query，最大1024个字符，只支持数字，大小写英文以及部分特殊字符：!#$&'()*+,/:;=?@-._~%
     * @param int $expire_type 默认值0.小程序 URL Link 失效类型，失效时间：0，失效间隔天数：1
     * @param int $expire_time 到期失效的 URL Link 的失效时间，为 Unix 时间戳。生成的到期失效 URL Link 在该时间前有效。最长有效期为30天。expire_type 为 0 必填
     * @param int $expire_interval 到期失效的URL Link的失效间隔天数。生成的到期失效URL Link在该间隔时间到达前有效。最长间隔天数为30天。expire_type 为 1 必填
     * @throws TransportExceptionInterface User: liusl
     * DateTime: 2024/11/23 下午2:46
     */
    public function generateUrlLink(string $path, string $query, int $expire_type = 0, int $expire_time = 30 * 24 * 3600, int $expire_interval = 29)
    {
        $params = [
            'path' => $path,
            'query' => $query,
            'expire_type' => $expire_type,
        ];
        if ($expire_type == 0) {
            $params['expire_time'] = time() + $expire_time;
        } else {
            $params['expire_interval'] = $expire_interval;
        }
        return $this->api->postJson('wxa/generate_urllink', $params);

    }
}
