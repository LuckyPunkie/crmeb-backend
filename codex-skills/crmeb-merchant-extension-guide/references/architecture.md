# 项目架构和目录

CRMEB Merchant 是多商户电商源码项目，支持平台后台、商户后台、用户端 API、PC 端、商品、订单、支付、退款、配送、优惠券、积分、佣金、商户结算、微信/支付宝、队列和定时任务。

## 核心目录

- `app/controller/admin`：平台后台控制器
- `app/controller/merchant`：商户后台控制器
- `app/controller/api`：用户端 API 控制器
- `app/common/repositories`：业务编排层，二开时最常接触
- `app/common/dao`：DAO 层，封装模型查询和基础写入
- `app/common/model`：ThinkORM 模型、关联、搜索器
- `app/validate`：请求验证类
- `crmeb/services`：业务服务、第三方服务、支付/微信/短信/上传等
- `crmeb/listens`：事件监听器，包含定时任务监听
- `crmeb/jobs`：队列任务
- `config`：框架、数据库、Redis、Swoole、微信等配置
- `route`：路由定义

## 典型请求链路

```text
route
-> controller
-> validate
-> repository
-> dao/model
-> service/job/listener
-> json response
```

## 二开判断方式

客户新增功能时，先判断属于哪类：

- 新接口：优先新增 controller + repository 方法。
- 新业务规则：优先在 repository 或监听器扩展。
- 异步处理：优先新增 job。
- 支付后处理：优先挂支付成功事件监听。
- 定时处理：优先新增 listener，并通过统一定时器注册。
- 第三方接口：优先放到 `crmeb/services`。

## 运行时特点

项目通过 Swoole 常驻进程运行。客户二开时要注意：

- 代码改动后通常需要重启 Swoole。
- 不要把请求状态长期保存在服务对象属性中。
- Redis/PDO 连接要从当前上下文获取，不要缓存原生连接 handler。

