# 使用官方 PHP-FPM 镜像作为基础镜像
FROM php:8.3-fpm-alpine

# 设置维护者信息
LABEL maintainer="Reward Website Project"
LABEL description="Linux.do Credit Reward Website"
LABEL version="2.0.1"

# 设置工作目录
WORKDIR /var/www/html

# 安装系统依赖和 PHP 扩展
RUN apk add --no-cache \
    nginx \
    curl \
    curl-dev \
    oniguruma-dev \
    $PHPIZE_DEPS \
    bash \
    && docker-php-ext-install opcache curl mbstring

# 复制项目文件
COPY . /var/www/html/

# 复制 Nginx 配置
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/http.d/default.conf

# 创建必要的目录
RUN mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/logs/orders \
    && mkdir -p /run/nginx \
    && mkdir -p /var/log/nginx

# 设置权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/logs

# 复制启动脚本
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# 暴露端口
EXPOSE 80

# 健康检查
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/index.html || exit 1

# 启动脚本
ENTRYPOINT ["/entrypoint.sh"]
