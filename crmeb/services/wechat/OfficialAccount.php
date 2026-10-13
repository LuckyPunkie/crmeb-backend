<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services\wechat;

use crmeb\services\wechat\client\official\CardClient;
use crmeb\services\wechat\client\official\MaterialClient;
use crmeb\services\wechat\client\official\MediaClient;
use crmeb\services\wechat\client\official\QrCodeClient;
use crmeb\services\wechat\client\official\StaffClient;
use crmeb\services\wechat\client\official\TemplateClient;
use crmeb\services\wechat\config\OfficialAccountConfig;
use crmeb\services\wechat\config\OpenAppConfig;
use crmeb\services\wechat\config\OpenWebConfig;
use crmeb\services\wechat\client\official\UserClient;
use crmeb\services\wechat\message\MessageInterface;
use EasyWeChat\Kernel\Exceptions\BadRequestException;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use EasyWeChat\Kernel\Exceptions\RuntimeException;
use EasyWeChat\OfficialAccount\Application;
use ReflectionException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use EasyWeChat\Kernel\HttpClient\Response;
use Throwable;

/**
 * 公众号服务
 * Class OfficialAccount
 * @package crmeb\services\wechat
 * @method UserClient user() 用户
 * @method CardClient card() 卡卷
 * @method TemplateClient template() 模板消息
 * @method MaterialClient material() 永久素材管理
 * @method MediaClient media() 临时素材管理
 * @method StaffClient staff() 消息
 * @method QrCodeClient qrCode() 二维码
 */
class OfficialAccount extends BaseApplication
{

    /**
     * @var string
     */
    protected string $name = 'official';

    /**
     * @var array
     */
    protected array $application;

    /**
     * OfficialAccount constructor.
     */
    public function __construct()
    {
        $this->debug = DefaultConfig::value('logger');
    }

    /**
     * 初始化
     * @return Application
     * @throws InvalidArgumentException
     */
    public function application(): Application
    {
        $request = request();

        $config = match ($accessEnd = $this->getAuthAccessEnd($request)) {
            //self::APP => app()->make(OpenAppConfig::class)->all(),
            //self::PC => app()->make(OpenWebConfig::class)->all(),
            default => app()->make(OfficialAccountConfig::class)->all(),
        };

		$application = new Application($config);
		$this->setHttpClient($application);
		$this->setRequest($application);
		$this->setLogger($application);
		$this->setCache($application);

        return $application;
    }

    /**
     * 服务端
     * @return \think\Response
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws Throwable
     */
    public static function serve($handler = null): \think\Response
    {
        $make = self::instance();
        $server = $make->application()->getServer();
        $pushMessageHandler = $handler ?: (isset($make->pushMessageHandler) ? $make->pushMessageHandler : null);
        if ($pushMessageHandler) {
            $server->with($pushMessageHandler);
        }
        $response = $server->serve();
        return response($response->getBody());
    }

    /**
     * @return OfficialAccount
     */
    public static function instance(): self
    {
        return app()->make(self::class);
    }

    /**
     * 获取js的SDK
     * @param string $url
     * @return array
     */
    public static function jsSdk(string $url = ''): array
    {
        $apiList = ['openAddress', 'updateTimelineShareData', 'updateAppMessageShareData',
            'onMenuShareTimeline', 'onMenuShareAppMessage', 'onMenuShareQQ',
            'onMenuShareWeibo', 'onMenuShareQZone', 'startRecord', 'stopRecord',
            'onVoiceRecordEnd', 'playVoice', 'pauseVoice', 'stopVoice',
            'onVoicePlayEnd', 'uploadVoice', 'downloadVoice', 'chooseImage',
            'previewImage', 'uploadImage', 'downloadImage', 'translateVoice',
            'getNetworkType', 'openLocation', 'getLocation', 'hideOptionMenu',
            'showOptionMenu', 'hideMenuItems', 'showMenuItems', 'hideAllNonBaseMenuItem',
            'showAllNonBaseMenuItem', 'closeWindow', 'scanQRCode', 'chooseWXPay',
            'openProductSpecificView', 'addCard', 'chooseCard', 'openCard','requestMerchantTransfer'];

        try {
            return self::retryWithTokenRefresh(function() use ($url, $apiList) {
                return self::instance()->application()->getUtils()->buildJsSdkConfig(
                    url: $url,
                    jsApiList: $apiList,
                    openTagList: [],
                    debug: false
                );
            });
        } catch (\Throwable $e) {
            self::error($e);
            return [];
        }
    }

