# Chạy và deploy Luxury Watches bằng Docker

> Triển khai production: **Render** là phương án đang được chọn – xem [RENDER_DEPLOY.md](RENDER_DEPLOY.md). Phần Railway (mục 9) chỉ để tham khảo.

Tài liệu cho người chạy dự án trên máy local bằng Docker và người deploy lên Railway
(hoặc nền tảng hỗ trợ Docker khác).

## Mục lục

1. [Kiến trúc](#1-kiến-trúc)
2. [Yêu cầu](#2-yêu-cầu)
3. [Tạo file .env](#3-tạo-file-env)
4. [Chạy local](#4-chạy-local)
5. [Lệnh vận hành hằng ngày](#5-lệnh-vận-hành-hằng-ngày)
6. [Database: chạy mới, import, export, đổi URL](#6-database)
7. [Uploads: sao lưu và khôi phục](#7-uploads)
8. [Chuyển từ XAMPP sang Docker](#8-chuyển-từ-xampp-sang-docker)
9. [Deploy lên Railway](#9-deploy-lên-railway)
10. [Domain HTTPS](#10-domain-https)
11. [Webhook SePay sau khi deploy](#11-webhook-sepay-sau-khi-deploy)
12. [Biến môi trường](#12-biến-môi-trường)
13. [Lỗi thường gặp](#13-lỗi-thường-gặp)

---

## 1. Kiến trúc

```
                 ┌──────────── mạng "web" ────────────┐
 trình duyệt ──► │ wordpress  (PHP 8.3 + Apache)      │ ──► Internet (Gemini, SePay, Google)
 :8000           │  cổng trong container: $PORT=8080  │
                 └───────────────┬────────────────────┘
                                 │ mạng "backend" (internal, không ra Internet)
                 ┌───────────────┴────────────────────┐
                 │ db  (MySQL 8.4)    không mở cổng   │
                 │ phpmyadmin  (profile "tools")      │ ◄── 127.0.0.1:8081 (chỉ local)
                 └────────────────────────────────────┘

 Volume:  db_data  → /var/lib/mysql                      (database)
          uploads  → /var/www/html/wp-content/uploads    (ảnh, file tải lên, log WooCommerce)
```

**Image `wordpress`** (xem `Dockerfile`):

| Thành phần | Nguồn | Phiên bản |
|---|---|---|
| PHP + Apache | `php:8.3.37-apache-trixie` | cố định |
| WordPress core | wordpress.org, kiểm tra SHA-256 | 7.1.4 |
| WooCommerce | wordpress.org, kiểm tra SHA-256 | 10.3.4 |
| Storefront (theme cha) | wordpress.org, kiểm tra SHA-256 | 4.6.1 |
| SePay Gateway | wordpress.org, kiểm tra SHA-256 | 1.1.24 |
| WP-CLI | GitHub, kiểm tra SHA-256 | 2.12.0 |
| `luxury-watches-child`, `luxury-auctions`, `luxury-chatbox`, `ai-content-guard` | repository | theo commit |

- PHP extension: mysqli, pdo_mysql, gd (jpeg/png/webp/avif), intl, zip, exif, mbstring, opcache, bcmath.
- Entrypoint chạy root chỉ để trao quyền thư mục uploads cho `www-data`; mọi request PHP do Apache
  `www-data` xử lý. Mã nguồn chỉ đọc; chỉ `uploads` ghi được. Cập nhật plugin/theme/core
  bằng cách đổi phiên bản trong `Dockerfile` rồi build lại, **không** cập nhật qua trang quản trị
  (đã tắt bằng `DISALLOW_FILE_MODS`).
- `wp-config.php` của image (`docker/wp-config-docker.php`) đọc mọi cấu hình từ biến môi trường.
- Media có sẵn của dự án (`wp-content/uploads/2026`) nằm trong image và được chép vào volume
  `uploads` ở lần chạy đầu – chỉ chép file còn thiếu, không ghi đè, không xóa.
- Image **không** chứa database, `.env`, `wp-config.php` của máy, file SQL, log hay ảnh chụp test
  (`.dockerignore` dạng danh sách cho phép).
- Healthcheck: `GET /healthz.php` → 200 khi PHP chạy và kết nối được database.

## 2. Yêu cầu

- Docker Desktop (Windows/macOS) hoặc Docker Engine + Compose plugin v2 (Linux).
- Khoảng 3 GB trống cho image và database. Trên Windows, ổ chứa dữ liệu Docker và ổ C:
  (Docker ghi log ở đó) đều cần còn chỗ trống.
- Git Bash (Windows) hoặc shell bash để chạy các script trong `scripts/docker/`.

## 3. Tạo file .env

```bash
cp .env.example .env
```

Mở `.env`, đổi tối thiểu:

- `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD` (và `WORDPRESS_DB_PASSWORD` giống `MYSQL_PASSWORD`).
- 8 khóa `WORDPRESS_*_KEY` / `*_SALT` (lấy từ https://api.wordpress.org/secret-key/1.1/salt/,
  bỏ phần `define(` – chỉ lấy chuỗi).

> Nếu đã có `.env` cũ (chỉ chứa `GEMINI_API_KEY`), **thêm** các dòng còn thiếu từ `.env.example`
> vào cuối file, đừng ghi đè. Compose báo lỗi rõ ràng nếu thiếu mật khẩu database.

Mật khẩu `MYSQL_*` chỉ được dùng khi tạo volume database lần đầu. Đổi sau đó phải đổi cả trong
MySQL (hoặc xóa volume nếu chưa có dữ liệu cần giữ).

## 4. Chạy local

```bash
docker compose up --build -d
docker compose ps          # chờ db "healthy", wordpress "healthy"
```

Mở http://localhost:8000. Database trống → WordPress mở trình cài đặt; có dump → xem mục 6.

phpMyAdmin (chỉ local, không bật mặc định):

```bash
docker compose --profile tools up -d     # http://localhost:8081  (user root hoặc wordpress)
docker compose --profile tools stop phpmyadmin
```

Phát triển theme/plugin (sửa file trên máy là thấy ngay, bật debug):

```bash
docker compose -f compose.yaml -f compose.dev.yaml up --build -d
```

`compose.dev.yaml` chỉ mount `luxury-watches-child`, `luxury-auctions`, `luxury-chatbox`;
core, WooCommerce, Storefront, SePay vẫn lấy từ image. Không dùng file này ở production.

## 5. Lệnh vận hành hằng ngày

| Việc | Lệnh |
|---|---|
| Dừng (giữ dữ liệu) | `docker compose stop` |
| Dừng và xóa container (giữ volume) | `docker compose down` |
| Khởi động lại | `docker compose restart wordpress` |
| Build lại sau khi sửa code / Dockerfile | `docker compose up --build -d` |
| Build lại từ đầu, không cache | `docker compose build --no-cache && docker compose up -d` |
| Xem log | `docker compose logs -f wordpress` (hoặc `db`) |
| Chạy WP-CLI | `docker compose exec -u www-data wordpress wp plugin list` |
| Vào shell container | `docker compose exec wordpress bash` |

> ⚠️ `docker compose down -v` **xóa volume** (database và uploads). Chỉ dùng khi chắc chắn.

Lỗi PHP không hiển thị trên trình duyệt; xem bằng `docker compose logs wordpress`.
Bật chi tiết hơn: đặt `WORDPRESS_DEBUG=true` trong `.env` rồi `docker compose up -d`.

## 6. Database

### Chạy mới

Không làm gì thêm: WordPress mở trình cài đặt ở lần truy cập đầu. Sau khi cài, vào
**Gói mở rộng (Plugins)** bật WooCommerce, SePay Gateway, Luxury Auctions, Luxury Watches Chatbox;
**Giao diện** chọn *Luxury Watches Atelier*; **Cài đặt → Đường dẫn tĩnh** chọn "Tên bài viết".

### Khôi phục database có sẵn – cách 1: lúc khởi tạo

1. Đặt file dump vào `docker/db-init/` (vd. `docker/db-init/luxury_watches.sql` hoặc `.sql.gz`).
2. `docker compose up --build -d`.

MySQL chỉ import khi volume `db_data` **còn trống**; restart hay rebuild sau đó không import lại.
File `.sql` trong thư mục này bị Git và Docker build bỏ qua. Import xong nên xóa file khỏi máy.

### Khôi phục – cách 2: vào database đang chạy

```bash
scripts/docker/db-import.sh duong-dan/dump.sql            # database trống
scripts/docker/db-import.sh duong-dan/dump.sql --replace  # đã có dữ liệu: tự sao lưu rồi ghi đè
```

### Export

```bash
scripts/docker/db-export.sh                        # → backups/db-<thời gian>.sql.gz
scripts/docker/db-export.sh backups/truoc-deploy.sql.gz
```

Thư mục `backups/` bị Git bỏ qua. File export chứa dữ liệu thật (khách hàng, đơn hàng):
không commit, không gửi qua kênh công khai.

### Đổi URL sau khi import

Dùng WP-CLI `search-replace` (xử lý đúng dữ liệu serialized). **Không** thay chuỗi trực tiếp
trong file SQL – sẽ làm hỏng dữ liệu serialized của WordPress/WooCommerce.

```bash
scripts/docker/search-replace.sh http://localhost/luxury-watches/wordpress http://localhost:8000          # chạy thử
scripts/docker/search-replace.sh http://localhost/luxury-watches/wordpress http://localhost:8000 --apply  # sao lưu rồi thay
```

Bỏ qua cột `guid` theo khuyến nghị của WordPress. URL đang hiển thị trên site còn do biến
`WP_HOME` / `WP_SITEURL` quyết định, nên trang vẫn chạy kể cả khi chưa search-replace.

## 7. Uploads

```bash
scripts/docker/uploads-backup.sh                                   # → backups/uploads-<thời gian>.tar.gz
scripts/docker/uploads-restore.sh backups/uploads-20260101-000000.tar.gz
scripts/docker/uploads-restore.sh wordpress/wp-content/uploads     # chép từ thư mục trên máy
```

Khôi phục chỉ thêm/ghi đè file, không xóa file đang có trong volume.

## 8. Chuyển từ XAMPP sang Docker

XAMPP và Docker dùng hai database riêng; chạy song song được (XAMPP: MariaDB cổng 3306 trên máy,
Docker: MySQL trong container, không mở cổng). Chỉ cần tránh trùng cổng web 8000
(`APP_PORT` trong `.env`).

1. Export database XAMPP (PowerShell, đổi đường dẫn nếu XAMPP cài nơi khác):
   ```powershell
   D:\xampp\mysql\bin\mysqldump.exe -u root -p --single-transaction --routines --triggers luxury_watches > D:\backup\luxury_watches.sql
   ```
   Đặt file **ngoài** thư mục repo hoặc trong `backups/` (bị Git bỏ qua).
2. `docker compose up --build -d`, chờ `db` healthy.
3. `scripts/docker/db-import.sh D:/backup/luxury_watches.sql`
4. Ảnh tải lên ngoài thư mục `2026` (nếu có): `scripts/docker/uploads-restore.sh wordpress/wp-content/uploads`
5. Đổi URL cũ nếu khác `http://localhost:8000` (mục 6). Có thể kiểm tra URL cũ:
   `docker compose exec -u www-data wordpress wp option get home`.
6. Đăng nhập `/wp-admin` bằng tài khoản cũ.

Database từ MariaDB 10.4 (XAMPP) import được vào MySQL 8.4. Dump có lỗi cú pháp riêng của
MariaDB thì export lại bằng `mysqldump` kèm `--compatible=ansi` hoặc export qua phpMyAdmin.

`wordpress/wp-config.php` của XAMPP không bị Docker dùng tới và vẫn bị Git bỏ qua.

## 9. Deploy lên Railway

### 9.1 Tạo service

1. **New Project → Deploy from GitHub repo**, chọn repository. Railway đọc `railway.toml` và
   build `Dockerfile` ở thư mục gốc; healthcheck `/healthz.php`.
2. **New → Database → MySQL** trong cùng project.

### 9.2 Biến môi trường của service WordPress

Thêm trong **Variables** (đổi `MySQL` thành tên service database nếu khác):

```text
WORDPRESS_DB_HOST=${{MySQL.MYSQLHOST}}:${{MySQL.MYSQLPORT}}
WORDPRESS_DB_NAME=${{MySQL.MYSQLDATABASE}}
WORDPRESS_DB_USER=${{MySQL.MYSQLUSER}}
WORDPRESS_DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
WP_HOME=https://${{RAILWAY_PUBLIC_DOMAIN}}
WP_SITEURL=https://${{RAILWAY_PUBLIC_DOMAIN}}
WP_ENVIRONMENT_TYPE=production
WORDPRESS_AUTH_KEY=…   (và 7 khóa còn lại – chuỗi ngẫu nhiên, KHÔNG dùng lại khóa local)
LUXURY_CHATBOX_GEMINI_KEY=…
GOOGLE_CLIENT_ID=…            (nếu dùng đăng nhập Google)
RAILWAY_RUN_UID=0             (chỉ cần nếu Railway chạy container bằng user khác root – xem 9.3)
```

Không đặt `PORT`: Railway tự cấp và Apache lắng nghe đúng cổng đó. Nếu thiếu `WORDPRESS_DB_*`,
image tự đọc biến `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`
(hoặc `MYSQL_URL`) khi chúng được tham chiếu sang service.

Bí mật chỉ đặt trong Variables của Railway (hoặc Railway Secrets), không đặt trong repository.

### 9.3 Lưu trữ uploads

Filesystem của container Railway **mất khi redeploy**. Bắt buộc gắn volume:

- Service WordPress → **Settings → Volumes → New Volume**, mount path
  `/var/www/html/wp-content/uploads`.
- Image mặc định chạy entrypoint bằng root: volume Railway thuộc root; entrypoint sẽ trao quyền
  thư mục cho `www-data`, chép media có sẵn, rồi Apache xử lý request bằng `www-data`.

Giới hạn: một volume chỉ gắn được vào **một** instance (không scale ngang), dung lượng theo gói
Railway, và cần tự sao lưu (snapshot của Railway hoặc tải về định kỳ). Khi lượng ảnh lớn hoặc
cần nhiều instance, chuyển uploads sang object storage (Cloudflare R2, Amazon S3, DigitalOcean
Spaces) bằng plugin offload như *WP Offload Media* hoặc *Media Cloud*; plugin đó cần thêm vào
`Dockerfile` (tải bản cố định như các plugin khác).

### 9.4 Đưa database lên

Database MySQL trên Railway trống → WordPress mở trình cài đặt. Để dùng dữ liệu hiện có:

1. Export local: `scripts/docker/db-export.sh backups/len-railway.sql.gz`.
2. Import vào MySQL Railway từ máy (lấy thông số trong tab **Connect** của service MySQL,
   dùng *public network*):
   ```bash
   gzip -dc backups/len-railway.sql.gz | mysql -h <host> -P <port> -u <user> -p <database>
   ```
3. Đổi URL bằng WP-CLI trong container Railway (Railway CLI):
   ```bash
   railway ssh   # hoặc: railway run -- bash, tùy phiên bản CLI
   wp search-replace 'http://localhost:8000' 'https://<domain>' --all-tables-with-prefix --precise --skip-columns=guid
   ```

### 9.5 Kiểm tra sau deploy

- `https://<domain>/healthz.php` → `{"status":"ok"}`
- `https://<domain>/` , `/wp-admin/`, `/shop/`, `/phien-dau-gia/`
- Log: tab **Deployments → View logs**.

## 10. Domain HTTPS

1. Railway: **Settings → Networking → Custom Domain**, thêm domain, tạo bản ghi CNAME theo hướng
   dẫn. Railway tự cấp chứng chỉ TLS.
2. Đổi `WP_HOME` và `WP_SITEURL` thành `https://domain-cua-ban` rồi redeploy.
3. Chạy search-replace từ domain cũ sang domain mới (mục 9.4 bước 3).
4. Cập nhật Authorized JavaScript origins của Google OAuth và webhook SePay (mục 11).

HTTPS kết thúc ở proxy Railway; container nhận HTTP kèm `X-Forwarded-Proto: https`.
`wp-config` nhận biết header này nên WordPress hiểu đúng kết nối HTTPS và không lặp chuyển hướng.
`FORCE_SSL_ADMIN` tự bật khi `WP_HOME` là https. Dùng proxy không gửi `X-Forwarded-Proto` thì
đặt `WORDPRESS_FORCE_SSL_ADMIN=false`.

## 11. Webhook SePay sau khi deploy

Khi chạy local, mã QR vẫn hiển thị nhưng SePay không gọi được webhook vào `localhost`, nên đơn
không tự xác nhận – xác nhận tay trong WooCommerce → Đơn hàng.

Production:

1. `WP_HOME`/`WP_SITEURL` phải là domain HTTPS công khai.
2. Trong WordPress: **WooCommerce → Cài đặt → Thanh toán → SePay**:
   - *Kết nối tài khoản SePay* (OAuth): plugin tự tạo webhook trỏ về site; hoặc
   - Điền tay ngân hàng, số tài khoản, chủ tài khoản; lưu lại **API Key** hiển thị ở đó.
3. Điền tay thì vào my.sepay.vn → **Webhooks → Thêm webhook**:
   - URL: `https://<domain>/wp-json/sepay-gateway/v1/add-payment`
   - Kiểu chứng thực: **API Key**, giá trị là API Key ở bước 2
     (SePay gửi header `Authorization: Apikey <key>`; Apache đã chuyển tiếp header này cho PHP).
4. Kiểm tra bằng tính năng gửi thử webhook của SePay hoặc một giao dịch giá trị nhỏ khi được phép.
   Không chuyển tiền thật chỉ để thử.

API Key nằm trong database (cấu hình plugin), không nằm trong image hay biến môi trường;
không ghi nó vào log, tài liệu hay ticket.

## 12. Biến môi trường

| Biến | Mặc định | Ghi chú |
|---|---|---|
| `APP_PORT` | `8000` | Cổng web trên máy host (chỉ Compose) |
| `PMA_PORT` | `8081` | Cổng phpMyAdmin, chỉ nghe 127.0.0.1 (chỉ Compose) |
| `MYSQL_ROOT_PASSWORD` | – bắt buộc | Mật khẩu root MySQL (chỉ Compose, khi tạo volume) |
| `MYSQL_DATABASE` / `MYSQL_USER` / `MYSQL_PASSWORD` | `luxury_watches` / `wordpress` / – bắt buộc | Tạo database và user khi khởi tạo volume |
| `WORDPRESS_DB_HOST` | `db` | `host` hoặc `host:port` |
| `WORDPRESS_DB_NAME` / `_USER` / `_PASSWORD` | như trên | Fallback: `MYSQLDATABASE`/`MYSQLUSER`/`MYSQLPASSWORD` (Railway), `MYSQL_URL` |
| `WORDPRESS_TABLE_PREFIX` | `wp_` | |
| `WP_HOME` / `WP_SITEURL` | `http://localhost:${APP_PORT}` | Production: domain HTTPS, bắt buộc đặt |
| `WP_ENVIRONMENT_TYPE` | `local` (Compose), `production` (image) | `local`, `development`, `staging`, `production` |
| `WORDPRESS_DEBUG` | `false` | Bật WP_DEBUG; lỗi ghi ra log container |
| `WORDPRESS_DEBUG_DISPLAY` | `false` | Hiện lỗi trên trang – bị bỏ qua khi `production` |
| `WORDPRESS_FORCE_SSL_ADMIN` | tự động | `true` khi `WP_HOME` là https |
| `WORDPRESS_DISALLOW_FILE_MODS` | `true` | Chặn cài/cập nhật plugin qua admin (mã nguồn chỉ đọc) |
| `WORDPRESS_AUTH_KEY` … `WORDPRESS_NONCE_SALT` | dẫn xuất | 8 khóa bảo mật – nên đặt |
| `LUXURY_CHATBOX_GEMINI_KEY` hoặc `GEMINI_API_KEY` | – | Gemini cho chatbot |
| `GOOGLE_CLIENT_ID` | – | Đăng nhập Google; trống thì ẩn nút |
| `LW_API_ALLOWED_ORIGINS` | – | CORS cho API `luxury/v1`, phân tách bằng dấu phẩy |
| `PORT` | `8080` | Cổng Apache trong container; Railway tự đặt |
| `RAILWAY_RUN_UID` | – | Railway: chỉ cần khi service bị đặt chạy bằng user khác root |
| `DB_INIT_DIR` | `./docker/db-init` | Thư mục SQL import lúc khởi tạo (chỉ Compose) |
| `COMPOSE_ARGS` | – | Tham số thêm cho `docker compose` trong `scripts/docker/*.sh`, vd. `--env-file .env.staging` |

Mọi biến cũng đọc được từ file qua hậu tố `_FILE` (Docker secrets), vd. `WORDPRESS_DB_PASSWORD_FILE=/run/secrets/db_password`.

## 13. Lỗi thường gặp

| Triệu chứng | Nguyên nhân / cách xử lý |
|---|---|
| `required variable MYSQL_ROOT_PASSWORD is missing` | Chưa tạo `.env` hoặc thiếu biến – xem mục 3. |
| `Bind for 0.0.0.0:8000 failed: port is already allocated` | Cổng 8000 đang dùng (XAMPP, `php -S`…). Tắt chương trình đó hoặc đặt `APP_PORT=8090`, đồng thời `WP_HOME=http://localhost:8090`. |
| "Error establishing a database connection" | `docker compose ps` xem `db` healthy chưa; `WORDPRESS_DB_PASSWORD` phải khớp `MYSQL_PASSWORD`. Đổi mật khẩu sau khi volume đã tạo không có tác dụng với MySQL – đổi trong MySQL hoặc tạo lại volume. |
| Vào site bị chuyển về `localhost` hoặc domain cũ | `WP_HOME`/`WP_SITEURL` sai, hoặc nội dung còn URL cũ → search-replace (mục 6). |
| Vòng lặp chuyển hướng trên HTTPS | Proxy không gửi `X-Forwarded-Proto`. Đặt `WORDPRESS_FORCE_SSL_ADMIN=false`, kiểm tra cấu hình proxy. |
| Trang 404 cho mọi đường dẫn trừ trang chủ | Vào **Cài đặt → Đường dẫn tĩnh** bấm Lưu (rewrite đã có sẵn trong Apache). |
| Upload ảnh báo "không thể ghi" | Volume uploads không thuộc `www-data` (entrypoint tự sửa khi khởi động; xem log). Sửa tay: `docker compose run --rm --user root --entrypoint chown wordpress -R www-data:www-data /var/www/html/wp-content/uploads`. |
| Ảnh lớn bị từ chối | Giới hạn 64 MB (`docker/php/luxury.ini`). |
| Không cài được plugin trong admin | Cố ý (`DISALLOW_FILE_MODS`). Thêm plugin vào `Dockerfile` rồi build lại. |
| Chatbot trả lời "chưa cấu hình" | Thiếu `LUXURY_CHATBOX_GEMINI_KEY`/`GEMINI_API_KEY` trong môi trường. |
| SePay không tự xác nhận đơn ở local | Bình thường – webhook không vào được `localhost` (mục 11). |
| Docker Desktop báo "not enough space on the disk" | Ổ C: đầy (Docker Desktop ghi log ở đó dù dữ liệu nằm ổ khác). Giải phóng dung lượng rồi mở lại; đừng chọn "Reset to factory defaults" (xóa toàn bộ image và volume). |
| Script trong `scripts/docker/` báo lỗi đường dẫn trên Windows | Chạy bằng Git Bash, không phải PowerShell/cmd. |
