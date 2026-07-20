# Vercel 部署指南

本项目同时支持传统 PHP/Docker 部署和 Vercel 部署。Vercel 版本使用官方 Node.js Functions 处理接口，并使用 Private Vercel Blob 持久化订单，不依赖 Vercel Function 的临时文件系统。

## 部署前准备

你需要：

- 一个 Vercel 账户；
- 一个 LINUX DO CREDIT 应用的 Client ID 和 Client Secret；
- 本项目的 GitHub 仓库或 Fork。

## 1. 导入项目

在 Vercel Dashboard 中选择 **Add New → Project**，导入本仓库，然后保持默认构建设置。

也可以使用按钮快速导入：

[![Deploy with Vercel](https://vercel.com/button)](https://vercel.com/new/clone?repository-url=https%3A%2F%2Fgithub.com%2FRazewang%2FLINUX_EASY_CREDIT)

首次导入时可以先不部署，继续完成下面的存储和环境变量配置。

## 2. 创建私有 Blob Store

1. 打开 Vercel 项目的 **Storage** 页面。
2. 选择 **Create Database → Blob**。
3. Access 选择 **Private**。
4. 将 Blob Store 连接到当前项目，并勾选 Production 和 Preview 环境。

连接后，Vercel 会为项目提供 Blob 访问凭据。不要把凭据写入仓库。

## 3. 配置环境变量

在 **Project Settings → Environment Variables** 添加：

| 变量 | 必填 | 默认值 | 说明 |
|---|---|---|---|
| `EPAY_PID` | 是 | 无 | LINUX DO CREDIT Client ID |
| `EPAY_KEY` | 是 | 无 | LINUX DO CREDIT Client Secret |
| `EPAY_GATEWAY` | 否 | `https://credit.linux.do/epay` | 易支付兼容网关 |
| `MIN_AMOUNT` | 否 | `0.01` | 最小积分数量 |
| `MAX_AMOUNT` | 否 | `9999.99` | 最大积分数量 |

至少把必填变量配置到 Production。需要预览部署时，也应配置到 Preview。

如果修改 `MIN_AMOUNT` 或 `MAX_AMOUNT`，还需要同步调整 `assets/js/main.js` 和 `index.html` 中的前端提示与限制。

## 4. 配置 LINUX DO CREDIT 回调

部署完成后，在 LINUX DO CREDIT 应用设置中填写：

```text
异步通知地址：https://你的域名/api/notify.php
同步回调地址：https://你的域名/success.html
```

推荐使用已绑定的正式域名，避免每次 Vercel Preview URL 变化后重新设置回调。

## 5. 部署与检查

完成配置后，在 Vercel 中重新部署。部署成功后依次检查：

1. 打开首页，确认静态资源加载正常。
2. 创建一笔最小积分测试订单。
3. 完成 LINUX DO CREDIT 认证。
4. 确认返回 `success.html` 后能够显示成功状态。
5. 在 Vercel Functions Logs 中确认没有配置或 Blob 错误。

## Vercel 版本结构

```text
api/vercel/             # Node.js Vercel Functions
lib/vercel/             # 签名、配置、订单存储和响应工具
vercel.json             # 将原 .php API 地址改写到 Functions
package.json            # Vercel Blob 依赖及测试命令
```

前端仍请求 `/api/*.php`，Vercel 通过 `vercel.json` 将这些地址改写到 Node.js Functions。因此 PHP/Docker 部署方式保持不变。

当前 Vercel 版本覆盖创建订单、异步回调和订单状态查询三项核心流程。PHP 配置中的 Notion、Webhook 和数据库集成不会在 Vercel Functions 中自动启用。

## 注意事项

- 不要把订单写入 Function 的 `/tmp`：它只是临时空间，不能跨实例持久化。
- Blob Store 必须使用 Private，订单内容不应通过公开 Blob URL 暴露。
- 修改环境变量后需要重新部署，变量才会进入新的 Deployment。
- 生产回调依赖稳定域名，不建议把 Preview URL 配置为长期回调地址。
