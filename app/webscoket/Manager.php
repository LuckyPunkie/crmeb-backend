<?php

namespace app\webscoket;

use app\webscoket\handler\AdminHandler;
use app\webscoket\handler\MerchantHandler;
use app\webscoket\handler\ServiceHandler;
use app\webscoket\handler\TransferHandler;
use app\webscoket\handler\UserHandler;
use crmeb\services\ConnectionStateManager;
use crmeb\services\metrics\ImMetricsCollector;
use Swoole\Table;
use Swoole\WebSocket\Frame;
use think\facade\Cache;
use think\facade\Log;
use think\Request;
use think\response\Json;
use think\swoole\contract\websocket\HandlerInterface;
use think\swoole\Websocket;
use think\swoole\websocket\Event as WsEvent;

class Manager implements HandlerInterface
{
    public const USER_TYPE = [
        'admin' => 0,
        'user'  => 1,
        'mer'   => 2,
        'ser'   => 3,
    ];

    protected const HANDLER_BIND = [
        'admin' => 'websocket_handler_admin',
        'user'  => 'websocket_handler_user',
        'mer'   => 'websocket_handler_mer',
        'ser'   => 'websocket_handler_ser',
    ];

    protected const HANDLER_CLASS = [
        'admin' => AdminHandler::class,
        'user'  => UserHandler::class,
        'mer'   => MerchantHandler::class,
        'ser'   => ServiceHandler::class,
    ];

    protected Websocket $websocket;

    protected static array $localConnections = [];

    public function __construct(Websocket $websocket)
    {
        $this->websocket = $websocket;
        app()->bind('websocket_handler_admin', AdminHandler::class);
        app()->bind('websocket_handler_user', UserHandler::class);
        app()->bind('websocket_handler_mer', MerchantHandler::class);
        app()->bind('websocket_handler_ser', ServiceHandler::class);
    }

