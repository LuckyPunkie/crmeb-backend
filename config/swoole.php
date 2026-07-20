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


use app\webscoket\Manager;
use Swoole\Table;

if (strtolower(substr(PHP_OS, 0, 3)) === 'win') {
    return [];
} else {
    return [
        'http'       => [
            'enable'     => true,
            'host'       => env('SWOOLE_HOST', '0.0.0.0'), // 监听地址
            'port'       => env('SWOOLE_PORT', 8324), // 监听端口
            'worker_num' => swoole_cpu_num(),
            'options'    => [
                'pid_file'              => runtime_path() . 'swoole.pid',
                'log_file'              => runtime_path() . 'swoole.log',
                'daemonize'             => false,
                // Normally this value should be 1~4 times larger according to your cpu cores.
                'reactor_num'           => swoole_cpu_num(),
                'worker_num'            => swoole_cpu_num(),
                'package_max_length'    => 50 * 1024 * 1024,
                'buffer_output_size'    => 10 * 1024 * 1024,
                'socket_buffer_size'    => 128 * 1024 * 1024,
                'max_request'           => 3000,
                'send_yield'            => true,
                'reload_async'          => true,
            ],
        ],
        'websocket'  => [
            'enable'        => true,
            'route'         => false,
            'handler'       => Manager::class,
            'ping_interval' => 25000,
            'ping_timeout'  => 60000,
            'room'          => [
                'type'  => 'table',
                'table' => [
                    'room_rows'   => 8192,
                    'room_size'   => 2048,
                    'client_rows' => 4096,
                    'client_size' => 2048,
                ],
                'redis' => [
                    'host'          => '127.0.0.1',
                    'port'          => 6379,
                    'max_active'    => 3,
                    'max_wait_time' => 5,
                ],
            ],
            'listen'        => [],
            'subscribe'     => [],
        ],
        'rpc'        => [
            'server' => [
                'enable'   => false,
                'port'     => 9000,
                'services' => [
                ],
            ],
            'client' => [
            ],
        ],
        'queue'      => [
            'enable'  => env('APP_DEBUG', true),
            'workers' => [
                env('queue_name', 'default') . '@redis' => [
                    'worker_num' => (int)env('SWOOLE_QUEUE_WORKER_NUM', 1),
                    'tries'      => (int)env('SWOOLE_QUEUE_TRIES', 1),
                    'timeout'    => (int)env('SWOOLE_QUEUE_TIMEOUT', 60),
                    'sleep'      => (int)env('SWOOLE_QUEUE_SLEEP', 1),
                ],
                env('SWOOLE_TASK_QUEUE', 'swoole_task') . '@redis' => [
                    'worker_num' => (int)env('SWOOLE_TASK_WORKER_NUM', 1),
                    'tries'      => (int)env('SWOOLE_TASK_TRIES', 1),
                    'timeout'    => (int)env('SWOOLE_TASK_TIMEOUT', 60),
                    'sleep'      => (int)env('SWOOLE_TASK_SLEEP', 1),
                ],
            ],
        ],
        'hot_update' => [
            'enable'  => env('SWOOLE_HOT_UPDATE', false),
            'name'    => ['*.php'],
            'include' => [app_path(), root_path('crmeb')],
            'exclude' => [],
        ],
        //连接池
        'pool'       => [
            'db'    => [
                'enable'        => true,
                'min_active'    => 5,
                'max_active'    => 50,
                'max_wait_time' => 5,
            ],
            'cache' => [
                'enable'        => true,
                'min_active'    => 5,
                'max_active'    => 50,
                'max_wait_time' => 5,
            ],
        ],
        'coroutine'  => [
            'enable'         => true,
        ],
        'tables'     => [
            'user' => [
                'size'    => 204800,
                'columns' => [
                    ['name' => 'fd', 'type' => Table::TYPE_INT],
                    ['name' => 'type', 'type' => Table::TYPE_INT],
                    ['name' => 'uid', 'type' => Table::TYPE_INT]
                ]
            ]
        ],
        //每个worker里需要预加载以共用的实例
        'concretes'  => [],
        //重置器
        'resetters'  => [],
        //每次请求前需要清空的实例
        'instances'  => [],
        //每次请求前需要重新执行的服务
        'services'   => [],
        'locks'      => ['group_buying'],
    ];
}
