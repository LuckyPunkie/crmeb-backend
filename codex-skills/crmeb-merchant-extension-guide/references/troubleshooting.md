# 部署和排障

## 修改代码后不生效

排查：

1. 是否运行在 Swoole 常驻进程。
2. 是否已重启 Swoole。
3. 是否清理缓存：`php think clear`。
4. 是否改的是实际运行目录。

## Swoole 命令

当前项目中以实际命令为准：

```bash
php think help swoole
php think swoole
```

不要默认存在 `restart/status/stop` 子命令。

## 定时器不执行

排查：

1. `app/event.php` 中事件是否注册。
2. `env('INSTALLED', false)` 是否为真。
3. Swoole 生命周期监听是否触发。
4. 定时器是否注册在正确 worker。
5. 日志是否写到当天 `runtime/log`。
6. 是否因为 Facade/sandbox/Redis 协程错误中断。

## 队列不消费

排查：

1. Swoole queue 是否开启。
2. Redis 配置是否正确。
3. 队列名是否一致。
4. 是否有 failed job。
5. worker 是否在运行。

## Redis socket 并发错误

典型错误：

```text
Socket has already been bound to another coroutine
reading of the same socket in coroutine at the same time is not allowed
```

常见原因：

- 缓存了原生 Redis handler。
- 多个协程共用同一 Redis socket。
- 定时器重复注册，多个任务同时执行同一连接。

处理建议：

- 不要把 `Cache::store('redis')->handler()` 存到长期属性。
- 每次从当前上下文获取 cache/redis。
- 定时任务只注册一次。

## 常用日志

- `runtime/log/*_error.log`
- `runtime/log/*_info.log`
- `runtime/swoole.log`，如配置存在

建议客户排障时先确认当天日志文件是否生成。

