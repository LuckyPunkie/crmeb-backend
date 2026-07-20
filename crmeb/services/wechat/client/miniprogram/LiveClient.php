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
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * 直播
 * Class LiveClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/15
 * @package crmeb\services\wechat\client\miniprogram
 */
class LiveClient extends BaseClient
{
    /**
     * 添加直播间参数
     * @var array
     */
    protected array $createData = [
        'name' => '',  // 房间名字
        'coverImg' => '',   // 通过 uploadfile 上传，填写 mediaID
        'startTime' => 0,   // 开始时间
        'endTime' => 0, // 结束时间
        'anchorName' => '',  // 主播昵称
        'anchorWechat' => '',  // 主播微信号
        'shareImg' => '',  //通过 uploadfile 上传，填写 mediaID
        'feedsImg' => '',   //通过 uploadfile 上传，填写 mediaID
        'isFeedsPublic' => 1, // 是否开启官方收录，1 开启，0 关闭
        'type' => 1, // 直播类型，1 推流 0 手机直播
        'screenType' => 0,  // 1：横屏 0：竖屏
        'closeLike' => 0, // 是否 关闭点赞 1 关闭
        'closeGoods' => 0, // 是否 关闭商品货架，1：关闭
        'closeComment' => 0, // 是否开启评论，1：关闭
        'closeReplay' => 1, // 是否关闭回放 1 关闭
        'closeShare' => 0,   //  是否关闭分享 1 关闭
        'closeKf' => 0 // 是否关闭客服，1 关闭
    ];

