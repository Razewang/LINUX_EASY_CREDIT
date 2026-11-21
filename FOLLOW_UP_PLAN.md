# 后续更新计划（事项 3–6）

本文仅记录代码核查结果与后续建议，不修改运行行为，也不表示以下测试已经实现或执行。文件路径以仓库根目录为基准；Vercel 的 PHP 风格入口由 `vercel.json` 重写到 `api/vercel/`，并不执行 PHP。

建议顺序：先处理隐私与错误响应（4、5、6 中的查询隐私和权限），再加强订单号（6），最后对齐产品行为（3）及清理死代码（6）。P1 表示优先安全加固，P2 表示兼容性与可靠性改进，P3 表示维护清理；不是对已发生泄露的认定。

## 3. PHP 与 Vercel 的 Notion、Webhook 和订单名称差异（P2）

**现状**

- PHP：`api/notify.php:64–104` 在验签、金额及成功状态检查后，先保存已支付状态，再调用 `IntegrationHelper::sendToIntegrations()`。`api/IntegrationHelper.php` 按 `isNotionEnabled()` / `isWebhookEnabled()` 检查开关和必要配置，分别发送 Notion 与 Webhook；Webhook 有请求内重试。集成异常被记录，不改变支付成功响应。已支付订单的重复回调提前返回，不再触发集成。
- Vercel：`api/vercel/notify.js` 只验证回调并更新订单后返回成功；`lib/vercel/config.js::getConfig()` 无 Notion/Webhook 配置，当前回调链路无对应集成实现。
- PHP `api/create_order.php:59–65` 的名称为“打赏支持”，Vercel `api/vercel/create-order.js:33–39` 为“积分流转”；有留言时都追加前 20 个字符，但 PHP 用真假判断及 `mb_substr`，Node 用字符串真假判断及码点数组切片，例如留言 `"0"` 的表现也不同。

**风险**：部署方式切换会改变第三方记录和支付名称；PHP 集成失败后，后续支付回调因已支付状态直接返回，不保证补发。不能将支付成功等同于第三方记录成功。

**计划改动**：先在 `INTEGRATIONS.md`、`VERCEL.md` 明示能力矩阵；确定统一名称及 Unicode/空值规则。若补齐 Node 集成，采用显式启用配置，独立记录投递结果、幂等键与可重试任务，避免耦合支付状态；PHP 同步采用相同投递契约。具体名称和是否补齐集成需产品确认，不能以当前 PHP 行为自动作为标准。留言投递遵循第 4 项。

**验证方式（计划测试）**：两端对空留言、中文/表情、`"0"` 比较名称；模拟集成关闭、配置缺失、成功、超时及非 2xx；验证支付结果不被集成故障改变，重复回调不重复记录，失败投递可单独重试。使用本地 HTTP 替身，不连接真实 Notion/Webhook。

## 4. 签名调试日志与留言隐私（P1）

**现状**

- PHP `api/create_order.php:72–93` 无调试开关地记录支付参数、签名、待签名参数串及完整留言。第 86 行追加的是字面量 `[KEY_HIDDEN]`，不是实际密钥；真正追加密钥只在 `api/EpayHelper.php::createSign()` 内，不能据此声称日志直接写出了密钥。
- `api/notify.php:16` 在验签前记录全部回调参数（可含 `sign` 和任意输入）。`api/IntegrationHelper.php::sendToNotion()` 记录完整 Notion 响应，`sendHttpRequest()` 将非 2xx 响应正文放入错误，再由调用方记录；返回内容可能含业务数据，需避免无筛选落盘。
- 留言完整保存在 PHP 订单 JSON（`api/create_order.php:95–105`），并由 `buildNotionPageData()`、`sendToWebhook()` 发送给已启用的第三方。Webhook 可配置 GET，`sendHttpRequest()` 会把包括留言的数据拼到 URL。
- Node `api/vercel/create-order.js` 保存完整留言到 `lib/vercel/orders.js` 的私有 Blob，无同类签名调试日志；两端支付名称均包含留言片段，且返回含支付参数的跳转 URL。Node 的 `api/vercel/*.js` 异常路径仍将原始 error 传给 `console.error`，不能据此保证异常日志不含敏感信息。

