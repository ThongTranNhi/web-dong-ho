#!/usr/bin/env bash
# Đổi URL trong database Render bằng WP-CLI (an toàn với dữ liệu serialized).
#   scripts/render/search-replace.sh <cũ> <mới>            # chạy thử
#   scripts/render/search-replace.sh <cũ> <mới> --apply    # sao lưu rồi thay thật
source "$(dirname "$0")/_common.sh"

old="${1:-}"; new="${2:-}"; mode="${3:-}"
[ -n "$old" ] && [ -n "$new" ] || die "dùng: scripts/render/search-replace.sh <cũ> <mới> [--apply]"
q() { printf '%q' "$1"; }

args="search-replace $(q "$old") $(q "$new") --all-tables-with-prefix --precise --skip-columns=guid --report-changed-only"
if [ "$mode" != "--apply" ]; then
    echo "Chạy thử (không đổi dữ liệu). Thêm --apply để thay thật."
    wp_remote "$args --dry-run"
    exit 0
fi

"$(dirname "$0")/db-backup.sh" "backups/render-db-before-search-replace-$(timestamp).sql.gz"
wp_remote "$args"
wp_remote "cache flush" >/dev/null 2>&1 || true
wp_remote "rewrite flush" >/dev/null 2>&1 || true
echo "Đã thay '$old' → '$new'."
