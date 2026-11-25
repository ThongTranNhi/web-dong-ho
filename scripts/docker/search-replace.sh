#!/usr/bin/env bash
# Thay URL trong database bằng WP-CLI (an toàn với dữ liệu serialized của WordPress).
#   scripts/docker/search-replace.sh <cũ> <mới>           # chạy thử, chỉ báo cáo
#   scripts/docker/search-replace.sh <cũ> <mới> --apply   # sao lưu rồi thay thật
#
# Ví dụ: scripts/docker/search-replace.sh http://localhost/luxury-watches/wordpress http://localhost:8000 --apply
source "$(dirname "$0")/_common.sh"

old="${1:-}"; new="${2:-}"; mode="${3:-}"
[ -n "$old" ] && [ -n "$new" ] || die "dùng: scripts/docker/search-replace.sh <cũ> <mới> [--apply]"
require_running wordpress

args=(search-replace "$old" "$new" --all-tables-with-prefix --precise --skip-columns=guid --report-changed-only)

if [ "$mode" != "--apply" ]; then
    echo "Chạy thử (không thay đổi dữ liệu). Thêm --apply để thay thật."
    "${COMPOSE[@]}" exec -T --user www-data wordpress wp "${args[@]}" --dry-run
    exit 0
fi

"$(dirname "$0")/db-export.sh" "backups/db-before-search-replace-$(timestamp).sql.gz"
"${COMPOSE[@]}" exec -T --user www-data wordpress wp "${args[@]}"
"${COMPOSE[@]}" exec -T --user www-data wordpress wp cache flush >/dev/null 2>&1 || true
echo "Đã thay '$old' → '$new'."
