#!/usr/bin/env bash
# Hàm dùng chung cho các script Docker. Chạy từ bất kỳ đâu trong repo.
set -Eeuo pipefail

# Git Bash trên Windows tự đổi /var/... thành C:/Program Files/Git/var/...: tắt hành vi này.
export MSYS_NO_PATHCONV=1 MSYS2_ARG_CONV_EXCL='*'

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

# Có thể truyền thêm tham số cho docker compose, vd: COMPOSE_ARGS="--env-file .env.staging"
# shellcheck disable=SC2206
COMPOSE=(docker compose ${COMPOSE_ARGS:-})

timestamp() { date +%Y%m%d-%H%M%S; }
die() { echo "Lỗi: $*" >&2; exit 1; }

require_running() {
    local service="$1"
    "${COMPOSE[@]}" ps --status running --services 2>/dev/null | grep -qx "$service" \
        || die "service '$service' chưa chạy. Chạy: docker compose up -d"
}

# Chạy lệnh mysql/mysqldump trong container db, mật khẩu lấy từ biến môi trường của container
# (không xuất hiện trên dòng lệnh hay log).
db_exec() {
    "${COMPOSE[@]}" exec -T db sh -c "MYSQL_PWD=\"\$MYSQL_ROOT_PASSWORD\" exec $1"
}
