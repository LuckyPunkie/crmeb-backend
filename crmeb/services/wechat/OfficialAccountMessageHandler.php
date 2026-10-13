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

namespace crmeb\services\wechat;

use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\wechat\WechatQrcodeRepository;
use app\common\repositories\wechat\WechatReplyRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\CopyCommand;
use Exception;
use think\facade\Cache;

class OfficialAccountMessageHandler
{
    public function handle($message)
    {
        $openId = $message->FromUserName;
        $message->EventKey = str_replace('qrscene_', '', $message->EventKey ?? '');
        $userInfo = OfficialAccount::getUserInfo($openId);
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $users = $wechatUserRepository->syncUser($openId, $userInfo, true);
        $scanLogin = function () use ($message, $users) {
            $ticket = $message->EventKey ?? '';
            if (strpos($ticket, '_sys_scan_login.') === 0) {
                $key = str_replace('_sys_scan_login.', '', $ticket);
                if (Cache::has('_scan_login' . $key) && ($users[1]['uid'] ?? 0)) {
                    Cache::set('_scan_login' . $key, $users[1]['uid']);
                }
            }
        };
        $response = null;
        /** @var WechatReplyRepository $make */
        $make = app()->make(WechatReplyRepository::class);
        event('wechat.message', compact('message'));
        switch ($message->MsgType) {
            case 'event':
                event('wechat.event', compact('message'));
                switch (strtolower($message->Event)) {
                    case 'subscribe':
                        $scanLogin();
                        $response = $this->qrKeyByMessage($message->EventKey) ?: $make->reply('subscribe');
                        if (!empty($message->EventKey)) {
                            /** @var WechatQrcodeRepository $qr */
                            $qr = app()->make(WechatQrcodeRepository::class);
                            if ($qrInfo = $qr->ticketByQrcode($message->Ticket)) {
                                $qrInfo->incTicket();
                                if (strtolower($qrInfo['third_type']) == 'spread' && $users) {
                                    $spreadUid = $qrInfo['third_id'];
                                    if ($users[1]['uid'] == $spreadUid) {
                                        return '自己不能推荐自己';
                                    } elseif ($users[1]['spread_uid']) {
                                        return '已有推荐人!';
                                    }
                                    try {
                                        $users[1]->setSpread($spreadUid);
                                    } catch (Exception $e) {
                                        return '绑定推荐人失败';
                                    }
                                }
                            }
                        }
                        event('wechat.event.subscribe', compact('message'));
                        break;
                    case 'unsubscribe':
                        $wechatUserRepository->unsubscribe($openId);
                        event('wechat.event.unsubscribe', compact('message'));
                        break;
                    case 'scan':
                        $scanLogin();
                        $response = $this->qrKeyByMessage($message->EventKey) ?: $make->reply('subscribe');
                        /** @var WechatQrcodeRepository $qr */
                        $qr = app()->make(WechatQrcodeRepository::class);
                        if ($message->EventKey && ($qrInfo = $qr->ticketByQrcode($message->Ticket))) {
                            $qrInfo->incTicket();
                            if (strtolower($qrInfo['third_type']) == 'spread' && $users) {
                                $spreadUid = $qrInfo['third_id'];
                                if ($users[1]['uid'] == $spreadUid) {
                                    return '自己不能推荐自己';
                                } elseif ($users[1]['spread_uid']) {
                                    return '已有推荐人!';
                                }
                                try {
                                    $users[1]->setSpread($spreadUid);
                                } catch (Exception $e) {
                                    return '绑定推荐人失败';
                                }
                            }
                        }
                        event('wechat.event.scan', compact('message'));
                        break;
                    case 'location':
                        event('wechat.event.location', compact('message'));
                        break;
                    case 'click':
                        $response = $make->reply($message->EventKey);
                        event('wechat.event.click', compact('message'));
                        break;
                    case 'view':
                        event('wechat.event.view', compact('message'));
                        break;
                    case 'funds_order_pay':
                        if (($count = strpos($message['order_info']['trade_no'], '_')) !== false) {
                            $tradeNo = substr($message['order_info']['trade_no'], $count + 1);
                        } else {
                            $tradeNo = $message['order_info']['trade_no'];
                        }
                        $prefix = substr($tradeNo, 0, 3);
                        switch ($prefix) {
                            case StoreOrderRepository::TYPE_SN_ORDER:
                                $attach = 'order';
                                break;
                            case StoreOrderRepository::TYPE_SN_PRESELL:
                                $attach = 'presell';
                                break;
                            case StoreOrderRepository::TYPE_SN_USER_ORDER:
                                $attach = 'user_order';
                                break;
                            case StoreOrderRepository::TYPE_SN_USER_RECHARGE:
                                $attach = 'user_recharge';
                                break;
                            default:
                                $attach = '';
                                break;
                        }
                        if ($attach !== '') {
                            event('pay_success_' . $attach, ['order_sn' => $message['order_info']['trade_no'], 'data' => $message, 'is_combine' => 0]);
                        }
                        break;
                }
                break;
            case 'text':
                if (preg_match('/^(\/@[1-9]{1}).*\*\//', $message->Content)) {
                    $command = app()->make(CopyCommand::class)->getMassage($message->Content);
                    if (empty($command)) {
                        $response = Messages::textMessage('无效口令');
                    } else {
                        if ($command['type'] == 30) {
                            $command['type'] = 3;
                        }
                        $key = '_scan_url_' . $command['type'] . '_' . $command['id'] . '_' . $command['uid'];
                        $response = $this->qrKeyByMessage($key);
                    }
                } else {
                    $response = $make->reply($message->Content);
                }
                event('wechat.message.text', compact('message'));
                break;
            case 'image':
                event('wechat.message.image', compact('message'));
                break;
            case 'voice':
                event('wechat.message.voice', compact('message'));
                break;
            case 'video':
                event('wechat.message.video', compact('message'));
                break;
            case 'location':
                event('wechat.message.location', compact('message'));
                break;
            case 'link':
                event('wechat.message.link', compact('message'));
                break;
            default:
                event('wechat.message.other', compact('message'));
                break;
        }

        return $response ?? false;
    }

