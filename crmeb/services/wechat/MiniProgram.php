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


use crmeb\services\wechat\client\miniprogram\MediaCheckClient;
use EasyWeChat\Kernel\Exceptions\BadRequestException;
use EasyWeChat\Kernel\Exceptions\RuntimeException;
use EasyWeChat\MiniApp\Application;
use GuzzleHttp\Exception\GuzzleException;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;
use crmeb\services\wechat\config\MiniProgramConfig;
use crmeb\services\wechat\client\miniprogram\AuthClient;
use crmeb\services\wechat\client\miniprogram\LiveClient;
use EasyWeChat\Kernel\Exceptions\InvalidConfigException;
use crmeb\services\wechat\client\miniprogram\OrderClient;
use crmeb\services\wechat\client\official\MaterialClient;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use crmeb\services\wechat\client\miniprogram\SubscribeClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use think\exception\ValidateException;
use think\facade\Log;


/**
 * 小程序服务
 * Class MiniProgram
 * @package crmeb\services\wechat
 * @method AuthClient auth() 授权
 * @method LiveClient live() 直播
 * @method MaterialClient material() 素材
 * @method OrderClient order() 订单
 * @method SubscribeClient subscribe() 订阅消息
 * @method MediaCheckClient media_check() 敏感词
 */
class MiniProgram extends BaseApplication
{

    /**
     * @var string
     */
    protected string $name = 'miniprogram';

    /**
     * @var MiniProgramConfig
     */
    protected MiniProgramConfig $config;

    /**
     * @var Application
     */
    protected $application;

    /**
     * MiniProgram constructor.
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
		$application = new Application($this->config->all());
		$this->setHttpClient($application);
		$this->setRequest($application);
		$this->setLogger($application);
		$this->setCache($application);
        return $application;
    }

    /**
     * @return \think\Response
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws RuntimeException
     * @throws \ReflectionException
     * @throws \Throwable
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/10/7
     */
    public static function serve(): \think\Response
    {
        $make = self::instance();
        $server = $make->application()->getServer();
        $server->with($make->pushMessageHandler);
        $response = $server->serve();
        return response($response->getBody());
    }

    /**
     * @return MiniProgram
     */
    public static function instance(): static
    {
        $instance = app()->make(self::class);
        $instance->config = app()->make(MiniProgramConfig::class);
        return $instance;
    }

    /**
     * 获得用户信息 根据code 获取session_key
     * @param string $code
     * @return array
     */
    public static function getUserInfo(string $code): array
    {
        try {
            $response = self::instance()->auth()->session($code);
            self::logger('获得用户信息 根据code 获取session_key', compact('code'), $response);
            return (new WechatResponse($response))->toArray();
        } catch (\Throwable $e) {
            throw new WechatException($e->getMessage());
        }
    }

    /**
     * 解密数据
     * @param string $sessionKey
     * @param string $iv
     * @param string $encryptData
     * @return array
     */
    public static function decryptData(string $sessionKey, string $iv, string $encryptData)
    {
        $response = self::instance()->auth()->decryptData($sessionKey, $iv, $encryptData);

        self::logger('解密数据', compact('sessionKey', 'iv', 'encryptData'), $response);

        return $response;
    }

    /**
     * 获取小程序码:适用于需要的码数量极多，或仅临时使用的业务场景
     * @param string $scene
     * @param string $path
     * @param int $width
     * @return Response|string
     * @throws TransportExceptionInterface
     */
    public static function appCodeUnlimit(string $scene, string $path = '', int $width = 0)
    {
        $optional = [
            'page' => $path,
            'width' => $width
        ];
        if (!$optional['page']) {
            unset($optional['page']);
        }
        if (!$optional['width']) {
            unset($optional['width']);
        }
        try {
            $response = self::instance()->auth()->getUnlimit($scene, $optional)->getContent();
            $res = json_decode($response);
            if (isset($res->errcode) && $res->errcode == 40001) {
                self::instance()->application()->getAccessToken()->refresh();
                $response = self::instance()->auth()->getUnlimit($scene, $optional)->getContent();
            }
        } catch (\Throwable $e) {
            Log::error('获取小程序码错误:' . $e->getMessage());
            $response = [];
        }
        return $response;
    }