    /**
     * 获取微信用户信息
     * @param $openid
     * @return array|Response|mixed|ResponseInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public static function getUserInfo($openid)
    {
        try {
            $userInfo = self::retryWithTokenRefresh(function() use ($openid) {
                $userService = self::instance()->user();

                if (is_array($openid)) {
                    $res = $userService->select($openid);
                    if (isset($res['user_info_list'])) {
                        return $res['user_info_list'];
                    } else {
                        throw new WechatException($res['errmsg'] ?? '获取微信粉丝信息失败');
                    }
                } else {
                    $userInfo = $userService->get($openid);
                    return is_object($userInfo) ? $userInfo->toArray() : $userInfo;
                }
            });
        } catch (Throwable $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }

        self::logger('获取微信用户信息', compact('openid'), $userInfo);

        return $userInfo;
    }

    /**
     * 获取会员卡列表
     * @param int $offset
     * @param int $count
     * @param string $statusList
     * @return mixed
     * @throws TransportExceptionInterface
     */
    public static function getCardList(int $offset = 0, int $count = 10, string $statusList = 'CARD_STATUS_VERIFY_OK')
    {
        try {
            $res = self::instance()->card()->list($offset, $count, $statusList);

            self::logger('获取会员卡列表', compact('offset', 'count', 'statusList'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['card_id_list'])) {
                return $res['card_id_list'];
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 获取卡券颜色
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function getCardColors()
    {
        try {

            $response = self::instance()->card()->colors();

            self::logger('获取卡券颜色', [], $response);

            return $response;
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 创建卡券
     * @param string $cardType
     * @param array $baseInfo
     * @param array $especial
     * @param array $advancedInfo
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function createCard(string $cardType, array $baseInfo, array $especial = [], array $advancedInfo = []): ResponseInterface|Response
    {
        try {
            $res = self::instance()->card()->create($cardType, array_merge(['base_info' => $baseInfo, 'advanced_info' => $advancedInfo], $especial));

            self::logger('创建卡券', compact('cardType', 'baseInfo', 'especial', 'advancedInfo'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['card_id'])) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * @param int $cardId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public static function getCard(int $cardId): ResponseInterface|Response
    {
        try {
            $res = self::retryWithTokenRefresh(function() use ($cardId) {
                return self::instance()->card()->get($cardId);
            });

            self::logger('获取卡券信息', compact('cardId'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 修改卡券
     * @param string $cardId
     * @param string $type
     * @param array $baseInfo
     * @param array $especial
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function updateCard(string $cardId, string $type, array $baseInfo = [], array $especial = []): ResponseInterface|Response
    {
        try {
            $res = self::retryWithTokenRefresh(function() use ($cardId, $type, $baseInfo, $especial) {
                return self::instance()->card()->update($cardId, $type, array_merge(['base_info' => $baseInfo], $especial));
            });

            self::logger('修改卡券', compact('cardId', 'type', 'baseInfo', 'especial'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 获取领卡券二维码
     * @param string $card_id 卡券ID
     * @param string $outer_id 生成二维码标识参数
     * @param string $code 自动移code
     * @param int $expire_time
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function getCardQRCode(string $card_id, string $outer_id, string $code = '', int $expire_time = 1800): ResponseInterface|Response
    {
        $data = [
            'action_name' => 'QR_CARD',
            'expire_seconds' => $expire_time,
            'action_info' => [
                'card' => [
                    'card_id' => $card_id,
                    'is_unique_code' => false,
                    'outer_id' => $outer_id
                ]
            ]
        ];
        if ($code) $data['action_info']['card']['code'] = $code;
        try {
            $res = self::instance()->card()->createQrCode($data);

            self::logger('获取领卡券二维码', compact('data'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['url'])) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 创建临时二维码
     * @param string|int $sceneValue
     * @param int|null $expireSeconds
     * @return array
     */
    public static function temporaryQrCode(string|int $sceneValue, ?int $expireSeconds = null): array
    {
        try {
            $response = self::retryWithTokenRefresh(function() use ($sceneValue, $expireSeconds) {
                return self::instance()->qrCode()->temporary($sceneValue, $expireSeconds);
            });

            self::logger('创建临时二维码', compact('sceneValue', 'expireSeconds'), $response);

            return $response;
        } catch (Throwable $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 创建永久二维码
     * @param string|int $sceneValue
     * @return array
     */
    public static function foreverQrCode(string|int $sceneValue): array
    {
        try {
            $response = self::retryWithTokenRefresh(function() use ($sceneValue) {
                return self::instance()->qrCode()->forever($sceneValue);
            });

            self::logger('创建永久二维码', compact('sceneValue'), $response);

            return $response;
        } catch (Throwable $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 获取二维码展示地址
     * @param string $ticket
     * @return string
     */
    public static function qrCodeUrl(string $ticket): string
    {
        return self::instance()->qrCode()->url($ticket);
    }

    /**
     * 设置会员卡激活字段
     * @param string $cardId
     * @param array $requiredForm
     * @param array $optionalForm
     * @return mixed
     * @throws TransportExceptionInterface
     */
    public static function cardActivateUserForm(string $cardId, array $requiredForm = [], array $optionalForm = []): mixed
    {
        try {
            $res = self::instance()->card()->setActivationForm($cardId, array_merge($requiredForm, $optionalForm));

            self::logger('设置会员卡激活字段', compact('cardId', 'requiredForm', 'optionalForm'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 会员卡激活
     * @param string $card_id
     * @param string $code
     * @param string $membership_number
     * @return mixed
     * @throws TransportExceptionInterface
     */
    public static function cardActivate(string $card_id, string $code, string $membership_number = ''): mixed
    {
        $info = [
            'membership_number' => $membership_number ? $membership_number : $code, //会员卡编号，由开发者填入，作为序列号显示在用户的卡包里。可与Code码保持等值。
            'code' => $code, //创建会员卡时获取的初始code。
            'activate_begin_time' => '', //激活后的有效起始时间。若不填写默认以创建时的 data_info 为准。Unix时间戳格式
            'activate_end_time' => '', //激活后的有效截至时间。若不填写默认以创建时的 data_info 为准。Unix时间戳格式。
            'init_bonus' => '0', //初始积分，不填为0。
            'init_balance' => '0', //初始余额，不填为0。
        ];
        try {
            $res = self::instance()->card()->activate($info);

            self::logger('会员卡激活', compact('info'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['url'])) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 获取会员信息
     * @param string $cardId
     * @param string $code
     * @return mixed
     * @throws TransportExceptionInterface
     */
    public static function getMemberCardUser(string $cardId, string $code): mixed
    {
        try {
            $res = self::instance()->card()->getUser($cardId, $code);

            self::logger('获取会员信息', compact('cardId', 'code'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['user_info'])) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 更新会员信息
     * @param array $data
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function updateMemberCardUser(array $data): ResponseInterface|Response
    {
        try {
            $res = self::instance()->card()->updateUser($data);

            self::logger('更新会员信息', compact('data'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['user_info'])) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 设置模版消息行业
     * @param int $industryOne
     * @param int $industryTwo
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public static function setIndustry(int $industryOne, int $industryTwo): ResponseInterface|Response
    {
        $response = self::instance()->template()->setIndustry($industryOne, $industryTwo);

        self::logger('设置模版消息行业', compact('industryOne', 'industryTwo'), $response);

        return $response;
    }

    /**
     * 获得添加模版ID
     * @param $templateIdShort
     * @param $keywordList
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function addTemplateId($templateIdShort, $keywordList): ResponseInterface|Response
    {
        try {
            $response = self::instance()->template()->addTemplate($templateIdShort, $keywordList);

            self::logger('获得添加模版ID', compact('templateIdShort'), $response);

            return $response;
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 获取模板列表
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function getPrivateTemplates(): ResponseInterface|Response
    {
        try {
            $response = self::instance()->template()->getPrivateTemplates();

            self::logger('获取模板列表', [], $response);

            return $response;
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 根据模版ID删除模版
     * @param string $templateId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function deleleTemplate(string $templateId): ResponseInterface|Response
    {
        try {
            return self::instance()->template()->deletePrivateTemplate($templateId);
        } catch (\Exception $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 获取行业
     * @return Response|ResponseInterface
     */
    public static function getIndustry(): ResponseInterface|Response
    {
        try {
            $response = self::instance()->template()->getIndustry();

            self::logger('获取行业', [], $response);

            return $response;
        } catch (Throwable $e) {
            throw new WechatException(ErrorMessage::getMessage($e->getMessage()));
        }
    }

    /**
     * 发送模板消息
     * @param string $openid
     * @param string $templateId
     * @param array $data
     * @param string|null $url
     * @param string|null $defaultColor
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function sendTemplate(string $openid, string $templateId, array $data, string $url = null, string $defaultColor = null): ResponseInterface|Response
    {
        $response = self::instance()->template()->send([
            'touser' => $openid,
            'template_id' => $templateId,
            'data' => $data,
            'url' => $url
        ]);

        self::logger('发送模板消息', compact('openid', 'templateId', 'data', 'url'), $response);

        return $response;
    }

    /**
     * 静默授权-使用code获取用户授权信息
     * @param string|null $code
     * @return array
     */
    public static function tokenFromCode(string $code = null): array
    {
        $code = $code ?: request()->param('code');
        if (!$code) {
            throw new WechatException('无效CODE');
        }
        //try {
            $response = self::instance()->application()->getOauth()->userFromCode($code);
            self::logger('静默授权-使用code获取用户授权信息', compact('code'), $response);

            return $response->getTokenResponse();
        //} catch (Throwable $e) {
        //    throw new WechatException('授权失败' . $e->getMessage() . 'line' . $e->getLine());
        //}
    }

    /**
     * 使用code获取用户授权信息
     * @param string|null $code
     * @return array
     */
    public static function userFromCode(string $code = null): array
    {
        $code = $code ?: request()->param('code');
        if (!$code) {
            throw new WechatException('无效CODE');
        }
        try {
            $response = self::instance()->application()->getOauth()->userFromCode($code);

            self::logger('使用code获取用户授权信息', compact('code'), $response);

            return $response->getRaw();
        } catch (Throwable $e) {
            throw new WechatException('授权失败' . $e->getMessage() . 'line' . $e->getLine());
        }
    }

    /**
     * 临时素材上传
     * @param string $path
     * @return WechatResponse
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public static function uploadImage(string $path): WechatResponse
    {
        $response = self::instance()->media()->uploadImage($path);

        self::logger('素材管理-上传附件', compact('path'), $response);

        return new WechatResponse($response);
    }

    /**
     * 永久素材上传
     * @param string $path
     * @param string $type
     * @return WechatResponse
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public static function temporaryUpload(string $path, string $type = 'image'): WechatResponse
    {
        $response = self::retryWithTokenRefresh(function() use ($path, $type) {
            return self::instance()->material()->upload($type, $path);
        });

        self::logger('临时素材上传', compact('path', 'type'), $response);

        return new WechatResponse($response);
    }

    /**
     * 消息回执
     * @param MessageInterface|string $message
     * @param string $to
     * @param string $account
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public static function staffSend(MessageInterface|string $message, string $to, string $account = '')
    {
        return self::retryWithTokenRefresh(function() use ($message, $to, $account) {
            return self::instance()->staff()->send($message, $to, $account);
        });
    }

    /**
     * 通用的access_token刷新重试方法
     * @param callable $callback 需要重试的回调函数
     * @param int $maxRetries 最大重试次数
     * @return mixed
     * @throws WechatException
     */
    protected static function retryWithTokenRefresh(callable $callback, int $maxRetries = 1)
    {
        $attempts = 0;

        while ($attempts <= $maxRetries) {
            try {
				$response  = $callback();
				$response = is_object($response) && method_exists($response, 'toArray') ? $response->toArray() : $response;
				if (isset($response['errcode']) && $response['errcode'] == 40001 && $attempts < $maxRetries) {
					$attempts++;
					self::instance()->application()->getAccessToken()->refresh();
					continue; // 继续下一次循环重试
				}
				return $response;
            } catch (Throwable $e) {
                $attempts++;

                // 检查是否是access_token过期错误
                $errorMessage = $e->getMessage();
                $isTokenError = strpos($errorMessage, 'invalid credential') !== false ||
                               strpos($errorMessage, 'access_token is invalid') !== false ||
                               (method_exists($e, 'getCode') && $e->getCode() == 40001);

                // 如果是token错误且还有重试机会，则刷新token并重试
                if ($isTokenError && $attempts <= $maxRetries) {
                    try {
                        self::instance()->application()->getAccessToken()->refresh();
                        continue; // 继续下一次循环重试
                    } catch (Throwable $refreshException) {
                        // 刷新token失败，抛出原始异常
                        throw $e;
                    }
                }

                // 不是token错误或已达到最大重试次数，抛出异常
                throw $e;
            }
        }

        throw new WechatException('重试次数已达上限');
    }
}
