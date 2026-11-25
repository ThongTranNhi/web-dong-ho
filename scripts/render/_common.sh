#!/usr/bin/env bash
# Hàm dùng chung cho script Render. Kết nối qua Render SSH (OpenSSH chuẩn).
#
# Cần đặt trước khi chạy (lấy trong dashboard Render → service → tab "Connect" → SSH):
#   export RENDER_SSH_WEB=srv-xxxxxxxx@ssh.singapore.render.com     # luxury-watches-web
#   export RENDER_SSH_MYSQL=srv-yyyyyyyy@ssh.singapore.render.com   # luxury-watches-mysql
# và đã thêm SSH public key vào Render (Account Settings → SSH Public Keys).
set -Eeuo pipefail
export MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL='*'

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

timestamp() { date +%Y%m%d-%H%M%S; }
die() { echo "Lỗi: $*" >&2; exit 1; }

SSH_OPTS=(-o BatchMode=yes -o ServerAliveInterval=30 -o StrictHostKeyChecking=accept-new)

need_web()   { [ -n "${RENDER_SSH_WEB:-}" ]   || die "chưa đặt RENDER_SSH_WEB (xem đầu file scripts/render/_common.sh)"; }
need_mysql() { [ -n "${RENDER_SSH_MYSQL:-}" ] || die "chưa đặt RENDER_SSH_MYSQL (xem đầu file scripts/render/_common.sh)"; }

web_ssh()   { need_web;   ssh "${SSH_OPTS[@]}" "$RENDER_SSH_WEB" "$@"; }
mysql_ssh() { need_mysql; ssh "${SSH_OPTS[@]}" "$RENDER_SSH_MYSQL" "$@"; }

# Lệnh mysql/mysqldump trong service MySQL; mật khẩu lấy từ biến môi trường của service
# (không đi qua dòng lệnh trên máy bạn, không ghi vào log).
mysql_exec() { mysql_ssh "MYSQL_PWD=\"\$MYSQL_ROOT_PASSWORD\" exec $1"; }

# WP-CLI trong service web.
wp_remote() { web_ssh "cd /var/www/html && wp --allow-root $*"; }
