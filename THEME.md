# UI 主题配置指南

本项目采用 Linux.do Credit 原版暗色主题设计，并支持通过 CSS 变量轻松自定义。

## 🎨 当前主题

**风格**：Linux.do Credit 官方暗色主题
- 纯黑背景 (#0a0a0a)
- 深灰卡片 (#1a1a1a)
- 圆角设计 (12-16px)
- 简洁现代

## 📝 自定义颜色

所有颜色定义在 `assets/css/style.css` 文件的 `:root` 部分。

### 修改方法

编辑 `assets/css/style.css`，找到以下部分并修改颜色值：

```css
:root {
    /* ===== 背景色 ===== */
    --bg-primary: #0a0a0a;           /* 主背景 - 纯黑 */
    --bg-secondary: #1a1a1a;         /* 卡片背景 - 深灰 */
    --bg-tertiary: #252525;          /* 输入框背景 */
    --bg-hover: #2a2a2a;             /* 悬停状态 */

    /* ===== 文字颜色 ===== */
    --text-primary: #ffffff;         /* 主要文字 - 白色 */
    --text-secondary: #a0a0a0;       /* 次要文字 - 浅灰 */
    --text-muted: #666666;           /* 辅助文字 - 中灰 */

    /* ===== 强调色 ===== */
    --accent-primary: #f5f5f5;       /* 主按钮背景 - 浅色 */
    --accent-text: #0a0a0a;          /* 主按钮文字 - 深色 */
    --accent-red: #ef4444;           /* 必填标记 - 红色 */

    /* ===== 边框 ===== */
    --border-color: #333333;         /* 边框颜色 */
    --border-radius: 12px;           /* 统一圆角 */
    --border-radius-lg: 16px;        /* 大圆角 */
}
```

## 🌈 预设主题方案

### 1. 原版暗色主题（当前）

```css
--bg-primary: #0a0a0a;
--bg-secondary: #1a1a1a;
--text-primary: #ffffff;
--accent-primary: #f5f5f5;
```

### 2. 蓝色强调主题

```css
--bg-primary: #0a0a0f;
--bg-secondary: #1a1a2e;
--text-primary: #ffffff;
--accent-primary: #4f46e5;
--accent-text: #ffffff;
```

### 3. 绿色活力主题

```css
--bg-primary: #0a0f0a;
--bg-secondary: #1a2e1a;
--text-primary: #ffffff;
--accent-primary: #22c55e;
--accent-text: #0a0a0a;
```

### 4. 紫色优雅主题

```css
--bg-primary: #0f0a0f;
--bg-secondary: #2e1a2e;
--text-primary: #ffffff;
--accent-primary: #a855f7;
--accent-text: #ffffff;
```

## 🔧 自定义元素

### 修改标题和图标

编辑 `index.html`，找到以下部分：

```html
<div class="header">
    <div class="header-icon">📋</div>
    <h1>积分流转信息</h1>
    <p>请仔细填写并核对接收方的信息和要转移的积分数量</p>
</div>
```

可以修改：
- `header-icon` 中的 emoji 图标
- `h1` 标题文字
- `p` 描述文字

### 修改预设金额

编辑 `config/config.php`：

```php
'preset_amounts' => [1, 5, 10, 20, 50, 100],  // 修改为您需要的金额
```

### 修改圆角大小

编辑 CSS 变量：

```css
--border-radius: 12px;      /* 小圆角（按钮、输入框） */
--border-radius-lg: 16px;   /* 大圆角（卡片） */
```

## 📱 响应式断点

移动端适配断点为 `640px`，可在 CSS 底部修改：

```css
@media (max-width: 640px) {
    /* 移动端样式 */
}
```

## 🎯 常见自定义场景

### 场景 1：更换主按钮颜色

想要蓝色主按钮：

```css
--accent-primary: #3b82f6;  /* 蓝色 */
--accent-text: #ffffff;      /* 白色文字 */
```

### 场景 2：增加卡片对比度

想要更明显的卡片效果：

```css
--bg-primary: #000000;      /* 纯黑背景 */
--bg-secondary: #2a2a2a;    /* 更亮的卡片 */
```

### 场景 3：调整文字对比度

想要更柔和的文字：

```css
--text-primary: #e5e5e5;     /* 稍暗的白色 */
--text-secondary: #999999;   /* 更亮的灰色 */
```

### 场景 4：修改输入框样式

想要更突出的输入框：

```css
--bg-tertiary: #2f2f2f;     /* 更亮的输入框背景 */
--border-color: #444444;     /* 更明显的边框 */
```

## 💡 最佳实践

1. **保持对比度**：确保文字与背景有足够对比度
2. **统一风格**：所有圆角、间距保持一致
3. **测试响应式**：修改后在手机上测试效果
4. **备份原文件**：修改前备份原始 CSS 文件

## 🔄 恢复默认主题

如果想恢复原始主题，可以从 Git 历史恢复：

```bash
git checkout assets/css/style.css
```

或重新从项目仓库下载 `style.css` 文件。

## 📚 CSS 变量优势

使用 CSS 变量的好处：
- ✅ 一次修改，全站生效
- ✅ 易于维护和管理
- ✅ 可以动态切换主题（JavaScript）
- ✅ 代码更清晰易读

## 🎨 创建您自己的主题

1. 复制 `style.css` 为 `style-custom.css`
2. 修改自定义文件中的颜色变量
3. 在 HTML 中引用自定义文件：
   ```html
   <link rel="stylesheet" href="./assets/css/style-custom.css">
   ```

## 📧 技术支持

如需帮助自定义主题，请查看：
- CSS 变量文档：https://developer.mozilla.org/zh-CN/docs/Web/CSS/Using_CSS_custom_properties
- 颜色选择器：https://colorhunt.co
- 渐变生成器：https://cssgradient.io

---

**提示**：修改 CSS 后，记得清除浏览器缓存（Ctrl+F5）查看效果！
