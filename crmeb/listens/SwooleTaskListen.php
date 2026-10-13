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


namespace crmeb\listens;

use app\common\repositories\store\service\StoreServiceLogRepository;
use app\common\repositories\store\service\StoreServiceReplyRepository;
use app\common\repositories\store\service\StoreServiceUserRepository;
use app\common\repositories\system\admin\AdminLogRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\user\UserVisitRepository;
use app\webscoket\handler\UserHandler;
use app\webscoket\Manager;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\SendNewsJob;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Queue;
use think\swoole\Websocket;

class SwooleTaskListen implements ListenerInterface
{
    /**
     * @var array
     */
    protected $task = [];

    public function handle($task): void
    {
        $request = request();
        if (method_exists($request, 'clearCache')) {
            call_user_func([$request, 'clearCache']);
        }
        $payload = $this->normalizePayload($task);
        $this->task = $payload;
        $type = $payload['type'] ?? '';
        $data = $payload['data'] ?? [];

        if (!$type || !is_array($data) || !method_exists($this, $type)) {
            Log::warning('未知 SwooleTask 任务：' . var_export($payload, true));
            return;
        }

        $this->{$type}($data);
    }

    protected function normalizePayload($task): array
    {
        if (is_array($task)) {
            return $task;
        }

        if (is_object($task) && isset($task->data) && is_array($task->data)) {
            return $task->data;
        }

        return [];
    }

    protected function pushToFd($fd, $data, array $except = []): bool
    {
        if ($this->inExcept($fd, $except)) {
            return false;
        }

        $sender = $this->resolveSender($fd);
        if (!$sender || $this->inExcept($sender, $except)) {
            return false;
        }

        try {
            app()->make(Websocket::class)->to($sender)->push($data);
            return true;
        } catch (\Throwable $e) {
            Log::info('WebSocket 推送失败：' . $e->getMessage());
            return false;
        }
    }

    protected function resolveSender($fd): ?string
    {
        $fd = (string)$fd;
        if (strpos($fd, '.') !== false) {
            return $fd;
        }

        $info = Cache::get('_ws_f_' . $fd);
        if (is_array($info) && !empty($info['sender'])) {
            return (string)$info['sender'];
        }

        return null;
    }

    protected function inExcept($fd, array $except): bool
    {
        return in_array($fd, $except, true) || in_array((string)$fd, array_map('strval', $except), true);
    }

    public function message(array $data)
    {
        $uid = is_array($data['uid']) ? $data['uid'] : [$data['uid']];
        $except = $data['except'] ?? [];
        if (!count($uid) && $data['type'] != 'user') {
            $fds = $data['type'] == 'mer' ? Manager::merFd($data['mer_id'] ?? 0) : Manager::userFd(0);
            foreach ($fds as $fd) {
                $this->pushToFd($fd, $data['data'], $except);
            }
        } else {
            foreach ($uid as $id) {
                $fds = Manager::userFd(array_search($data['type'], Manager::USER_TYPE), $id);
                foreach ($fds as $fd) {
                    $this->pushToFd($fd, $data['data'], $except);
                }
            }
        }
    }

