# Deploy Luxury Watches lên Render

**Render là phương án triển khai đang được chọn.** Tài liệu Railway cũ
([RAILWAY_DEPLOY.md](RAILWAY_DEPLOY.md)) chỉ còn để tham khảo. Chạy local bằng Docker:
[DOCKER_DEPLOY.md](DOCKER_DEPLOY.md).

## Mục lục

1. [Kiến trúc](#1-kiến-trúc)
2. [Chi phí và giới hạn](#2-chi-phí-và-giới-hạn)
3. [Biến môi trường](#3-biến-môi-trường)
4. [Deploy bằng Blueprint](#4-deploy-bằng-blueprint)
5. [Kết nối SSH để vận hành](#5-kết-nối-ssh-để-vận-hành)
6. [Đưa database lên](#6-đưa-database-lên)
7. [Uploads](#7-uploads)
8. [Domain riêng](#8-domain-riêng)
9. [SePay webhook](#9-sepay-webhook)
10. [Backup và restore](#10-backup-và-restore)
11. [Rollback](#11-rollback)
12. [Kiểm tra sau deploy](#12-kiểm-tra-sau-deploy)
13. [Lỗi thường gặp](#13-lỗi-thường-gặp)

---

## 1. Kiến trúc

```
                    HTTPS (*.onrender.com hoặc domain riêng)
 trình duyệt ──────────────► Render proxy ──HTTP + X-Forwarded-Proto──┐
                                                                      ▼
                         ┌────────────────────────────────────────────────┐
                         │ luxury-watches-web   (Web Service, Docker)     │
                         │  Apache + PHP 8.3, WordPress, WooCommerce,     │
                         │  theme/plugin dự án — lắng nghe $PORT          │
                         │  Disk "wp-uploads" → /var/www/html/wp-content/ │
                         │                       uploads                  │
                         └──────────────────────┬─────────────────────────┘
                                                │ private network, cổng 3306
                         ┌──────────────────────┴─────────────────────────┐
                         │ luxury-watches-mysql (Private Service, Docker) │
                         │  MySQL 8.4 — không có địa chỉ public           │
                         │  Disk "mysql-data" → /var/lib/mysql            │
                         │  (dữ liệu trong /var/lib/mysql/data)           │
                         └────────────────────────────────────────────────┘
```

| Service | Loại | Image | Disk |
|---|---|---|---|
| `luxury-watches-web` | Web Service, gói Starter, vùng Singapore | `Dockerfile` ở thư mục gốc | `wp-uploads`, 2 GB, `/var/www/html/wp-content/uploads` |
| `luxury-watches-mysql` | Private Service, gói Starter, vùng Singapore | `docker/mysql/Dockerfile` (mysql:8.4.13 + `render.cnf`) | `mysql-data`, 2 GB, `/var/lib/mysql` |

Toàn bộ khai báo nằm trong [`render.yaml`](../render.yaml).

- **HTTPS**: Render cấp chứng chỉ và kết thúc TLS ở proxy. `wp-config` đọc
  `X-Forwarded-Proto: https` để WordPress hiểu kết nối là HTTPS, bật `FORCE_SSL_ADMIN`,
  không lặp chuyển hướng.
- **Địa chỉ site**: `WP_HOME`/`WP_SITEURL` nếu đặt; nếu không, dùng `RENDER_EXTERNAL_URL`
  (Render tự cấp, dạng `https://luxury-watches-web.onrender.com`). Header `Host` không
  bao giờ được dùng khi đã có một trong hai.
- **Bảo mật**: không hiển thị lỗi PHP ở production, mã nguồn chỉ đọc, chặn cài/sửa plugin
  qua admin, khóa bảo mật sinh một lần và giữ nguyên giữa các lần deploy, table prefix được
  làm sạch.
- **Build lại có chọn lọc**: commit chỉ sửa `docs/`, `scripts/`, `*.md` không build lại web;
  service MySQL chỉ build lại khi `docker/mysql/` thay đổi.

## 2. Chi phí và giới hạn

Ước tính theo bảng giá Render (kiểm tra lại tại https://render.com/pricing trước khi tạo):

| Hạng mục | Giá | Ghi chú |
|---|---|---|
| Web Service Starter (0,5 CPU, 512 MB) | $7/tháng | Gói Free không gắn được disk |
| Private Service Starter (0,5 CPU, 512 MB) | $7/tháng | Private Service không có gói Free |
| Disk `wp-uploads` 2 GB | $0,50/tháng | $0,25/GB/tháng, tính theo giây |
| Disk `mysql-data` 2 GB | $0,50/tháng | |
| **Tổng** | **≈ $15/tháng** | Chưa gồm băng thông vượt hạn mức gói workspace |

Giới hạn cần biết:

- Service có disk **không deploy zero-downtime**: mỗi lần deploy/restart site gián đoạn
  khoảng vài chục giây đến vài phút.
- Disk chỉ gắn vào **một instance**: không scale ngang web service. Cần nhiều instance thì
  chuyển uploads sang object storage (S3, Cloudflare R2) bằng plugin offload.
- Disk **tăng được, không giảm được** dung lượng.
- Disk chỉ truy cập được khi service đang chạy (không đọc được lúc build).
- Snapshot disk của Render **không** phải backup database an toàn: Render khuyến cáo không
  khôi phục database bằng snapshot (có thể hỏng dữ liệu). Dùng mysqldump (mục 10).
- MySQL trên 512 MB RAM: cấu hình đã giới hạn bộ nhớ (`docker/mysql/render.cnf`); site đông
  khách thì nâng gói Private Service.

## 3. Biến môi trường

Không đặt giá trị bí mật trong repository. Cột "Nguồn" cho biết giá trị đến từ đâu.

**`luxury-watches-mysql`**

| Biến | Nguồn | Ghi chú |
|---|---|---|
| `MYSQL_DATABASE` | `render.yaml`: `luxury_watches` | |
| `MYSQL_USER` | `render.yaml`: `wordpress` | |
| `MYSQL_PASSWORD` | Render tự sinh | Chỉ có tác dụng lần khởi tạo disk đầu tiên |
| `MYSQL_ROOT_PASSWORD` | Render tự sinh | Như trên |

**`luxury-watches-web`**

| Biến | Nguồn | Ghi chú |
|---|---|---|
| `WORDPRESS_DB_HOST` | tham chiếu host của `luxury-watches-mysql` | Private network |
| `WORDPRESS_DB_PORT` | `render.yaml`: `3306` | |
| `WORDPRESS_DB_NAME` / `_USER` / `_PASSWORD` | tham chiếu `MYSQL_*` của service MySQL | |
| `WP_ENVIRONMENT_TYPE` | `render.yaml`: `production` | |
| `WP_DEBUG` | `render.yaml`: `false` | Lỗi chỉ ghi vào log, không hiển thị |
| `WORDPRESS_AUTH_KEY` … `WORDPRESS_NONCE_SALT` (8 biến) | Render tự sinh | Đổi giá trị sẽ đăng xuất mọi người |
| `GEMINI_API_KEY` | **nhập tay** khi tạo Blueprint | Key của chatbot |
| `GOOGLE_CLIENT_ID` | **nhập tay** (để trống nếu chưa dùng) | Đăng nhập Google |
| `WP_HOME`, `WP_SITEURL` | **thêm tay khi gắn domain riêng** | Mặc định dùng `RENDER_EXTERNAL_URL` |
| `LW_API_ALLOWED_ORIGINS` | tùy chọn | CORS cho API `luxury/v1` |
| `PORT`, `RENDER_EXTERNAL_URL` | Render tự cấp | Không đặt tay |

Danh sách đầy đủ biến image hỗ trợ: [DOCKER_DEPLOY.md, mục 12](DOCKER_DEPLOY.md#12-biến-môi-trường).

## 4. Deploy bằng Blueprint

1. Đẩy code (gồm `render.yaml`) lên GitHub, ở nhánh sẽ deploy.
2. Render Dashboard → **New → Blueprint** → kết nối GitHub, chọn repository
   `ThongTranNhi/web-dong-ho` và nhánh.
3. Render đọc `render.yaml`, liệt kê 2 service, 2 disk và hỏi các biến `sync: false`:
   nhập `GEMINI_API_KEY`; `GOOGLE_CLIENT_ID` để trống nếu chưa có.
4. Xem lại chi phí hiển thị → **Apply**. (Bước này yêu cầu phương thức thanh toán.)
5. Chờ `luxury-watches-mysql` chạy trước (log có `ready for connections`), sau đó
   `luxury-watches-web` build xong và healthcheck `/healthz.php` đạt.
6. Mở `https://luxury-watches-web.onrender.com` (tên thật xem trên dashboard):
   - chưa import database → trình cài đặt WordPress; **chưa cài vội** nếu sẽ import (mục 6).

Mỗi lần push lên nhánh đã chọn, Render tự build và deploy lại (`autoDeploy: true`).

## 5. Kết nối SSH để vận hành

Các thao tác database/uploads dùng Render SSH (OpenSSH chuẩn), không cần mở MySQL ra Internet.

1. Render → **Account Settings → SSH Public Keys** → thêm public key của máy bạn
   (`ssh-keygen -t ed25519` nếu chưa có, nội dung `~/.ssh/id_ed25519.pub`).
2. Mỗi service → tab **Connect → SSH**, lấy địa chỉ dạng `srv-xxxx@ssh.singapore.render.com`.
3. Trong Git Bash:
   ```bash
   export RENDER_SSH_WEB=srv-xxxx@ssh.singapore.render.com
   export RENDER_SSH_MYSQL=srv-yyyy@ssh.singapore.render.com
   ssh "$RENDER_SSH_WEB" 'curl -s localhost:$PORT/healthz.php'   # thử kết nối
   ```

SSH cần service đang chạy ở gói trả phí.

## 6. Đưa database lên

Luôn import **bản sao**, không thao tác trên database XAMPP gốc.

1. Sao lưu local (đã có sẵn trong `D:\webdongho-backups` nếu làm theo quy trình migration),
   hoặc tạo mới:
   ```powershell
   D:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --routines --triggers --default-character-set=utf8mb4 luxury_watches > D:\webdongho-backups\luxury_watches.sql
   ```
2. Import lên Render (database Render đang trống):
   ```bash
   scripts/render/db-import.sh /d/webdongho-backups/luxury_watches-YYYYMMDD-HHMMSS.sql.gz
   ```
   Database Render đã có dữ liệu → thêm `--replace` (script tự sao lưu bản trên Render trước).
3. Đổi URL bằng WP-CLI (xử lý đúng dữ liệu serialized; **không** thay chuỗi trong file SQL):
   ```bash
   scripts/render/search-replace.sh http://localhost:8000 https://luxury-watches-web.onrender.com           # chạy thử
   scripts/render/search-replace.sh http://localhost:8000 https://luxury-watches-web.onrender.com --apply   # thay thật
   ```
4. Đăng nhập `/wp-admin` bằng tài khoản cũ; vào **Cài đặt → Đường dẫn tĩnh** bấm **Lưu**.

Database từ MariaDB 10.4 (XAMPP) đã được kiểm tra: toàn bảng InnoDB, collation
`utf8mb4_unicode_520_ci`, không dùng cú pháp riêng của MariaDB.

## 7. Uploads

- Media của dự án (`wp-content/uploads/2026`) nằm trong image và **tự chép vào disk** ở lần
  khởi động đầu (chỉ chép file còn thiếu, không ghi đè, không xóa).
- Ảnh khác trên máy (ngoài `2026`) hoặc bản sao lưu:
  ```bash
  scripts/render/uploads-restore.sh wordpress/wp-content/uploads
  scripts/render/uploads-restore.sh backups/render-uploads-20260101-000000.tar.gz
  ```
- Tải uploads về máy: `scripts/render/uploads-backup.sh`.

Chỉ dữ liệu dưới `/var/www/html/wp-content/uploads` được giữ qua deploy/restart; mọi thay
đổi file khác trong container mất khi deploy lại.

## 8. Domain riêng

1. `luxury-watches-web` → **Settings → Custom Domains → Add**, tạo bản ghi DNS theo hướng
   dẫn (CNAME tới `luxury-watches-web.onrender.com`, hoặc A/ALIAS cho domain gốc). Render tự
   cấp chứng chỉ TLS.
2. **Environment** → thêm `WP_HOME=https://domain-cua-ban` và `WP_SITEURL=https://domain-cua-ban`
   → lưu (service tự deploy lại).
3. Đổi URL trong database:
   `scripts/render/search-replace.sh https://luxury-watches-web.onrender.com https://domain-cua-ban --apply`
4. Cập nhật webhook SePay (mục 9) và Authorized JavaScript origins của Google OAuth.

## 9. SePay webhook

Cấu hình SePay (MBBank, số tài khoản, chủ tài khoản, tiền tố `DH`, API key) nằm trong
database và đi theo khi import; không đặt trong biến môi trường hay repository.

1. Sau khi site chạy HTTPS, đăng nhập WP Admin → **WooCommerce → Cài đặt → Thanh toán → SePay**,
   kiểm tra thông tin và xem **API Key** (không chép key vào tài liệu, chat, ticket).
2. my.sepay.vn → **Webhooks** → sửa/thêm webhook:
   - URL: `https://<domain>/wp-json/sepay-gateway/v1/add-payment`
   - Chứng thực: **API Key**, giá trị là API Key ở bước 1 (SePay gửi
     `Authorization: Apikey <key>`; Apache đã chuyển tiếp header này cho PHP).
   - Tiền tố mã thanh toán trên SePay phải là `DH` (khớp WordPress).
3. Dùng chức năng gửi thử của SePay, hoặc giả lập (không chuyển tiền):
   ```bash
   # Không có key → phải bị từ chối (401/403)
   curl -s -o /dev/null -w '%{http_code}\n' -X POST https://<domain>/wp-json/sepay-gateway/v1/add-payment
   ```
   Thử với key đúng chỉ trên đơn test và số tiền giả lập, theo tài liệu payload của SePay.
4. Giao dịch thật chỉ thực hiện khi chủ shop đồng ý, giá trị nhỏ, đơn hàng test có tên nhận diện.

Đổi domain thì phải sửa lại URL webhook.

## 10. Backup và restore

Snapshot disk của Render không đủ cho database. Quy trình chính là **mysqldump ra ngoài Render**.

### Backup thủ công

```bash
scripts/render/db-backup.sh          # → backups/render-db-<thời gian>.sql.gz (kiểm tra dump hoàn chỉnh)
scripts/render/uploads-backup.sh     # → backups/render-uploads-<thời gian>.tar.gz
```

`backups/` bị Git bỏ qua. Chép bản sao lưu sang nơi khác (Google Drive, ổ ngoài…), giữ ít
nhất 7 bản hằng ngày và 4 bản hằng tuần.

### Backup định kỳ (máy Windows của bạn)

Task Scheduler → **Create Task**:

- Trigger: hằng ngày, ví dụ 02:00.
- Action: `C:\Program Files\Git\bin\bash.exe` với tham số
  `-lc "export RENDER_SSH_MYSQL=srv-yyyy@ssh.singapore.render.com; cd /d/webdongho && scripts/render/db-backup.sh"`
- Điều kiện: chỉ chạy khi có mạng; máy phải bật.

Phương án không phụ thuộc máy cá nhân: Render **Cron Job** (tính phí theo thời gian chạy)
chạy mysqldump và đẩy lên object storage (S3/R2) bằng khóa lưu trong Render Secret – cần
thêm tài khoản lưu trữ, chưa cấu hình sẵn.

### Restore

```bash
scripts/render/db-import.sh backups/render-db-20260101-020000.sql.gz --replace   # tự sao lưu bản hiện tại trước
scripts/render/uploads-restore.sh backups/render-uploads-20260101-020000.tar.gz
```

Restore về domain khác thì chạy thêm search-replace (mục 6).

## 11. Rollback

- **Code**: service → **Events / Deploys** → chọn deploy cũ → **Rollback**. Image cũ chạy lại
  với cùng disk và biến môi trường. Hoặc `git revert` rồi push.
- **Database**: không có rollback tự động. Restore từ bản mysqldump gần nhất (mục 10). Trước
  thay đổi lớn (cập nhật WooCommerce, import), luôn chạy `scripts/render/db-backup.sh`.
- **Nâng WooCommerce/WordPress**: đổi phiên bản trong `Dockerfile`; rollback code về phiên
  bản cũ **sau khi** database đã được nâng cấp schema có thể không tương thích – restore
  database đi kèm.

## 12. Kiểm tra sau deploy

- `https://<domain>/healthz.php` → `{"status":"ok"}`
- Trang chủ, `/shop/`, một trang sản phẩm, `/phien-dau-gia/`, `/my-account/`
- `/wp-admin/`, `/quan-tri-dau-gia/` (yêu cầu đăng nhập)
- Chatbot: `GET /wp-json/luxury-chat/v1/status`
- Không có mixed content: Console trình duyệt không báo "Mixed Content"
- Restart web (Manual Deploy → Restart) → ảnh vẫn còn; restart MySQL → dữ liệu vẫn còn

## 13. Lỗi thường gặp

| Triệu chứng | Xử lý |
|---|---|
| Healthcheck thất bại, log "database_unavailable" | Service MySQL chưa sẵn sàng hoặc biến `WORDPRESS_DB_*` sai. Xem log MySQL; kiểm tra tên service trong `fromService` khớp tên thật. |
| MySQL không khởi động, log "data directory has files in it" | Disk phải gắn ở `/var/lib/mysql`, image dùng datadir `/var/lib/mysql/data` (đúng theo `docker/mysql`). Không đổi mount path sau khi có dữ liệu. |
| Đổi `MYSQL_PASSWORD` trên dashboard nhưng WordPress không kết nối được | Mật khẩu chỉ áp dụng khi khởi tạo disk. Đổi trong MySQL bằng `ALTER USER` qua SSH, rồi cập nhật biến. |
| Site chuyển hướng về `localhost:8000` | Chưa search-replace sau import (mục 6). |
| Vòng lặp chuyển hướng HTTPS | Thêm `WORDPRESS_FORCE_SSL_ADMIN=false`, báo lỗi proxy. |
| Upload báo không ghi được | Kiểm tra disk `wp-uploads` đã gắn đúng `/var/www/html/wp-content/uploads`; log entrypoint. |
| Site gián đoạn mỗi lần deploy | Bình thường khi có disk (không zero-downtime). Deploy giờ vắng khách. |
| Chatbot báo lỗi provider 503/timeout | Lỗi phía Gemini; tích hợp vẫn đúng nếu route `status` trả 200 và frontend báo lỗi thân thiện. |
| `ssh: Permission denied (publickey)` | Chưa thêm SSH key vào Render hoặc service ở gói Free. |
