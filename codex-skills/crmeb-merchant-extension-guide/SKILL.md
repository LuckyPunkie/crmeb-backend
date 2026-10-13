---
name: crmeb-merchant-extension-guide
description: 面向购买 CRMEB Merchant 多商户电商源码的客户技术团队，提供项目二开指南、架构说明、推荐扩展点、常见定制场景、风险边界、Swoole/Redis/队列/定时器排障建议，适用于客户二开、交付培训、技术支持和源码改造评估。
---

# CRMEB Merchant 客户二开指南

本 skill 用于给客户技术团队提供 CRMEB Merchant 项目的二开说明。回答时应尽量站在“客户拿到源码后如何安全扩展”的角度，强调可扩展入口、风险边界和回归验证。

## 使用原则

1. 先说明推荐扩展方式，再说明直接改核心源码的风险。
2. 订单、支付、退款、库存、积分、优惠券、佣金、商户结算等链路要特别谨慎。
3. Swoole 项目要提醒客户：修改代码后需要重启服务，常驻进程下不要长期持有连接对象或请求状态。
4. 回答客户二开问题时，优先给“入口 -> 涉及文件 -> 推荐改法 -> 验证点”的路径。
5. 不把本 skill 当成运行时代码；它是给技术人员和 AI 助手使用的二开资料。

## 参考资料

- 项目架构和目录：读取 `references/architecture.md`。
- 推荐扩展点：读取 `references/extension-points.md`。
- 常见二开场景：读取 `references/common-customizations.md`。
- 高风险边界：读取 `references/risk-boundaries.md`。
- 部署和排障：读取 `references/troubleshooting.md`。

## 默认回答结构

客户问“怎么改/怎么二开”时，优先按这个结构回答：

1. 推荐入口
2. 涉及文件
3. 推荐实现方式
4. 风险点
5. 验证方式

## 基础约定

- 当前项目实际依赖 ThinkPHP 8、ThinkORM、think-swoole 4.1。
- 主要业务分层为 Controller -> Repository -> DAO/Model。
- 服务和第三方集成多在 `crmeb/services`。
- 事件定义在 `app/event.php`，监听器在 `crmeb/listens`，队列任务在 `crmeb/jobs`。
- 控制器通常通过 `app('json')->success()`、`fail()`、`status()` 返回。
- 面向用户的业务错误优先使用 `think\exception\ValidateException`。

## 快速验证

小范围 PHP 文件改动后先跑：

```bash
php -l path/to/file.php
```

涉及配置、路由、定时器、队列、Swoole 生命周期时，提醒客户清理缓存并重启 Swoole 服务。

