# AGENTS.md

本文件为 Codex（Codex.ai/code）在本仓库中工作时提供项目说明和协作约定。

## 项目概览

CRMEB Merchant 是一个基于 ThinkPHP + Swoole 的多商户电商平台，支持商户、用户、订单、商品、支付（微信/支付宝）、配送、优惠券、队列任务等能力。

**PHP 版本**：8.0+
**框架**：当前 `composer.json` 实际使用 ThinkPHP 8、ThinkORM、think-swoole 4.1
**重要依赖**：EasyWeChat 6、firebase/php-jwt、overtrue/socialite、think-queue

## 架构

### 目录结构

```text
app/
├── controller/          # 按入口划分的控制器
│   ├── admin/           # 平台后台控制器
│   ├── api/             # 用户侧 API 控制器
│   ├── merchant/        # 商户后台控制器
│   └── pc/              # PC Web 控制器
├── common/
│   ├── repositories/    # Repository 层，承载主要业务编排和数据访问入口
│   │   ├── store/       # 订单、商品、优惠券等业务
│   │   ├── user/        # 用户、账单、佣金等业务
│   │   ├── system/      # 系统配置、商户、财务等业务
│   │   └── wechat/      # 微信相关业务
│   ├── model/           # ThinkORM 模型
│   ├── dao/             # DAO 数据访问对象
│   └── middleware/      # HTTP 中间件
├── validate/            # 表单/请求验证类
crmeb/
├── basic/               # 基础类，如 BaseController、BaseManager
├── services/            # 业务服务和第三方集成服务
├── jobs/                # 队列任务
├── listens/             # 事件监听器
└── utils/               # 工具类
config/                  # ThinkPHP、数据库、Redis、Swoole、微信等配置
route/                   # 路由定义
public/
├── index.php            # HTTP 入口
```

### 核心模式

**Repository 模式**：主要业务逻辑集中在 `app/common/repositories/`。Repository 通常继承 `BaseRepository`，并委托 DAO/Model 完成数据访问。

**控制器层级**：控制器通常继承 `crmeb\basic\BaseController`，常用能力包括：

- 通过 `$this->request` 处理请求
- 通过 `app('json')->success()` / `app('json')->fail()` 返回 JSON
- 通过 `$this->validate()` 做验证
- 通过 `$this->getPage()` 获取分页参数

**服务层**：第三方集成和部分业务服务位于 `crmeb/services/`，常通过 `app()->make(ServiceName::class)` 或依赖注入调用。

**队列任务**：后台任务位于 `crmeb/jobs/`，常通过 `Queue::push(JobClass::class, $data)` 投递。

**事件监听**：事件定义在 `app/event.php`，监听器位于 `crmeb/listens/`。定时任务通常与 Swoole 生命周期事件和 `create_timer` 事件相关。

## 命名约定

遵循 PSR-2 / PSR-4，并注意 CRMEB 项目内的惯例：

| 元素 | 约定 | 示例 |
| --- | --- | --- |
| 目录/文件 | 小写 + 下划线 | `store_order.php` |
| 类/接口/Trait | CamelCase | `StoreOrderRepository` |
| 方法/属性 | camelCase | `getPage()` |
| 控制器 action | camelCase | `getUserInfo()` |
| 常量 | UPPER_SNAKE | `PAY_TYPE` |
| 表/字段 | 小写 + 下划线 | `store_order`, `user_name` |

## 常用命令

```bash
# 安装依赖
composer install

# 启动 Swoole 服务
php think swoole

# 清理缓存
php think clear

# PHP 文件语法检查
php -l path/to/file.php
```

注意：当前 think-swoole 命令为 `php think swoole`。不要默认存在 `php think swoole restart/status/stop` 子命令，实际以 `php think help swoole` 为准。

## JSON 响应格式

控制器通常使用 `json` 服务返回：

```php
// 成功
return app('json')->success($data);
return app('json')->success('message', $data);

// 失败
return app('json')->fail('error message');
return app('json')->fail('error message', 400);

// 状态响应，常用于支付等中间状态
return app('json')->status(201, 'message', $data);
```

## 请求数据

常见参数读取方式：

```php
// 单个值
$id = $this->request->param('id/d', 0);

// 分页
[$page, $limit] = $this->getPage();

// 带默认值的批量参数
$filters = $this->request->getMore([['status', 0], ['name', '']]);

// 通过方法参数注入 repository 或验证类
public function update(UserRepository $repository) { }
```

## 数据库

- 优先使用 `app/common/model/` 中的 ThinkORM 模型和 DAO/Repository，不要随意直接使用 `Db::table()`。
- 模型通常继承 `BaseModel`，并定义 `tablePk()`、`tableName()`。
- 写入时注意字段白名单、模型保存逻辑、事务边界和并发库存问题。

## 验证

使用 `app/validate/` 下的验证类：

```php
public function update(UserAuthValidate $validate)
{
    $data = $this->request->params([...]);
    $validate->scene('update')->check($data);
}
```

## 错误处理

面向用户的业务错误优先抛出 `ValidateException`，系统异常保留原异常或记录日志：

```php
throw new ValidateException('错误信息');
```

## Swoole 注意事项

- Swoole 下服务对象可能常驻内存，不要把请求级状态长期放在对象属性中。
- 不要缓存原生 Redis handler，例如长期保存 `Cache::store('redis')->handler()`。
- 定时器、队列、协程环境中要注意 Redis/PDO 连接复用问题。
- worker 生命周期早期直接使用 Facade 可能遇到 sandbox app 未初始化问题。
- 定时器应只注册一次，避免每秒重复注册业务定时器。

## 高风险模块

以下模块涉及资金、库存、优惠、订单状态和商户结算，改动前要先理清链路并准备回归：

- `app/common/repositories/store/order/StoreOrderCreateRepository.php`
- `app/common/repositories/store/order/StoreOrderRepository.php`
- `app/common/repositories/store/order/StoreRefundOrderRepository.php`
- `crmeb/listens/pay/`
- `crmeb/services/wechat/`
- `crmeb/services/PayService.php`
- 商品库存、活动商品、优惠券、积分、佣金相关 repository

## 当前分支说明

当前开发分支为 `v4.0`。项目中存在微信服务、小程序服务和 EasyWeChat 迁移相关痕迹；编辑微信/支付相关代码前，需要先确认当前实际引用链路。