**风险**：签名、留言及第三方响应进入日志后扩大可见范围；URL 中的留言片段或 GET Webhook 留言可能进入浏览器、代理及目标服务日志。私有存储不等于留言在所有出口均私密；查询接口问题见第 6 项。

**计划改动**：移除签名/原始参数调试输出，统一日志字段白名单（事件、必要订单标识、结果码），不记录密钥、签名、留言或原始响应；对未验签输入只记受控摘要并防日志换行注入。异常记录采用受控分类与关联 ID。支付名称默认不含留言；第三方留言投递需显式配置并说明用途，避免通过 GET 发送私人内容；为两端订单与日志约定保留期、清理方式及访问权限。

**验证方式（计划测试）**：用虚构密钥、签名和唯一留言标记，覆盖正常创建、空留言、错误签名回调、含换行输入及第三方失败响应，捕获 PHP 日志/Node console，断言均无敏感标记；检查支付 URL 和默认第三方请求不带留言，显式开启时仅发送允许字段。

## 5. PHP 500 响应暴露异常信息（P1）

**现状**：`api/create_order.php:116–118`、`api/query_order.php:56–58` 在 `catch (Exception $e)` 中把 `$e->getMessage()` 拼到 500 JSON。`api/EpayHelper.php::jsonResponse()` 原样输出该文本。不是所有 PHP 500 都如此：`api/notify.php:112–115` 只返回 `fail`；配置加载在这些入口的 try 外，且 `Exception` 不覆盖全部 `Throwable`，因此不能声称已有统一错误边界。Node 创建/查询接口分别在 `api/vercel/create-order.js:59–61`、`api/vercel/query-order.js:16–18` 返回通用文案，`notify.js` 返回 `fail`，`config.js` 返回通用配置错误。

**风险**：创建/查询捕获到的内部异常可能泄露存储路径、依赖细节等；具体泄露内容取决于异常，不能断言每次 500 都包含密钥。

**计划改动**：PHP 创建/查询统一返回通用错误及关联 ID，不回传异常文本；梳理配置加载和 `Throwable` 边界，保证 API 错误格式稳定；生产关闭错误正文显示，内部诊断遵循第 4 项脱敏策略，回调保持协议要求的 `fail`。Node 保持通用响应，并审查原始异常日志。

**验证方式（计划测试）**：注入带虚构路径/敏感标记的读写异常、配置缺失、坏订单 JSON 和运行时错误，断言 HTTP 状态、JSON/回调格式正确且正文无异常标记；正常与空/非法输入仍分别走成功或 400/404 分支，不能全部变成 500。

## 6. 权限、闲置 HTTP 方法、订单号及查询隐私（分批处理）

### 日志与订单目录权限（P1）

- **现状**：`Dockerfile` 与 `docker/entrypoint.sh` 都对 `logs` 递归 `chmod 777`，后者在每次启动时执行；PHP `api/EpayHelper.php::log()` / `saveOrder()` 新建目录使用 0755，文件权限受 umask 影响。`docker/default.conf` 有拒绝敏感目录的规则，但不能代替文件系统权限，也不证明其他部署方式有同样保护。Node 使用 `lib/vercel/orders.js` 的私有 Blob，无对应本地日志目录权限逻辑。
- **风险与计划改动**：同环境其他用户可能读写日志/订单；改为按运行用户最小授权，区分目录与文件权限，保留持久化卷可写能力，同时检查 Web 服务敏感路径规则及实际匹配结果。
- **验证方式（计划测试）**：在新容器、重启和挂载旧卷场景检查属主/权限；运行用户能创建、查询、回调并原子保存订单，非授权用户不能读写；HTTP 直接访问日志/订单被拒绝；只读卷失败时响应仍脱敏。Node 模拟 Blob 拒绝访问，检查通用错误而非套用 chmod 测试。