    /**
     * //TODO 用户给客服发送消息
     *
     * @param array $data
     * @author xaboy
     * @day 2020/6/15
     */
    public function chatToService(array $data)
    {
        $flag = UserHandler::serviceOnline($data['uid'], $data['data']['uid']);
        $serviceLogRepository = app()->make(StoreServiceLogRepository::class);
        $lst = Cache::sMembers('m_chat' . $data['uid']) ?: [];
        foreach ($lst as $item) {
            [$fd, $merId, $toUid] = explode('/', $item);
            $sendData = $data['data'];
            $sendData['is_get'] = 1;
            if ($this->pushToFd($fd, ['type' => $toUid == $data['data']['uid'] ? 'chat' : 'back_chat', 'data' => $sendData], $data['except'] ?? [])) {
                $data['data']['is_get'] = 1;
            }
        }
        if (!$flag) {
            //TODO 客服消息提醒
            $nickname = app()->make(UserRepository::class)->getUsername($data['data']['uid']);
            Queue::push(SendNewsJob::class, [
                $data['uid'],
                [
                    'title' => '收到用户【' . $nickname . '】的咨询消息，请及时查看',
                    'description' => $data['data'],
                    'url' => rtrim(systemConfig('site_url'), '/') . '/pages/chat/customer_list/chat?userId=' . $data['data']['uid'] . '&mer_id=' . $data['data']['mer_id'],
                    'image' => rtrim(systemConfig('site_url'), '/') . '/static/service_wechat_msg.jpg'
                ]
            ]);
        }else{
            $serviceLogRepository->serviceRead($data['data']['mer_id'], $data['data']['uid'], $data['data']['service_id']);
            app()->make(StoreServiceUserRepository::class)->read($data['data']['mer_id'], $data['data']['uid'], true);
        }
        if ($data['data']['msn_type'] === 1) {
            $serviceLogRepository = app()->make(StoreServiceLogRepository::class);
            $reply = app()->make(StoreServiceReplyRepository::class)->keywordByValidData($data['data']['msn'], $data['data']['mer_id']);
            if ($reply) {
                $log = null;
                if (($reply->type === 2 || $reply->type === 1) && $reply['content']) {
                    $log = $serviceLogRepository->create([
                        'mer_id' => $data['data']['mer_id'],
                        'msn' => $reply['content'],
                        'uid' => $data['data']['uid'],
                        'service_id' => $data['data']['service_id'],
                        'remind' => 1,
                        'send_type' => 1,
                        'msn_type' => $reply->type === 2 ? 3 : 1,
                        'type' => 1,
                        'service_type' => 0,
                    ]);
                }
                if ($log) {
                    $lst = Cache::sMembers('u_chat' . $data['data']['uid']) ?: [];
                    $log->append(['service']);
                    foreach ($lst as $item) {
                        [$fd, $merId] = explode('/', $item);
                        if ($merId == $data['data']['mer_id']) {
                            $this->pushToFd($fd, ['type' => 'chat', 'data' => $log->toArray()]);
                        }
                    }
                    $lst = Cache::sMembers('m_chat' . $log->service->uid) ?: [];
                    foreach ($lst as $item) {
                        [$fd, $merId, $toUid] = explode('/', $item);
                        if ($toUid == $data['data']['uid']) {
                            $this->pushToFd($fd, ['type' => 'chat', 'data' => $log->toArray()]);
                        }
                    }
                }
            }
        }
    }

    /**
     * //TODO 客服给用户发送消息
     * @param array $data
     * @author xaboy
     * @day 2020/6/15
     */
    public function chatToUser(array $data)
    {
        $flag = UserHandler::userOnline($data['uid'], $data['data']['mer_id']);
        if ($flag) {
            $serviceLogRepository = app()->make(StoreServiceLogRepository::class);
            $lst = Cache::sMembers('u_chat' . $data['uid']) ?: [];
            foreach ($lst as $item) {
                [$fd, $merId] = explode('/', $item);
                $sendData = $data['data'];
                $sendData['is_get'] = 1;
                if ($merId == $data['data']['mer_id'] && $this->pushToFd($fd, ['type' => 'chat', 'data' => $sendData], $data['except'] ?? [])) {
                    $data['data']['is_get'] = 1;
                }
            }
            $serviceLogRepository->userRead($data['data']['mer_id'], $data['data']['uid']);
            app()->make(StoreServiceUserRepository::class)->read($data['data']['mer_id'], $data['data']['uid']);
        } else {
            //TODO 用户消息提醒
            Queue::push(SendNewsJob::class, [
                $data['uid'],
                [
                    'title' => '您收到新的消息，请及时查看',
                    'description' => $data['data'],
                    'url' => rtrim(systemConfig('site_url'), '/') . '/pages/chat/customer_list/chat?mer_id=' . $data['data']['mer_id'],
                    'image' => rtrim(systemConfig('site_url'), '/') . '/static/service_wechat_msg.jpg'
                ]
            ]);
        }
    }

    public function admin(array $data)
    {
        $this->message([
                'uid' => $data['uid'] ?? [],
                'type' => 'admin',
                'data' => $data['data']
            ]
        );
    }

    public function merchant(array $data)
    {
        $this->message([
                'uid' => $data['uid'] ?? [],
                'mer_id' => $data['mer_id'],
                'type' => 'mer',
                'data' => $data['data']
            ]
        );
    }

    public function visit(array $data)
    {
        /** @var UserVisitRepository $make */
        $make = app()->make(UserVisitRepository::class);
        $make->create($data);
    }

    public function log(array $data)
    {
        app()->make(AdminLogRepository::class)->create($data['merId'], $data['result']);
    }
}
