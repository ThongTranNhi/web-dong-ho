# Luxury Watches

Website bán hàng và đấu giá đồng hồ xây dựng trên WordPress, WooCommerce và Storefront. Giao diện tập trung vào sản phẩm cao cấp, bộ sưu tập nam/nữ, nội dung kiến thức và quy trình hậu mãi.

## Thành phần trong repository

- `wordpress/wp-content/themes/luxury-watches-child`: child theme và giao diện trang chủ.
- `wordpress/wp-content/plugins/luxury-auctions`: tính năng phiên đấu giá và trả giá.
- `wordpress/wp-content/plugins/luxury-chatbox`: backend chatbot hỗ trợ khách hàng (REST API, lưu hội thoại, tích hợp Gemini). Xem `docs/CHATBOX_API.md`.
- `wordpress/wp-content/uploads/2026`: hình ảnh sản phẩm và bài viết của dự án.
- `scripts`: script tạo sản phẩm mẫu và phân loại bộ sưu tập.

WordPress core, plugin bên thứ ba, cấu hình cơ sở dữ liệu, log và backup không được lưu trong Git.

## Chạy bằng Docker (khuyến nghị)

```bash
cp .env.example .env      # đổi các mật khẩu trong .env
docker compose up --build -d
```

Mở http://localhost:8000. Image đã gồm WordPress core, WooCommerce, Storefront, SePay Gateway (phiên bản cố định) cùng theme và plugin của dự án; database và uploads nằm trong volume nên không mất khi khởi động lại. Import database có sẵn, sao lưu, chuyển từ XAMPP và deploy lên Railway: xem [docs/DOCKER_DEPLOY.md](docs/DOCKER_DEPLOY.md).

## Chạy bằng XAMPP

### Yêu cầu

- XAMPP hoặc môi trường có Apache, PHP và MySQL.
- WordPress đã cài đặt trong thư mục `wordpress`.
- WooCommerce và theme Storefront.

## Cài đặt cục bộ

1. Clone repository vào thư mục `C:\xampp\htdocs\luxury-watches`.
2. Cài WordPress core vào thư mục `wordpress` nếu chưa có.
3. Cài và kích hoạt WooCommerce cùng theme Storefront.
4. Kích hoạt plugin **Luxury Auctions** và **Luxury Watches Chatbox**.
5. Kích hoạt theme **Luxury Watches Atelier**.
6. Sau khi kết nối cơ sở dữ liệu, chạy các script tạo sản phẩm nếu cần:

```powershell
C:\xampp\php\php.exe scripts\add_luxury_products.php
C:\xampp\php\php.exe scripts\add_gender_collections.php
C:\xampp\php\php.exe scripts\add_auction_collection.php
C:\xampp\php\php.exe scripts\add_new_arrivals_20.php
C:\xampp\php\php.exe scripts\add_information_pages.php
```

Script `add_new_arrivals_20.php` tạo hoặc cập nhật 20 sản phẩm LW Atelier thuộc **Bộ sưu tập mới 2026**, kèm ảnh riêng, giá bán, tồn kho và phân loại Nam/Nữ. Script có thể chạy lại mà không tạo sản phẩm trùng SKU.

Ảnh sản phẩm dùng WebP để giảm dung lượng tải trang. Khi cần tạo lại từ ảnh PNG nguồn, chạy `python scripts/optimize_new_arrivals.py` trước khi nhập sản phẩm.

Script `add_information_pages.php` tạo hoặc cập nhật các trang hướng dẫn mua hàng, đấu giá, đổi trả, vận chuyển, bảo mật và câu hỏi thường gặp.

Quy trình tạo, kiểm tra và vận hành phiên đấu giá được mô tả tại `docs/AUCTION_WORKFLOW.md`.

Website cục bộ mặc định: `http://localhost/luxury-watches/wordpress/`.

## Cấu hình riêng trên từng máy (không có trong Git)

`wordpress/wp-config.php` bị Git bỏ qua. Mỗi thành viên tạo file này từ `wordpress/wp-config-sample.php` (có sẵn khi cài WordPress core), rồi điền:

```php
define('DB_NAME', 'luxury_watches');
define('DB_USER', 'root');
define('DB_PASSWORD', '');          // mật khẩu MySQL trên máy bạn
define('DB_HOST', 'localhost');
// Chỉ khi chạy bằng PHP built-in server (php -S localhost:8000):
// define('WP_HOME', 'http://localhost:8000');
// define('WP_SITEURL', 'http://localhost:8000');
```

**API key Gemini nằm trong file `.env`** ở thư mục gốc dự án (cạnh `README.md`), cũng bị Git bỏ qua. Tạo file này từ mẫu rồi điền key lấy tại Google AI Studio:

```powershell
copy .env.example .env
```

```dotenv
GEMINI_API_KEY=your-gemini-api-key
```

Khi chạy bằng `php -S` trong thư mục `wordpress`, file `.env` nằm ngoài thư mục web. Khi đặt dự án trong `htdocs` của Apache, file `.htaccess` ở thư mục gốc chặn truy cập `.env` qua trình duyệt. Trên server (Docker/Railway), đặt biến môi trường `GEMINI_API_KEY` thay cho file. Thứ tự ưu tiên: hằng số `LUXURY_CHATBOX_GEMINI_KEY` trong `wp-config.php` (nếu có), rồi biến môi trường, rồi file `.env`.

Sau đó vào WP Admin → **Chatbox** để bật chatbot, chọn model (đang dùng `gemini-3.5-flash`, timeout 60 giây) và model dự phòng nếu có. Chi tiết API và cấu hình: `docs/CHATBOX_API.md`.

Dữ liệu database (sản phẩm, đơn hàng, phiên đấu giá, hội thoại) không nằm trong Git. Dùng các script ở trên để tạo dữ liệu mẫu, hoặc nhập bản export SQL được chia sẻ riêng.

## Kiểm thử

```powershell
C:\xampp\php\php.exe scripts\tests\auction_bidding.php
C:\xampp\php\php.exe scripts\tests\auction_confirmation.php
C:\xampp\php\php.exe scripts\tests\auction_dashboard.php
C:\xampp\php\php.exe scripts\tests\auction_dashboard_products.php
C:\xampp\php\php.exe scripts\tests\auction_validation.php
C:\xampp\php\php.exe scripts\tests\chatbox_backend.php
node scripts\tests\chatbox_frontend.js   # cần Chrome và puppeteer-core (biến PUPPETEER_CORE)
```

Các test chatbox dùng AI giả lập nên không gọi Gemini và không tốn phí.

## Phông chữ và nhận diện

Website dùng phông Be Vietnam Pro (tự lưu trong `themes/luxury-watches-child/assets/fonts`, giấy phép OFL) cho cả tiêu đề và nội dung. Các mẫu đồng hồ nguyên bản sử dụng dấu hiệu nhận diện **LW Atelier**.