    public function onOpen(Request $request): void
    {
        $type = (string)$request->param('type', '');
        $token = (string)$request->param('token', '');
        // 小程序：/api/temp-ws-token 下发的一次性凭证走 query t=
        $tempKey = (string)$request->param('t', '');
        if ($tempKey !== '' && $tempKey !== 'undefined') {
            $cached = Cache::get('ws_temp_token:' . $tempKey);
            if (is_string($cached) && $cached !== '') {
                $token = $cached;
                Cache::delete('ws_temp_token:' . $tempKey);
            }
        }
        if ($token === 'undefined' || $token === 'false') {
            $token = '';
        }

        if (!$type || !isset(self::USER_TYPE[$type])) {
            $this->websocket->close();
            return;
        }

        $fd = $this->getFrameFd();
        $sender = $this->websocket->getSender();

        try {
            $handler = $this->makeHandler($type);
            $response = $handler->login([
                'token' => $token,
                'fd'    => $fd,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WebSocket login failed: ' . $e->getMessage());
            $this->websocket->close();
            return;
        }

        $payload = $this->extractResponseData($response);
        if (($payload['status'] ?? 0) != 200 || empty($payload['data']['uid'])) {
            $this->websocket->close();
            return;
        }

        $uid = (int)$payload['data']['uid'];
        $typeIndex = self::USER_TYPE[$type];
        $merId = (int)($payload['data']['mer_id'] ?? 0);
        $extraPayload = $payload['data']['payload'] ?? [];

        $this->login($type, $typeIndex, $uid, $fd, $sender, $merId, $extraPayload);

        $this->pushResponse(app('json')->message('ping', ['now' => time()]));
        $this->pushResponse(app('json')->success($payload['data']));
    }

    public function onMessage(Frame $frame): void
    {
        $payload = json_decode($frame->data, true);
        if (!is_array($payload) || empty($payload['type'])) {
            return;
        }

        $eventType = $payload['type'];
        $data = $payload['data'] ?? [];
        $fd = (int)$frame->fd;

        if ($eventType === 'ping') {
            ConnectionStateManager::heartbeat($fd);
            $this->pushResponse(app('json')->message('ping', ['now' => time()]));
            return;
        }

        $info = self::$localConnections[$fd] ?? null;
        if (!$info) {
            $this->websocket->close();
            return;
        }

        ConnectionStateManager::heartbeat($fd);

        if (in_array($eventType, ['transfer_request', 'transfer_targets'], true)) {
            $handler = app()->make(TransferHandler::class);
        } else {
            $handler = $this->makeHandler($info['handler']);
        }

        if (!method_exists($handler, $eventType)) {
            return;
        }

        $result = [
            'type'    => $info['type_index'],
            'uid'     => $info['uid'],
            'fd'      => $fd,
            'frame'   => $frame,
            'data'    => $data,
            'payload' => $info['payload'] ?? [],
        ];

        try {
            $response = $handler->{$eventType}($result);
            if ($response !== null) {
                $this->pushResponse($response);
            }
        } catch (\Throwable $e) {
            Log::warning("WebSocket event {$eventType} failed: " . $e->getMessage());
            $this->pushResponse(app('json')->message('err_tip', $e->getMessage()));
        }
    }

    public function onClose(): void
    {
        $fd = $this->getFrameFd();
        $info = self::$localConnections[$fd] ?? null;
        if (!$info) {
            return;
        }

        $handler = $this->makeHandler($info['handler']);
        if (method_exists($handler, 'close')) {
            try {
                $handler->close([
                    'type' => $info['type_index'],
                    'uid'  => $info['uid'],
                    'fd'   => $fd,
                ]);
            } catch (\Throwable $e) {
                Log::info('WebSocket close handler failed: ' . $e->getMessage());
            }
        }

        $this->logout($fd, $info);
    }

    public function encodeMessage($message)
    {
        if ($message instanceof WsEvent) {
            return json_encode([
                'type' => $message->type,
                'data' => $message->data,
            ]);
        }

        if (is_array($message)) {
            return json_encode($message);
        }

        return (string)$message;
    }

    public static function userFd($type, $uid = 0): array
    {
        $typeIndex = self::normalizeTypeIndex($type);
        $fds = [];

        foreach (self::$localConnections as $fd => $info) {
            if ((int)$info['type_index'] !== $typeIndex) {
                continue;
            }
            if ($uid && (int)$info['uid'] !== (int)$uid) {
                continue;
            }
            $fds[] = $fd;
        }

        if (!empty($fds)) {
            return array_values(array_unique($fds));
        }

        $cacheKey = '_ws_t_' . $typeIndex . '_' . (int)$uid;
        $cached = Cache::sMembers($cacheKey) ?: [];
        return array_map('intval', $cached);
    }

    public static function merFd($merId): array
    {
        $merId = (int)$merId;
        $fds = [];

        foreach (self::$localConnections as $fd => $info) {
            if ((int)($info['mer_id'] ?? 0) === $merId && $info['handler'] === 'mer') {
                $fds[] = $fd;
            }
        }

        if (!empty($fds)) {
            return array_values(array_unique($fds));
        }

        $cacheKey = '_ws_mer_' . $merId;
        $cached = Cache::sMembers($cacheKey) ?: [];
        return array_map('intval', $cached);
    }

    protected function login(
        string $handlerType,
        int $typeIndex,
        int $uid,
        int $fd,
        string $sender,
        int $merId = 0,
        array $payload = []
    ): void {
        self::$localConnections[$fd] = [
            'handler'    => $handlerType,
            'type_index' => $typeIndex,
            'uid'        => $uid,
            'mer_id'     => $merId,
            'sender'     => $sender,
            'payload'    => $payload,
        ];

        $this->setTable($fd, $typeIndex, $uid);

        Cache::set('_ws_f_' . $fd, [
            'sender'  => $sender,
            'type'    => $typeIndex,
            'uid'     => $uid,
            'mer_id'  => $merId,
            'handler' => $handlerType,
        ], 7200);
        Cache::sadd('_ws_t_' . $typeIndex . '_' . $uid, $fd);
        Cache::expire('_ws_t_' . $typeIndex . '_' . $uid, 7200);

        if ($merId > 0 && $handlerType === 'mer') {
            Cache::sadd('_ws_mer_' . $merId, $fd);
            Cache::expire('_ws_mer_' . $merId, 7200);
        }

        ConnectionStateManager::register($fd, $uid, $handlerType, $merId);
        ImMetricsCollector::recordConnection($handlerType);
    }

    protected function logout(int $fd, array $info): void
    {
        unset(self::$localConnections[$fd]);

        $typeIndex = (int)$info['type_index'];
        $uid = (int)$info['uid'];
        $merId = (int)($info['mer_id'] ?? 0);
        $handlerType = (string)$info['handler'];

        Cache::delete('_ws_f_' . $fd);
        Cache::srem('_ws_t_' . $typeIndex . '_' . $uid, $fd);
        if ($merId > 0) {
            Cache::srem('_ws_mer_' . $merId, $fd);
        }

        $this->deleteTable($fd);
        ConnectionStateManager::unregister($fd, $uid);
        ImMetricsCollector::recordDisconnection($handlerType);
    }

    protected function makeHandler(string $type)
    {
        $bind = self::HANDLER_BIND[$type] ?? 'websocket_handler_user';
        if (!app()->bound($bind)) {
            app()->bind($bind, self::HANDLER_CLASS[$type] ?? UserHandler::class);
        }
        return app()->make($bind);
    }

    protected function pushResponse($response): void
    {
        if (!$response instanceof Json) {
            return;
        }

        $data = $response->getData();
        if (!is_array($data)) {
            return;
        }

        if (isset($data['type'])) {
            $this->websocket->push(json_encode($data));
            return;
        }

        if (($data['status'] ?? 0) == 200) {
            $this->websocket->push(json_encode([
                'type' => 'success',
                'data' => $data['data'] ?? [],
            ]));
            return;
        }

        $this->websocket->push(json_encode([
            'type' => 'err_tip',
            'data' => $data['message'] ?? 'error',
        ]));
    }

    protected function extractResponseData($response): array
    {
        if ($response instanceof Json) {
            $data = $response->getData();
            return is_array($data) ? $data : [];
        }
        return is_array($response) ? $response : [];
    }

    protected function getFrameFd(): int
    {
        $sender = (string)$this->websocket->getSender();
        if (strpos($sender, '.') !== false) {
            return (int)substr($sender, strrpos($sender, '.') + 1);
        }
        return (int)$sender;
    }

    protected function setTable(int $fd, int $type, int $uid): void
    {
        try {
            if (app()->has('swoole.table.user')) {
                /** @var Table $table */
                $table = app()->make('swoole.table.user');
                $table->set((string)$fd, ['fd' => $fd, 'type' => $type, 'uid' => $uid]);
            }
        } catch (\Throwable $e) {
        }
    }

    protected function deleteTable(int $fd): void
    {
        try {
            if (app()->has('swoole.table.user')) {
                /** @var Table $table */
                $table = app()->make('swoole.table.user');
                $table->del((string)$fd);
            }
        } catch (\Throwable $e) {
        }
    }

    protected static function normalizeTypeIndex($type): int
    {
        if (is_string($type) && isset(self::USER_TYPE[$type])) {
            return (int)self::USER_TYPE[$type];
        }

        if (is_numeric($type)) {
            return (int)$type;
        }

        return 0;
    }
}