    /**
     * 获取直播房间
     * @param int $start
     * @param int $limit
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getRooms(int $start = 0, int $limit = 10): ResponseInterface|Response
    {
        $params = [
            'start' => $start,
            'limit' => $limit,
        ];

        return $this->api->postJson('wxa/business/getliveinfo', $params);
    }

    /**
     * 获取直播回放
     * @param int $roomId
     * @param int $start
     * @param int $limit
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getPlaybacks(int $roomId, int $start = 0, int $limit = 10): ResponseInterface|Response
    {
        $params = [
            'action' => 'get_replay',
            'room_id' => $roomId,
            'start' => $start,
            'limit' => $limit,
        ];

        return $this->api->postJson('wxa/business/getliveinfo', $params);
    }

    /**
     * 创建直播间
     * @param array $data
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function createRoom(array $data): ResponseInterface|Response
    {
        $params = array_merge($this->createData, $data);
        return $this->api->postJson('wxaapi/broadcast/room/create', $params);
    }

    /**
     * 直播间导入商品
     * @param int $room_id
     * @param $ids
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function roomAddGoods(int $room_id, $ids): ResponseInterface|Response
    {
        $params = [
            'ids' => $ids,
            'roomId' => $room_id
        ];
        return $this->api->postJson('wxaapi/broadcast/room/addgoods', $params);
    }

    /**
     * 获取商品列表信息
     * @param int $status
     * @param int $page
     * @param int $limit
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getGoodsList(int $status, int $page = 0, int $limit = 30): ResponseInterface|Response
    {
        $params = [
            'offset' => $page * $limit,
            'limit' => $limit,
            'status' => $status
        ];
        return $this->api->get('wxaapi/broadcast/goods/getapproved', $params);
    }

    /**
     * 获取商品详情
     * @param array $ids
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getGooodsInfo(array $ids): ResponseInterface|Response
    {
        $params = [
            'goods_ids' => $ids
        ];
        return $this->api->get('wxa/business/getgoodswarehouse', $params);
    }

    /**
     * 商品添加并审核
     * @param string $coverImgUrl
     * @param string $name
     * @param int $priceType
     * @param string $url
     * @param string $price
     * @param string $price2
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function addGoods(string $coverImgUrl, string $name, int $priceType, string $url, string $price, string $price2 = ''): ResponseInterface|Response
    {
        $params = [
            'goodsInfo' => [
                'coverImgUrl' => $coverImgUrl,
                'name' => $name,
                'priceType' => $priceType,
                'price' => $price,
                'url' => $url
            ]
        ];
        if ($priceType != 1) $params['goodsInfo']['price2'] = $price2;
        return $this->api->postJson('wxaapi/broadcast/goods/add', $params);
    }

    /**
     * 撤回审核
     * @param int $goodsId
     * @param int $auditId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function resetauditGoods(int $goodsId, int $auditId): ResponseInterface|Response
    {
        $params = [
            'goodsId' => $goodsId,
            'auditId' => $auditId
        ];
        return $this->api->postJson('wxaapi/broadcast/goods/resetaudit', $params);
    }

    /**
     * 重新提交审核
     * @param int $goodsId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function auditGoods(int $goodsId): ResponseInterface|Response
    {
        $params = [
            'goodsId' => $goodsId
        ];
        return $this->api->postJson('wxaapi/broadcast/goods/autdit', $params);
    }

    /**
     * 删除商品
     * @param int $goodsId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function deleteGoods(int $goodsId): ResponseInterface|Response
    {
        $params = [
            'goodsId' => $goodsId
        ];
        return $this->api->postJson('wxaapi/broadcast/goods/delete', $params);
    }

    /**
     * 更新商品
     * @param int $goodsId
     * @param string $coverImgUrl
     * @param string $name
     * @param int $priceType
     * @param string $url
     * @param string $price
     * @param string $price2
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function updateGoods(int $goodsId, string $coverImgUrl, string $name, int $priceType, string $url, string $price, string $price2 = ''): ResponseInterface|Response
    {
        $params = ['goodsInfo' => [
            'goodsId' => $goodsId,
            'coverImgUrl' => $coverImgUrl,
            'name' => $name,
            'priceType' => $priceType,
            'price' => $price,
            'url' => $url
        ]];
        if ($priceType != 1) $params['goodsInfo']['price2'] = $price2;
        return $this->api->postJson('wxaapi/broadcast/goods/update', $params);
    }

    /**
     * 获取成员列表
     * @param int $role
     * @param int $page
     * @param int $limit
     * @param string $keyword
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getRoleList(int $role = 2, int $page = 0, int $limit = 30, string $keyword = ''): ResponseInterface|Response
    {
        $params = [
            'role' => $role,
            'offset' => $page * $limit,
            'limit' => $limit,
            'keyword' => $keyword
        ];
        return $this->api->get('wxaapi/broadcast/role/getrolelist', $params);
    }

    /**
     * 获取推流地址
     * @param int $roomId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function getPushUrl(int $roomId): ResponseInterface|Response
    {
        return $this->api->get('wxaapi/broadcast/room/getpushurl', ['roomId' => $roomId]);
    }

    /**
     * 获取长期订阅用户
     * @param string $pageBreak
     * @param int $limit
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function getFollowers(string $pageBreak = '', int $limit = 2000): ResponseInterface|Response
    {
        $params = ['limit' => $limit];
        if ($pageBreak) $params['page_break'] = $pageBreak;
        return $this->api->postJson('wxa/business/get_wxa_followers', $params);
    }

    /**
     * 群发消息
     * @param int $roomId
     * @param array $userOpenids
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function pushMessage(int $roomId, array $userOpenids): ResponseInterface|Response
    {
        return $this->api->postJson('wxa/business/push_message', [
            'room_id'     => $roomId,
            'user_openid' => $userOpenids,
        ]);
    }

    /**
     * 商品上下架
     * @param int $roomId
     * @param int $goodsId
     * @param int $onSale 1上架 0下架
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function goodsOnsale(int $roomId, int $goodsId, int $onSale): ResponseInterface|Response
    {
        return $this->api->postJson('wxaapi/broadcast/goods/onsale', [
            'roomId'  => $roomId,
            'goodsId' => $goodsId,
            'onSale'  => $onSale,
        ]);
    }

    /**
     * 开关客服 0开启 1关闭
     * @param int $roomId
     * @param int $closeKf
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function closeKf(int $roomId, int $closeKf): ResponseInterface|Response
    {
        return $this->api->postJson('wxaapi/broadcast/room/updatekf', [
            'roomId'  => $roomId,
            'closeKf' => $closeKf,
        ]);
    }

    /**
     * 开关评论 1禁言 0取消禁言
     * @param int $roomId
     * @param int $banComment
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function banComment(int $roomId, int $banComment): ResponseInterface|Response
    {
        return $this->api->postJson('wxaapi/broadcast/room/updatecomment', [
            'roomId'     => $roomId,
            'banComment' => $banComment,
        ]);
    }

    /**
     * 更新官方收录 1公开 0不公开
     * @param int $roomId
     * @param int $isFeedsPublic
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function updateFeedPublic(int $roomId, int $isFeedsPublic): ResponseInterface|Response
    {
        return $this->api->postJson('wxaapi/broadcast/room/updatefeedpublic', [
            'roomId'        => $roomId,
            'isFeedsPublic' => $isFeedsPublic,
        ]);
    }

    /**
     * 添加助手
     * @param int $roomId
     * @param array $users [['username'=>'','nickname'=>''], ...]
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function addAssistant(int $roomId, array $users): ResponseInterface|Response
    {
        return $this->api->postJson('wxaapi/broadcast/room/addassistant', [
            'roomId' => $roomId,
            'users'  => $users,
        ]);
    }

    /**
     * 删除助手
     * @param int $roomId
     * @param string $username
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function removeAssistant(int $roomId, string $username): ResponseInterface|Response
    {
        return $this->api->postJson('wxaapi/broadcast/room/removeassistant', [
            'roomId'   => $roomId,
            'username' => $username,
        ]);
    }

    /**
     * 获取商品信息与审核状态（仓库）
     * @param array $goodsIds
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function getGoodsWarehouse(array $goodsIds): ResponseInterface|Response
    {
        return $this->api->postJson('wxa/business/getgoodswarehouse', ['goods_ids' => $goodsIds]);
    }

    /**
     * 获取审核通过的商品列表
     * @param array $params
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function getApproved(array $params): ResponseInterface|Response
    {
        return $this->api->get('wxaapi/broadcast/goods/getapproved', $params);
    }
}