### 未被调用的 HTTP 辅助方法（P3）

- **现状**：全仓调用搜索只找到 `api/EpayHelper.php::httpPost()` / `httpGet()` 定义，没有调用点；`api/IntegrationHelper.php::sendHttpRequest()` 则确实用于 Notion/Webhook，不能一起当死代码删除。`api/query_order.php:45–46` 明确不使用会把 Client Secret 放入 URL 的上游 GET 查询；Node `api/vercel/query-order.js` 也仅读本地订单存储，无同名 HTTP 辅助方法。
- **风险与计划改动**：闲置接口增加维护误用面，不代表当前存在该网络请求或 TLS 校验缺失（两方法均启用证书验证）。确认无仓库外调用约定后删除未用方法，保留独立集成请求实现，禁止恢复 URL 携带密钥的查询。
- **验证方式（计划测试）**：删除后做全仓引用检查、PHP 语法检查及现有回归；模拟订单查询，断言不请求上游支付查询接口。

### out_trade_no 随机性（P2，先于第 3 项）

- **现状**：`api/EpayHelper.php::generateOrderNo()` 为 `RW` + 秒级日期 + 4 字节随机后缀；随机源抛异常时降级到 `uniqid`。Node `lib/vercel/epay.js::generateOrderNo()` 使用 UTC 日期及 8 字节 `randomBytes`，无弱随机回退；其异常由创建入口转为 500。两端当前均已有密码学随机源，不能描述成“纯时间戳订单号”。
- **风险与计划改动**：PHP 后缀空间较小，回退不应当作密码学随机；采用至少与 Node 一致的随机强度，随机源失败时明确失败而非降级，检查存储层碰撞保护并重试，确认上游长度/字符限制。随机订单号不能代替留言访问控制。
- **验证方式（计划测试）**：固定时间验证格式/长度和批量生成；注入随机源异常与重复订单号，验证不返回成功、不覆盖旧订单；检查正常创建、查询与回调兼容旧订单号。批量无重复不等于已证明密码学安全。

### 查询订单时的留言隐私（P1，与第 4 项联动）

- **现状**：PHP `api/query_order.php:24–54` 仅凭合法且存在的 `order_no` 即返回完整 `message`，没有额外身份校验；Node `api/vercel/query-order.js::GET()` 调用 `lib/vercel/http.js::publicOrder()`，同样返回留言，无额外身份校验。私有 Blob 只保护直接存储访问，不阻止该 API 输出。
- **风险与计划改动**：获知订单号的人可读取留言；默认公共查询移除 `message`，只返回支付状态所需字段。如必须展示留言，另行设计绑定用户或独立查询凭证，不把订单号本身当授权。梳理前端及 API 文档兼容性；PHP 增加与 Node `jsonResponse` 一致的禁止缓存策略。
- **验证方式（计划测试）**：两端对有留言/空留言订单都不在公共响应暴露该字段；覆盖缺失、非法、不存在订单及存储异常，检查状态码和 no-store；若引入私有查询，增加无凭证、错误凭证、跨订单凭证和正确凭证用例。

## 后续实施的检查入口

现有 `npm test`（`node --test`）覆盖 `test/config.test.js`、`test/epay.test.js` 及 PHP 持久化桥接测试；PHP 用例为 `test/php/order-persistence.test.php`。上述计划中的日志、集成、权限和查询隐私用例尚需补充，不能用现有测试全绿代替。PHP 语法检查沿用 `.github/workflows/ci.yml` 的 `find . -name '*.php' -print0 | xargs -0 -n1 php -l`；容器权限改动另做容器验证。测试均使用虚构数据及本地替身，不使用生产密钥或真实留言。