    protected function qrKeyByMessage($key)
    {
        if (strpos($key, '_scan_url_') !== 0) {
            return null;
        }
        $key = str_replace('_scan_url_', '', $key);
        $data = explode('_', $key);
        $siteUrl = rtrim(systemConfig('site_url'), '/');
        $make = app()->make(\app\common\repositories\store\product\ProductRepository::class);
        if ($data[0] === 'home') {
            $share = systemConfig(['share_title', 'share_info', 'share_pic']);
            $share['url'] = $siteUrl . '?spid=' . $data[1];
        } else if ($data[0] === 'mer') {
            $ret = app()->make(\app\common\repositories\system\merchant\MerchantRepository::class)->get($data[1]);
            if (!$ret) return null;
            $share = [
                'share_title' => $ret['mer_name'],
                'share_info' => $ret['mer_info'],
                'share_pic' => $ret['mer_avatar'],
                'url' => $siteUrl . '/pages/store/home/index?id=' . $data[1],
            ];
        } else if ($data[0] === 'p0') {
            $ret = $make->get($data[1]);
            if (!$ret) return null;
            $share = [
                'share_title' => $ret['store_name'],
                'share_info' => $ret['store_info'],
                'share_pic' => $ret['image'],
                'url' => $siteUrl . '/pages/goods_details/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
            ];
        } else if ($data[0] === 'p1') {
            $ret = $make->get($data[1]);
            if (!$ret) return null;
            $share = [
                'share_title' => $ret['store_name'],
                'share_info' => $ret['store_info'],
                'share_pic' => $ret['image'],
                'url' => $siteUrl . '/pages/activity/goods_seckill_details/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
            ];
        } else if ($data[0] === 'p2') {
            $ret = app()->make(\app\common\repositories\store\product\ProductPresellRepository::class)->search(['product_presell_id' => $data[1]])->find();
            if (!$ret) return null;
            $res = $make->get($ret['product_id']);
            $share = [
                'share_title' => $ret['store_name'],
                'share_info' => $ret['store_info'],
                'share_pic' => $res['image'],
                'url' => $siteUrl . '/pages/activity/presell_details/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
            ];
        } else if ($data[0] === 'p3') {
            $ret = app()->make(\app\common\repositories\store\product\ProductAssistSetRepository::class)->getSearch(['product_assist_set_id' => $data[1]])->find();
            if (!$ret) return null;
            $res = $make->get($ret['product_id']);
            $share = [
                'share_title' => $res['store_name'],
                'share_info' => $res['store_info'],
                'share_pic' => $res['image'],
                'url' => $siteUrl . '/pages/activity/assist_detail/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
            ];
        } else if ($data[0] === 'p4') {
            $ret = app()->make(\app\common\repositories\store\product\ProductGroupRepository::class)->get($data[1]);
            if (!$ret) return null;
            $res = $make->get($ret['product_id']);
            $share = [
                'share_title' => $res['store_name'],
                'share_info' => $res['store_info'],
                'share_pic' => $res['image'],
                'url' => $siteUrl . '/pages/activity/combination_details/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
            ];
        } else if ($data[0] === 'p40') {
            $res = app()->make(\app\common\repositories\store\product\ProductGroupBuyingRepository::class)->getSearch(['group_buying_id' => $data[1]])->find();
            if (!$res) return null;
            $ret = $make->get($res->productGroup['product_id']);
            if (!$ret) return null;
            $share = [
                'share_title' => $ret['store_name'],
                'share_info' => $ret['store_info'],
                'share_pic' => $ret['image'],
                'url' => $siteUrl . '/pages/activity/combination_status/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
            ];
        } else if ($data[0] === 'form') {
            $res = app()->make(\app\common\repositories\store\StoreActivityRepository::class)->getSearch(['activity_id' => $data[1]])->find();
            if (!$res) return null;
            $share = [
                'share_title' => $res['activity_name'],
                'share_info' => $res['info'],
                'share_pic' => $res['pic'],
                'url' => $siteUrl . '/pages/activity/registrate_activity/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
            ];
        } else {
            return null;
        }
        return Messages::newsMessage($share['share_title'], $share['share_info'], $share['url'], $share['share_pic']);
    }
}