    /**
     * 获取短链
     * @param string $page
     * @param string $query
     * @return Response|string|ResponseInterface
     * User: liusl
     * DateTime: 2024/11/23 下午2:54
     */
    public static function generateUrlLink(string $page, string $query)
    {
        try {
            return self::instance()->auth()->generateUrlLink($page, $query);
        } catch (\Throwable $e) {
            Log::error('获取小程序码错误:' . $e->getMessage());
            return '';
        }
    }

    /**
     * 发送订阅消息
     * @param string $touser
     * @param string $templateId
     * @param array $data
     * @param string $link
     * @return Response|ResponseInterface
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws GuzzleException
     */
    public static function sendSubscribeTemlate(string $touser, string $templateId, array $data, string $link = '')
    {
        $response = self::instance()->subscribe()->send([
            'template_id' => $templateId,
            'touser' => $touser,
            'page' => $link,
            'data' => $data
        ]);

        self::logger('发送订阅消息', compact('templateId', 'touser', 'link', 'data'), $response);

        return $response;
    }

    /**
     * 添加订阅消息模版
     * @param string $tid
     * @param array $kidList
     * @param string $sceneDesc
     * @return mixed
     */
    public static function addSubscribeTemplate(string $tid, array $kidList, string $sceneDesc = '')
    {
        try {
            $res = self::instance()->subscribe()->addTemplate($tid, $kidList, $sceneDesc);

            self::logger('添加订阅消息模版', compact('tid', 'kidList', 'sceneDesc'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['priTmplId'])) {
                return $res['priTmplId'];
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException($e);
        }
    }

    /**
     * 删除订阅消息
     * @param string $templateId
     * @return Response|ResponseInterface
     */
    public static function delSubscribeTemplate(string $templateId)
    {
        try {
            $response = self::instance()->subscribe()->deleteTemplate($templateId);

            self::logger('删除订阅消息', compact('templateId'), $response);

            return $response;
        } catch (\Throwable $e) {
            throw new WechatException($e->getMessage());
        }
    }

    /**
     * 获取模版标题的关键词列表
     * @param string $tid
     * @return mixed
     */
    public static function getSubscribeTemplateKeyWords(string $tid)
    {
        try {
            $res = self::instance()->subscribe()->getTemplateKeywords($tid);

            self::logger('获取模版标题的关键词列表', compact('tid'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['data'])) {
                return $res['data'];
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException($e);
        }
    }

    /**
     * 获取直播列表
     * @param int $page
     * @param int $limit
     * @return array
     */
    public static function getLiveInfo(int $page = 1, int $limit = 10)
    {
        try {
            $res = self::instance()->live()->getRooms($page, $limit);

//            self::logger('获取直播列表', compact('page', 'limit'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['room_info']) && $res['room_info']) {
                return $res['room_info'];
            } else {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 获取直播回放
     * @param int $room_id
     * @param int $page
     * @param int $limit
     * @return mixed
     */
    public static function getLivePlayback(int $room_id, int $page = 1, int $limit = 10)
    {
        try {
            $res = self::instance()->live()->getPlaybacks($room_id, $page, $limit);

            self::logger('获取直播回放', compact('room_id', 'page', 'limit'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['live_replay'])) {
                return $res['live_replay'];
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 创建直播间
     * @param array $data
     * @return mixed
     */
    public static function createLiveRoom(array $data)
    {
        try {
            $res = self::instance()->live()->createRoom($data);

            self::logger('创建直播间', compact('data'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['roomId'])) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 直播间添加商品
     * @param int $roomId
     * @param $ids
     * @return bool
     */
    public static function roomAddGoods(int $roomId, $ids)
    {
        try {
            $res = self::instance()->live()->roomAddGoods($roomId, $ids);

            self::logger('直播间添加商品', compact('roomId', 'ids'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 获取商品列表
     * @param int $status
     * @param int $page
     * @param int $limit
     * @return mixed
     */
    public static function getGoodsList(int $status = 2, int $page = 1, int $limit = 10)
    {
        try {
            $res = self::instance()->live()->getGoodsList($status, $page, $limit);

            self::logger('获取商品列表', compact('status', 'page', 'limit'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['goods'])) {
                return $res['goods'];
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 获取商品详情
     * @param $goods_ids
     * @return mixed
     */
    public static function getGooodsInfo($goods_ids)
    {
        try {
            $res = self::instance()->live()->getGooodsInfo($goods_ids);

            self::logger('获取商品详情', compact('goods_ids'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['goods'])) {
                return $res['goods'];
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 添加商品
     * @param string $coverImgUrl
     * @param string $name
     * @param int $priceType
     * @param string $url
     * @param $price
     * @param string $price2
     * @return mixed
     */
    public static function addGoods(string $coverImgUrl, string $name, int $priceType, string $url, $price, $price2 = '')
    {
        try {
            $res = self::instance()->live()->addGoods($coverImgUrl, $name, $priceType, $url, $price, $price2);

            self::logger('添加商品', compact('coverImgUrl', 'name', 'priceType', 'url', 'price', 'price2'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['goodsId'])) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 商品撤回审核
     * @param int $goodsId
     * @param $auditId
     * @return bool
     */
    public static function resetauditGoods(int $goodsId, $auditId)
    {
        try {
            $res = self::instance()->live()->resetauditGoods($goodsId, $auditId);

            self::logger('商品撤回审核', compact('goodsId', 'auditId'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 商品重新提交审核
     * @param int $goodsId
     * @return mixed
     */
    public static function auditGoods(int $goodsId)
    {
        try {
            $res = self::instance()->live()->auditGoods($goodsId);

            self::logger('商品重新提交审核', compact('goodsId'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['auditId'])) {
                return $res['auditId'];
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 删除商品
     * @param int $goodsId
     * @return bool
     */
    public static function deleteGoods(int $goodsId)
    {
        try {
            $res = self::instance()->live()->deleteGoods($goodsId);

            self::logger('删除商品', compact('goodsId'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 更新商品
     * @param int $goodsId
     * @param string $coverImgUrl
     * @param string $name
     * @param int $priceType
     * @param string $url
     * @param $price
     * @param string $price2
     * @return bool
     */
    public static function updateGoods(int $goodsId, string $coverImgUrl, string $name, int $priceType, string $url, $price, $price2 = '')
    {
        try {
            $res = self::instance()->live()->updateGoods($goodsId, $coverImgUrl, $name, $priceType, $url, $price, $price2);

            self::logger('更新商品', compact('goodsId', 'coverImgUrl', 'name', 'priceType', 'url', 'price', 'price2'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 获取成员列表
     * @param int $role
     * @param int $page
     * @param int $limit
     * @param string $keyword
     * @return mixed
     */
    public static function getRoleList(int $role = 2, int $page = 0, int $limit = 30, string $keyword = '')
    {
        try {
            $res = self::instance()->live()->getRoleList($role, $page, $limit, $keyword);

            self::logger('获取成员列表', compact('role', 'page', 'limit', 'keyword'), $res);

            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['list'])) {
                return $res['list'];
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 获取推流地址
     * @param int $roomId
     * @return mixed
     */
    public static function getPushUrl(int $roomId)
    {
        try {
            $res = self::instance()->live()->getPushUrl($roomId);
            self::logger('获取推流地址', compact('roomId'), $res);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return $res;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 获取长期订阅用户
     * @param string $pageBreak
     * @param int $limit
     * @return mixed
     */
    public static function getFollowers(string $pageBreak = '', int $limit = 2000)
    {
        try {
            $res = self::instance()->live()->getFollowers($pageBreak, $limit);
            self::logger('获取长期订阅用户', compact('pageBreak', 'limit'), $res);
            return $res;
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 群发消息
     * @param int $roomId
     * @param array $userOpenids
     * @return bool
     */
    public static function pushMessage(int $roomId, array $userOpenids)
    {
        try {
            $res = self::instance()->live()->pushMessage($roomId, $userOpenids);
            self::logger('群发消息', compact('roomId', 'userOpenids'), $res);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 商品上下架
     * @param int $roomId
     * @param int $goodsId
     * @param int $onSale
     * @return bool
     */
    public static function goodsOnsale(int $roomId, int $goodsId, int $onSale)
    {
        try {
            $res = self::instance()->live()->goodsOnsale($roomId, $goodsId, $onSale);
            self::logger('商品上下架', compact('roomId', 'goodsId', 'onSale'), $res);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 开关客服
     * @param int $roomId
     * @param int $closeKf
     * @return bool
     */
    public static function closeKf(int $roomId, int $closeKf)
    {
        try {
            $res = self::instance()->live()->closeKf($roomId, $closeKf);
            self::logger('开关客服', compact('roomId', 'closeKf'), $res);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 开关评论
     * @param int $roomId
     * @param int $banComment
     * @return bool
     */
    public static function banComment(int $roomId, int $banComment)
    {
        try {
            $res = self::instance()->live()->banComment($roomId, $banComment);
            self::logger('开关评论', compact('roomId', 'banComment'), $res);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 更新官方收录
     * @param int $roomId
     * @param int $isFeedsPublic
     * @return bool
     */
    public static function updateFeedPublic(int $roomId, int $isFeedsPublic)
    {
        try {
            $res = self::instance()->live()->updateFeedPublic($roomId, $isFeedsPublic);
            self::logger('更新官方收录', compact('roomId', 'isFeedsPublic'), $res);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 添加直播间助手
     * @param int $roomId
     * @param array $users
     * @return bool
     */
    public static function addAssistant(int $roomId, array $users)
    {
        try {
            $res = self::instance()->live()->addAssistant($roomId, $users);
            self::logger('添加直播间助手', compact('roomId', 'users'), $res);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 删除直播间助手
     * @param int $roomId
     * @param string $username
     * @return bool
     */
    public static function removeAssistant(int $roomId, string $username)
    {
        try {
            $res = self::instance()->live()->removeAssistant($roomId, $username);
            self::logger('删除直播间助手', compact('roomId', 'username'), $res);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return true;
            } else {
                throw new WechatException($res['errmsg']);
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 获取商品信息与审核状态（仓库）
     * @param array $goodsIds
     * @return mixed
     */
    public static function getGoodsWarehouse(array $goodsIds)
    {
        try {
            $res = self::instance()->live()->getGoodsWarehouse($goodsIds);
            self::logger('获取商品仓库信息', compact('goodsIds'), $res);
            if (isset($res['errcode']) && $res['errcode'] == 0 && isset($res['goods'])) {
                return $res['goods'];
            } else {
                throw new WechatException($res['errmsg'] ?? '获取失败');
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 获取审核通过的商品列表
     * @param array $params
     * @return mixed
     */
    public static function getApproved(array $params)
    {
        try {
            $res = self::instance()->live()->getApproved($params);
            self::logger('获取审核通过商品列表', compact('params'), $res);
            if (isset($res['errcode']) && $res['errcode'] == 0) {
                return $res;
            } else {
                throw new WechatException($res['errmsg'] ?? '获取失败');
            }
        } catch (\Throwable $e) {
            throw new WechatException(ErrorMessage::getValidMessgae($e));
        }
    }

    /**
     * 小程序临时素材上传
     * @param string $path
     * @param string $type
     * @return WechatResponse
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public static function temporaryUpload(string $path, string $type = 'image')
    {
        $response = self::instance()->material()->upload($type, $path);

        self::logger('小程序-临时素材上传', compact('path', 'type'), $response);


        return new WechatResponse($response);
    }


    /**
     * 上传订单
     * @param string $out_trade_no 订单号(商城订单好)
     * @param int $logistics_type 物流模式，发货方式枚举值：1、实体物流配送采用快递公司进行实体物流配送形式 2、同城配送 3、虚拟商品，虚拟商品，例如话费充值，点卡等，无实体配送形式 4、用户自提
     * @param array $shipping_list 物流信息列表，发货物流单列表，支持统一发货（单个物流单）和分拆发货（多个物流单）两种模式，多重性: [1, 10]
     * @param string $payer_openid 支付者，支付者信息
     * @param string $path
     * @param int $delivery_mode 发货模式，发货模式枚举值：1、UNIFIED_DELIVERY（统一发货）2、SPLIT_DELIVERY（分拆发货） 示例值: UNIFIED_DELIVERY
     * @param bool $is_all_delivered 分拆发货模式时必填，用于标识分拆发货模式下是否已全部发货完成，只有全部发货完成的情况下才会向用户推送发货完成通知。示例值: true/false
     * @param string $sub_mchid 分账场景必填，分账商户号，分账场景下的二级商户号。示例值: 1900000109
     * @return array
     * @throws TransportExceptionInterface
     */
    public static function shippingByTradeNo(string $out_trade_no, int $logistics_type, array $shipping_list, string $payer_openid, string $path, int $delivery_mode = 1, bool $is_all_delivered = true, string $sub_mchid = '')
    {
        return self::instance()->order()->shippingByTradeNo($out_trade_no, $logistics_type, $shipping_list, $payer_openid, $path, $delivery_mode, $is_all_delivered, $sub_mchid);
    }

    /**
     * 合单
     * @param string $out_trade_no
     * @param int $logistics_type
     * @param array $sub_orders
     * @param string $payer_openid
     * @param int $delivery_mode
     * @param bool $is_all_delivered
     * @return array
     * @throws TransportExceptionInterface
     */
    public static function combinedShippingByTradeNo(string $out_trade_no, int $logistics_type, array $sub_orders, string $payer_openid, int $delivery_mode = 2, bool $is_all_delivered = false)
    {
        return self::instance()->order()->combinedShippingByTradeNo($out_trade_no, $logistics_type, $sub_orders, $payer_openid, $delivery_mode, $is_all_delivered);
    }

    /**
     * 签收通知
     * @param string $merchant_trade_no
     * @param string $received_time
     * @return array
     * @throws TransportExceptionInterface
     */
    public static function notifyConfirmByTradeNo(string $merchant_trade_no, string $received_time)
    {
        return self::instance()->order()->notifyConfirmByTradeNo($merchant_trade_no, $received_time);
    }

    /**
     * 判断是否开通
     * @return bool
     * @throws HttpException
     * @date 2023/05/17
     * @author yyw
     */
    public static function isManaged()
    {
        return self::instance()->order()->checkManaged();
    }


    /**
     * 设置小修跳转路径
     * @param $path
     * @return array
     * @throws HttpException|TransportExceptionInterface
     */
    public static function setMesJumpPathAndCheck($path)
    {
        return self::instance()->order()->setMesJumpPathAndCheck($path);
    }

    /**
     * 敏感词验证
     * @param string $content
     * @param int $scene
     * @param string $openId
     * @return true
     * User: liusl
     * DateTime: 2024/11/26 下午4:29
     */
    public static function msgSecCheck(string $content, int $scene, string $openId, $mediaType)
    {
        try {
            //$media_type 1:音频;2:图片
            //scene 场景枚举值（1 资料；2 评论；3 论坛；4 社交日志）
            if (!in_array($scene, [1, 2, 3, 4])) {
                throw new ValidateException('使用场景类型错误');
            }
            if (!$openId) {
                return true;
            }
            $response = $mediaType
                ? self::instance()->media_check()->mediaSecCheck($content, $scene, $openId, $mediaType)
                : self::instance()->media_check()->msgSecCheck($content, $scene, $openId);
            $res = is_object($response) && method_exists($response, 'toArray') ? $response->toArray(false) : (is_array($response) ? $response : []);
            if (isset($res['errcode']) && $res['errcode'] == 40001) {
                self::instance()->application()->getAccessToken()->refresh();
                $response = $mediaType
                    ? self::instance()->media_check()->mediaSecCheck($content, $scene, $openId, $mediaType)
                    : self::instance()->media_check()->msgSecCheck($content, $scene, $openId);
                $res = is_object($response) && method_exists($response, 'toArray') ? $response->toArray(false) : (is_array($response) ? $response : []);
            }
            return new WechatResponse($res);
        } catch (\Throwable $e) {
            Log::error('敏感词检查:' . $e->getMessage());
            if ($e instanceof ValidateException) {
                throw $e;
            }
            throw new WechatException($e->getMessage());
        }
    }

}
