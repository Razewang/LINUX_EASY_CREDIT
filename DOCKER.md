# Docker 部署指南

使用 Docker 快速部署打赏网站项目。

---

## 📦 前置要求

- 已安装 Docker 和 Docker Compose
- 如未安装，参考：https://docs.docker.com/engine/install/

---

## 🚀 快速部署

### 1. 配置 API 密钥

```bash
# 进入项目目录
cd /home/paygo/reward-website

# 复制配置文件
cp config/config.example.php config/config.php

# 编辑配置
nano config/config.php
```

**修改以下配置**：

```php
'epay' => [
    'pid' => 'YOUR_CLIENT_ID',              // Linux.do Credit Client ID
    'key' => 'YOUR_CLIENT_SECRET',          // Linux.do Credit Client Secret
    'notify_url' => 'https://tip.yourdomain.com/api/notify.php',
    'return_url' => 'https://tip.yourdomain.com/success.html',
],
```

**获取 API 密钥**：访问 https://credit.linux.do → 控制台 → 应用管理 → 创建应用

### 2. 启动容器

```bash
# 构建并启动
docker compose up -d

# 查看启动状态
docker compose ps
```

### 3. 访问网站

浏览器访问：`http://your-server-ip/index.html`

---

## 📊 查看日志

### 容器日志

```bash
# 查看所有日志
docker compose logs

# 实时查看日志
docker compose logs -f

# 查看最近 100 行
docker compose logs --tail=100

# 查看指定时间后的日志
docker compose logs --since 30m
```

### 应用日志

```bash
# 查看当天应用日志
docker compose exec web tail -f /var/www/html/logs/$(date +%Y-%m-%d).log

# 查看 Nginx 错误日志
docker compose exec web tail -f /var/log/nginx/error.log

# 查看 Nginx 访问日志
docker compose exec web tail -f /var/log/nginx/access.log
```

### 在宿主机查看日志

```bash
# 应用日志（已挂载到宿主机）
tail -f logs/$(date +%Y-%m-%d).log

# 订单日志
ls -la logs/orders/
```

---

## 🔧 常用管理命令

### 基础操作

```bash
# 启动服务
docker compose up -d

# 停止服务
docker compose down

# 重启服务
docker compose restart

# 停止服务（保留容器）
docker compose stop

# 启动已停止的服务
docker compose start
```

### 查看状态

```bash
# 查看容器状态
docker compose ps

# 查看资源使用
docker stats reward-website

# 查看容器详细信息
docker inspect reward-website
```

### 容器操作

```bash
# 进入容器（交互式）
docker compose exec web sh

# 在容器内执行命令
docker compose exec web php --version
docker compose exec web nginx -t

# 查看容器内进程
docker compose exec web ps aux
```

### 更新和重建

```bash
# 重新构建镜像
docker compose build

# 重新构建并启动
docker compose up -d --build

# 拉取最新镜像（如果使用远程镜像）
docker compose pull
```

---

## ⚙️ 配置说明

### 修改端口

编辑 `.env` 文件或直接修改 `docker-compose.yml`：

```bash
# 方法 1：创建 .env 文件
cp .env.example .env
nano .env
```

```env
WEB_PORT=8080  # 修改为其他端口
```

```bash
# 方法 2：直接修改 docker-compose.yml
nano docker-compose.yml
```

```yaml
ports:
  - "8080:80"  # 宿主机端口:容器端口
```

重启生效：

```bash
docker compose down
docker compose up -d
```

### 配置 HTTPS（推荐）

#### 方法 1：使用 Nginx 反向代理（推荐）

在宿主机配置 Nginx + Certbot：

```bash
# 安装 Certbot
sudo apt install -y certbot python3-certbot-nginx

# 申请证书
sudo certbot --nginx -d tip.yourdomain.com
```

宿主机 Nginx 配置：

```nginx
server {
    listen 80;
    server_name tip.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name tip.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/tip.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tip.yourdomain.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;  # Docker 容器端口
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

修改 `docker-compose.yml` 端口绑定：

```yaml
ports:
  - "127.0.0.1:8080:80"  # 仅监听本地
```

#### 方法 2：挂载证书到容器

```yaml
volumes:
  - /etc/letsencrypt/live/tip.yourdomain.com:/etc/nginx/ssl:ro
ports:
  - "443:443"
```

---

## 🐛 常见问题

### 1. 端口被占用

```bash
# 查看端口占用
sudo netstat -tulpn | grep :80

# 修改 .env 中的端口
WEB_PORT=8080
```

### 2. 配置修改不生效

```bash
# 重启容器
docker compose restart

# 或重新构建
docker compose up -d --build
```

### 3. 日志文件权限问题

```bash
# 修复权限
chmod -R 777 logs/
docker compose restart
```

### 4. 查看详细错误

```bash
# 查看容器启动日志
docker compose logs -f

# 进入容器检查
docker compose exec web sh
ps aux | grep php
ps aux | grep nginx
```

### 5. 清理未使用的资源

```bash
# 清理停止的容器、未使用的网络和镜像
docker system prune

# 清理所有未使用的资源（包括卷）
docker system prune -a --volumes
```

---

## 📋 快速命令参考

```bash
# 部署
docker compose up -d

# 停止
docker compose down

# 重启
docker compose restart

# 日志
docker compose logs -f

# 进入容器
docker compose exec web sh

# 状态
docker compose ps

# 重建
docker compose up -d --build
```

---

## 📚 相关文档

- [README.md](README.md) - 项目介绍
- [DEPLOYMENT.md](DEPLOYMENT.md) - 其他部署方式
- [API.md](API.md) - API 接口文档

---

**文档版本**: v2.0.1
**最后更新**: 2026-07-09
**Docker 版本**: 20.10+
**Docker Compose 版本**: 2.0+
