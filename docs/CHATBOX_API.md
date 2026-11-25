# Chatbox hỗ trợ khách hàng — Backend

Plugin `luxury-chatbox` cung cấp backend cho chatbot tư vấn đồng hồ, giải đáp chính sách và hướng dẫn đấu giá. Dữ liệu trả lời được đọc trực tiếp từ WooCommerce, các trang đã xuất bản và plugin `luxury-auctions` tại thời điểm hỏi. Chatbot chỉ tư vấn và trả về đường dẫn; khách tự đặt hàng hoặc trả giá trên các trang hiện có.

- Nhà cung cấp AI: Google Gemini, model mặc định `gemini-3.8-flash` (đổi được trong admin).
- Namespace REST: `/wp-json/luxury-chat/v1`.
- Kiểm thử: `C:\xampp\php\php.exe scripts\tests\chatbox_backend.php` (backend) và `node scripts/tests/chatbox_frontend.js` (giao diện, mục 6b).

## 1. Cấu trúc plugin

```
wordpress/wp-content/plugins/luxury-chatbox/
├── luxury-chatbox.php                 # bootstrap, hook kích hoạt/tắt
├── uninstall.php                      # hành vi khi gỡ plugin
└── includes/
    ├── class-lc-settings.php          # cấu hình admin + đọc API key (env / wp-config)
    ├── class-lc-installer.php         # tạo/nâng cấp bảng bằng dbDelta
    ├── class-lc-repository.php        # toàn bộ SQL hội thoại/tin nhắn, kiểm tra chủ sở hữu
    ├── class-lc-session.php           # định danh khách (cookie token) và thành viên
    ├── class-lc-rate-limiter.php      # giới hạn tốc độ và hạn mức
    ├── class-lc-knowledge.php         # truy xuất sản phẩm, chính sách, phiên đấu giá
    ├── interface-lc-ai-provider.php   # hợp đồng nhà cung cấp AI
    ├── class-lc-ai-error.php          # lỗi AI đã chuẩn hóa mã
    ├── class-lc-gemini-provider.php# gọi Gemini Messages API
    ├── class-lc-chat-service.php      # điều phối: idempotency, prompt, kiểm tra phản hồi
    ├── class-lc-rest.php              # REST API
    ├── class-lc-cleanup.php           # cron dọn hội thoại hết hạn
    └── class-lc-admin.php             # menu Chatbox trong WP Admin
```

Plugin đấu giá có thêm một hàm đọc dùng chung `Luxury_Watches_Auctions::snapshot($id)`. Hàm này trả đúng dữ liệu của `auction_response()` (trạng thái, giá hiện tại, mức trả tối thiểu, bước giá) và không thay đổi luồng trả giá.

## 2. Cấu trúc database

Hai bảng dùng `$wpdb->prefix`, được tạo khi kích hoạt và tự nâng cấp khi `luxury_chatbox_db_version` khác phiên bản plugin. `dbDelta` cho phép chạy lại nhiều lần.

### `{prefix}luxury_chat_conversations`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | bigint unsigned, PK | ID hội thoại |
| `user_id` | bigint unsigned | ID tài khoản nếu đã đăng nhập, `0` với khách |
| `guest_hash` | char(64) | HMAC-SHA256 của token khách (khóa `wp_salt('auth')`); rỗng với thành viên |
| `created_at` | datetime (UTC) | |
| `last_activity_at` | datetime (UTC) | Cập nhật mỗi lần gửi tin, dùng để dọn dữ liệu |

Chỉ mục: `user_activity (user_id, last_activity_at)`, `guest_activity (guest_hash, last_activity_at)`, `last_activity (last_activity_at)`.

### `{prefix}luxury_chat_messages`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `conversation_id` | bigint unsigned | |
| `role` | varchar(16) | `user` hoặc `assistant` |
| `content` | longtext | Văn bản thuần |
| `status` | varchar(16) | `pending`, `completed`, `failed` |
| `request_id` | varchar(64) | Mã yêu cầu từ frontend; tin trả lời mang cùng mã |
| `meta` | longtext JSON | Trả lời: sản phẩm, phiên, nguồn, model, usage. Tin lỗi: mã lỗi |
| `created_at` | datetime (UTC) | |

Chỉ mục: `UNIQUE conversation_request_role (conversation_id, request_id, role)` để chống ghi trùng; `conversation_created (conversation_id, created_at)` để đọc lịch sử; `role_created`, `created_at` để đếm hạn mức và dọn dữ liệu.

## 3. Cấu hình

### API key

Key chỉ được đọc phía máy chủ, không lưu trong database, không đưa vào Git, frontend hay phản hồi API. Cách khuyến nghị là file `.env` ở thư mục gốc dự án (tạo từ `.env.example`, bị Git bỏ qua):

```dotenv
GEMINI_API_KEY=YOUR_GEMINI_API_KEY
```

Plugin chỉ đọc đúng biến `GEMINI_API_KEY` trong file (hỗ trợ comment `#`, dấu nháy, `export`), không nạp các biến khác vào môi trường. Đổi đường dẫn file bằng `define('LUXURY_CHATBOX_ENV_FILE', '...')`. File `.htaccess` ở thư mục gốc chặn tải `.env` khi dự án nằm trong document root của Apache.

Thứ tự ưu tiên: hằng số `LUXURY_CHATBOX_GEMINI_KEY` trong `wp-config.php`, biến môi trường `GEMINI_API_KEY` của tiến trình PHP (dùng cho Docker/Railway), rồi file `.env`. Kiểm thử: `scripts/tests/chatbox_env.php`.

### WP Admin → Chatbox

| Mục | Mặc định | Giới hạn |
|---|---|---|
| Bật chatbot | Tắt | |
| Model | `gemini-3.8-flash` (đang cấu hình: `gemini-3.5-flash`) | |
| Model dự phòng | Trống (tắt) | Dùng tối đa một lần khi model chính trả HTTP 503 |
| Độ dài tối đa tin nhắn | 1000 ký tự | 50–4000 |
| Số tin nhắn lịch sử gửi cho AI | 8 | 0–20 |
| Giới hạn mỗi phút (mỗi phiên) | 6 | 1–60 |
| Giới hạn mỗi ngày (mỗi phiên) | 60 lượt gọi AI | 1–1000 |
| Hạn mức toàn website mỗi ngày | 2000 lượt gọi AI | 1–100000 |
| Thời gian chờ AI | 30 giây (đang cấu hình: 60) | 5–120, áp dụng cho từng lần gọi |
| Thời gian lưu hội thoại | 30 ngày | 1–365 |
| Xóa dữ liệu khi gỡ plugin | Tắt | |

Trang này cũng hiển thị trạng thái key (chỉ "đã/chưa cấu hình" và nguồn), kết nối WooCommerce/đấu giá, số hội thoại, lịch dọn dữ liệu, nút **Dọn hội thoại hết hạn ngay** và **Xóa toàn bộ dữ liệu chat** (phải nhập `XOA` để xác nhận).

### Vòng đời dữ liệu

- Cron `luxury_chatbox_cleanup` chạy hằng ngày, xóa hội thoại có `last_activity_at` cũ hơn thời gian lưu cùng mọi tin nhắn của nó.
- Tắt (Deactivate) plugin: hủy lịch cron, giữ nguyên dữ liệu.
- Gỡ (Delete) plugin: mặc định giữ dữ liệu. Nếu bật "Xóa dữ liệu khi gỡ plugin", `uninstall.php` xóa hai bảng và các option của plugin.
- Khách tự xóa lịch sử của mình qua `DELETE /messages`.

## 4. REST API

### Định danh và bảo mật

- **Thành viên**: dùng phiên đăng nhập WordPress. Frontend phải gửi header `X-WP-Nonce` (giá trị `wp_create_nonce('wp_rest')`, ví dụ truyền qua `wp_localize_script`). Thiếu nonce, WordPress coi request là khách.
- **Khách**: lần đầu gọi `POST /session` hoặc `POST /messages`, máy chủ tạo token ngẫu nhiên 256 bit và trả cookie `lw_chat_session` (`HttpOnly`, `SameSite=Lax`, thêm `Secure` khi chạy HTTPS, hạn bằng thời gian lưu hội thoại). Máy chủ chỉ lưu HMAC của token.
- Không route nào nhận ID hội thoại từ client. Máy chủ luôn chọn hội thoại của phiên hiện tại, nên biết ID của người khác cũng không đọc hay xóa được.
- Gọi bằng `fetch(..., { credentials: 'same-origin' })` để trình duyệt gửi kèm cookie.

### Cấu trúc phản hồi thống nhất

```json
{ "ok": true,  "data": { ... }, "error": null }
{ "ok": false, "data": null,   "error": { "code": "rate_limited", "message": "…", "retryable": true, "retry_after": 60 } }
```

Mọi phản hồi có `Cache-Control: no-store, private`. Lỗi có `retry_after > 0` kèm header `Retry-After`.

### `GET /status`

Kiểm tra chatbot có dùng được không, trước khi hiển thị nút chat.

```json
{ "ok": true, "data": {
  "enabled": true, "available": true, "logged_in": false,
  "limits": { "max_message_chars": 1000, "per_minute": 6, "per_day": 60 }
}, "error": null }
```

`available` = đã bật và đã cấu hình key.

### `POST /session`

Tạo hoặc tiếp tục hội thoại gần nhất của phiên, trả về lịch sử.

```json
{ "ok": true, "data": {
  "conversation": { "id": 12, "created_at": "2026-11-24T17:39:53+00:00", "last_activity_at": "2026-11-24T17:39:53+00:00" },
  "messages": []
}, "error": null }
```

### `POST /messages`

Body JSON:

| Trường | Bắt buộc | Mô tả |
|---|---|---|
| `message` | có | Nội dung, tối đa `limits.max_message_chars` ký tự |
| `request_id` | có | 8–64 ký tự `[A-Za-z0-9_-]`, sinh mới cho mỗi tin (ví dụ `crypto.randomUUID()`). Khi gửi lại do mất mạng, dùng lại đúng mã cũ. |

```http
POST /wp-json/luxury-chat/v1/messages
Content-Type: application/json
X-WP-Nonce: <nonce, nếu đã đăng nhập>

{"message":"Tư vấn đồng hồ nam dưới 30 triệu phong cách lặn","request_id":"5f0c1c1e-9d1a-4b8e-9f3a-2a7c0c6b1d11"}
```

```json
{ "ok": true, "data": {
  "conversation": { "id": 12, "created_at": "…", "last_activity_at": "…" },
  "request_id": "5f0c1c1e-9d1a-4b8e-9f3a-2a7c0c6b1d11",
  "replayed": false,
  "user_message": { "id": 50, "role": "user", "content": "Tư vấn đồng hồ nam…", "status": "completed", "request_id": "…", "created_at": "…" },
  "reply": {
    "id": 51, "role": "assistant", "status": "completed", "request_id": "…", "created_at": "…",
    "content": "Trong tầm 30 triệu, Ocean GMT 24 (22.500.000 ₫, đang giảm từ 25.500.000 ₫) có bezel 24 giờ…",
    "products": [{
      "id": 13, "name": "Ocean GMT 24", "price": 22500000, "regular_price": 25500000, "on_sale": true,
      "in_stock": true, "stock_quantity": 6, "gender": "Nam", "case_size_mm": null,
      "summary": "Mặt xanh navy, kim GMT đỏ và vòng bezel 24 giờ dành cho người thường xuyên di chuyển.",
      "url": "http://localhost:8000/product/ocean-gmt-24/",
      "image": "http://localhost:8000/wp-content/uploads/2026/10/luxury-collection/gmt-ocean.webp"
    }],
    "auctions": [],
    "sources": [{ "title": "Ocean GMT 24", "url": "http://localhost:8000/product/ocean-gmt-24/" }],
    "missing_info": false
  }
}, "error": null }
```

Phần tử trong `auctions`:

```json
{ "id": 26, "title": "Aurelius Deepsea GMT", "lot": "LOT 026", "state": "live", "state_label": "Đang diễn ra",
  "starts_at": "04:11 09/11/2026", "ends_at": "19:11 13/11/2026", "start_price": 24500000, "current_bid": 26000000,
  "minimum_bid": 26500000, "bid_increment": 500000, "bid_count": 4, "accepting_bids": true,
  "url": "http://localhost:8000/phien-dau-gia/aurelius-deepsea-gmt/", "image": "…" }
```

`case_size_mm` là `null` khi mô tả sản phẩm không ghi kích thước vỏ. `minimum_bid` là `null` với phiên đã kết thúc. `missing_info: true` nghĩa là câu trả lời có phần chưa xác nhận được; frontend nên gợi ý liên hệ cửa hàng.

**Gửi lại cùng `request_id`**: không gọi AI lần hai. Nếu đã có câu trả lời, API trả lại đúng câu trả lời đó với `replayed: true`. Nếu lần trước lỗi, API thử lại đúng một lần. Nếu lần trước còn đang xử lý, API trả `409 request_in_progress`.

### `GET /messages`

Lịch sử hội thoại của phiên hiện tại (tối đa 100 tin gần nhất). Tin của khách bị lỗi có `status: "failed"` và `error_code`, để frontend hiện nút gửi lại với cùng `request_id`.

### `DELETE /messages`

Xóa hội thoại và tin nhắn của phiên hiện tại: `{ "ok": true, "data": { "deleted_conversations": 1 }, "error": null }`.

### Mã lỗi

| HTTP | `code` | Gửi lại được | Ý nghĩa |
|---|---|---|---|
| 400 | `invalid_request_id`, `empty_message`, `message_too_long` | không | Dữ liệu gửi lên không hợp lệ |
| 409 | `request_id_conflict` | không | `request_id` đã dùng cho nội dung khác |
| 409 | `request_in_progress` | có | Yêu cầu cùng mã đang được xử lý |
| 429 | `rate_limited` | có | Vượt giới hạn mỗi phút |
| 429 | `quota_exceeded` | có (sau) | Vượt giới hạn 24 giờ của phiên |
| 429 | `service_quota_exceeded` | có (sau) | Vượt hạn mức toàn website |
| 429 | `ai_busy` | có | Nhà cung cấp AI giới hạn tốc độ |
| 429 | `ai_quota_exceeded` | không | Tài khoản AI hết hạn mức chi tiêu |
| 422 | `ai_refused`, `ai_bad_request` | không | AI từ chối hoặc không xử lý được nội dung |
| 503 | `chatbot_disabled` | không | Quản trị viên đã tắt chatbot |
| 503 | `ai_not_configured` | không | Chưa có API key hoặc key sai |
| 503 | `ai_unavailable`, `ai_bad_response` | có | AI lỗi hoặc trả dữ liệu hỏng |
| 504 | `ai_timeout` | có | Vượt thời gian chờ |
| 500 | `storage_error`, `internal_error` | có | Lỗi máy chủ |

Chi tiết kỹ thuật của lỗi AI (mã HTTP, loại lỗi) chỉ lưu trong `meta` của tin nhắn, không trả cho client.

## 5. Dữ liệu gửi cho AI và kiểm soát an toàn

- Mỗi câu hỏi được phân loại bằng từ khóa: sản phẩm, chính sách, đấu giá. Câu hỏi tiếp nối ngắn kế thừa chủ đề và tiêu chí (ngân sách, giới tính, kích thước) của hai tin trước.
- **Sản phẩm**: `wc_get_products` lấy sản phẩm đang hiển thị, lọc theo ngân sách và danh mục `dong-ho-nam` / `dong-ho-nu`, xếp hạng theo phong cách, kích thước, tồn kho. Gửi tối đa 6 sản phẩm, cộng 3 mẫu thay thế gần ngân sách nhất khi không đủ mẫu phù hợp. Sản phẩm thuộc `bo-suu-tap-dau-gia` bán qua đấu giá nên không được gợi ý mua trực tiếp.
- **Chính sách**: đọc trang đã xuất bản theo slug (`chinh-sach-bao-hanh`, `chinh-sach-doi-tra`, `chinh-sach-van-chuyen`, `chinh-sach-bao-mat`, `huong-dan-mua-hang`, `huong-dan-dau-gia`, `cau-hoi-thuong-gap`), bỏ HTML và shortcode, tối đa 3500 ký tự mỗi trang, tối đa 2 trang.
- **Đấu giá**: dữ liệu từ `Luxury_Watches_Auctions::snapshot()`. Không gửi giá dự trữ và danh tính người trả giá.
- Không gửi đơn hàng, tài khoản hay dữ liệu khách hàng khác.
- System prompt cố định (được cache). Dữ liệu website nằm trong `<website_data>`, tin nhắn khách nằm trong `<customer_message>`; cả hai được khai báo là dữ liệu tham khảo, không phải chỉ dẫn. Máy chủ loại bỏ thẻ này khỏi nội dung khách để khách không thoát được khỏi khối dữ liệu.
- AI không có công cụ nào, nên không thể chạy SQL, PHP hay thao tác quản trị.
- AI trả JSON theo schema cố định. Máy chủ chỉ giữ ID sản phẩm/phiên và URL có trong dữ liệu đã gửi. Thông tin sản phẩm và phiên trong phản hồi lấy từ dữ liệu máy chủ, không lấy từ văn bản AI. HTML bị loại khỏi câu trả lời, và mọi đường dẫn không thuộc website bị xóa.
- **Frontend phải hiển thị `content` bằng `textContent`** (không dùng `innerHTML`), và chỉ tạo link từ `products[].url`, `auctions[].url`, `sources[].url`.

## 6. Kết quả kiểm tra

`scripts/tests/chatbox_backend.php` dùng một AI giả lập (không gọi mạng, không tốn phí). Riêng phần Gemini được kiểm bằng cách chặn HTTP để soát request và các phản hồi lỗi. Test tự dọn dữ liệu tạo ra và khôi phục cấu hình.

Kết quả ngày 25/11/2026 sau khi chuyển Gemini: **74/74 đạt**. Sau khi thêm model dự phòng: **91/91 đạt** (mục 11 của test: chính 503 → dự phòng thành công; cả hai 503; gửi lại cùng `request_id` không tạo tin trùng; lỗi không phải 503 không dùng dự phòng; bỏ qua dự phòng khi hết hạn mức; request còn trong thời gian chờ không bị gọi lại).

| Tình huống nghiệm thu | Kết quả |
|---|---|
| Khách chưa đăng nhập và thành viên gửi tin được | Đạt |
| Không đọc/xóa được hội thoại của người khác, kể cả khi biết ID | Đạt |
| Gửi lại cùng `request_id`: một lần gọi AI, một câu trả lời | Đạt |
| Gợi ý sản phẩm khớp ngân sách, giới tính, giá và tồn kho WooCommerce | Đạt |
| Đấu giá: trạng thái, bước giá, mức tối thiểu khớp plugin đấu giá | Đạt |
| Chính sách có nguồn; thiếu thông tin được đánh dấu `missing_info` | Đạt |
| Tin nhắn độc hại không lộ key, không thoát khối dữ liệu, không chèn link/HTML | Đạt |
| Giới hạn độ dài, tốc độ, hạn mức; lỗi AI trả mã xử lý được | Đạt |
| Xóa lịch sử và dọn hội thoại hết hạn | Đạt |
| Request gửi Gemini, chuyển role lịch sử, JSON schema, token usage; lỗi 429/503/403/404/400, timeout, safety và phản hồi không hợp lệ | Đạt |

Kiểm tra hồi quy: `auction_bidding.php`, `auction_confirmation.php`, `auction_validation.php` đều PASS. Trang chủ, cửa hàng, sản phẩm, giỏ hàng, tài khoản, đấu giá, chi tiết phiên và `/wp-json/luxury/v1/*` trả 200; checkout chuyển hướng (302) khi giỏ hàng trống, như trước.

### Gọi Gemini thật (25/11/2026)

Key đã cấu hình trong `wp-config.php`. `models.get` trả 200, tức key hợp lệ. Danh sách model được lấy bằng `models.list`; thử 3 model, mỗi model một request với cùng system prompt và JSON schema của chatbot:

| Model | Kết quả |
|---|---|
| `gemini-3.7-flash` | `HTTP 503 UNAVAILABLE` (Google báo quá tải) |
| `gemini-3.5-flash` | Trả JSON hợp lệ theo schema sau 23,7 giây (Google báo `modelVersion = gemini-3.6-flash`) |
| `gemini-2.5-flash` | Không thử, vì model trước đã đạt |

Kiểm tra qua REST của website với `gemini-3.5-flash` **không đạt**: câu tư vấn sản phẩm vượt timeout 30 giây (`ai_timeout`), các câu tiếp nối, bảo hành và đấu giá nhận `HTTP 503 UNAVAILABLE`. Vì vậy model **không được thay đổi**: cấu hình vẫn là `gemini-3.8-flash`. Lần thử được ghi trong option `luxury_chatbox_previous_model`. Model mặc định `gemini-3.8-flash` cũng nhận 503 (quá tải) ở mọi lần thử trước đó.

### Khắc phục 503 (25/11/2026, lần 2)

Lấy lại danh sách model và thử 3 model, mỗi model một request với `LC_Gemini_Provider`, cùng system prompt và schema của chatbot:

| Model | Kết quả |
|---|---|
| `gemini-3.5-flash` | **Hợp lệ** sau 21,7 giây |
| `gemini-2.5-flash` | `ai_bad_response`: trả văn bản không phải JSON. Model này không làm theo `responseFormat` mà provider dùng |
| `gemini-flash-latest` | `ai_unavailable`, `HTTP 503 UNAVAILABLE` |

Kiểm tra qua REST với `gemini-3.5-flash`, timeout 60 giây:

| Câu hỏi | Kết quả |
|---|---|
| Xin chào | 200, 19 giây, nguồn: Câu hỏi thường gặp |
| Đồng hồ nam dưới 30 triệu, thể thao | 200, 16,9 giây. Gợi ý Chrono Slate Automatic, Ocean GMT 24, Field Olive Automatic; giá, giá gốc, tồn kho và thông số khớp WooCommerce |
| Mẫu nào rẻ nhất, còn hàng không | 200, 16,4 giây. Field Olive Automatic 15.900.000 ₫, còn 9 chiếc, khớp WooCommerce; dùng đúng lịch sử hội thoại |

Lần chạy đầu, câu tiếp nối nhận 503, và lần thử lại thủ công với cùng `request_id` cũng nhận 503. Sau khi thêm cơ chế dự phòng, chạy lại cả ba câu trong phiên mới thì đều đạt. Cấu hình đã được cập nhật: model `gemini-3.5-flash`, timeout 60 giây (frontend tự đồng bộ 90 giây). Cấu hình cũ (`gemini-3.8-flash`, 30 giây) được lưu trong option `luxury_chatbox_previous_config`.

**Model dự phòng chưa được cấu hình**, vì trong 3 model được thử chỉ `gemini-3.5-flash` trả lời hợp lệ. Cơ chế dự phòng đã sẵn sàng; khi có model khác vượt qua cùng bài kiểm tra, nhập tên model đó vào WP Admin → Chatbox → Model dự phòng.

### Cơ chế model dự phòng

- Chỉ kích hoạt khi model chính trả **HTTP 503**. Lỗi 429, 500, timeout, lỗi định dạng không dùng model dự phòng.
- Thử model dự phòng **tối đa một lần** trong cùng request, giữ nguyên `request_id`. Chỉ một câu trả lời được lưu. `meta.fallback_used` và `meta.configured_model` ghi lại model đã trả lời.
- Mỗi lần gọi AI (chính, dự phòng, thử lại thủ công) cộng vào cột `messages.ai_calls`. Hạn mức mỗi ngày của phiên và toàn website tính theo tổng số lần gọi. Giới hạn mỗi phút vẫn tính theo số tin nhắn gửi. Nếu lần gọi dự phòng sẽ vượt hạn mức, nó bị bỏ qua.
- Cả hai model đều 503: trả `503 ai_unavailable` với thông báo "Trợ lý AI đang quá tải, kể cả máy chủ dự phòng…", `retryable: true`, `retry_after` tối thiểu 15 giây. Frontend hiển thị nút "Thử lại" có đếm ngược; không tự gọi lại.
- Thời gian chờ tối đa của một tin nhắn là `timeout × 2` khi có model dự phòng. Frontend chờ thêm 30 giây. Một request còn trong thời gian này không bị coi là bỏ dở, nên không bị gọi AI song song.

## 6b. Frontend chatbox

Widget nằm trong plugin, chỉ được nạp khi chatbot đang bật:

```
luxury-chatbox/
├── includes/class-lc-frontend.php   # enqueue CSS/JS, truyền restUrl, nonce, thời gian chờ
└── assets/
    ├── chatbox.css                  # giao diện, scoped dưới .lwc-root
    └── chatbox.js                   # widget JavaScript thuần, không thư viện
```

- Chỉ gọi `/wp-json/luxury-chat/v1/` (`status`, `messages` GET/POST/DELETE) với `credentials: 'same-origin'`. Thành viên gửi `X-WP-Nonce`; khách dùng cookie phiên do backend cấp.
- Kiểm tra `GET /status` trước khi cho gửi tin. Chatbot tắt và AI chưa cấu hình có thông báo riêng, khác với lỗi khi gọi AI.
- Mỗi tin nhắn mới có `request_id` riêng (`crypto.randomUUID`). Khi thử lại, hoặc gửi lại đúng nội dung vừa lỗi, widget dùng lại mã cũ. Nút gửi bị khóa trong lúc chờ. Widget không tự gửi lại.
- Lỗi có `retryable` hiển thị nút "Thử lại", bị khóa theo `retry_after`. Lỗi không thử lại được hiển thị nút "Sửa tin nhắn". Nội dung khách nhập được giữ lại trong ô nhập khi gửi thất bại.
- Lỗi phía trình duyệt: mất mạng, quá thời gian chờ (bằng tổng thời gian chờ AI của backend, gồm cả lần gọi dự phòng, cộng 30 giây; điều chỉnh qua filter `luxury_chatbox_frontend_config`), phản hồi không đọc được, nonce hết hạn.
- Toàn bộ nội dung hiển thị bằng `textContent`. Link và ảnh chỉ được tạo từ `products[].url/image`, `auctions[].url`, `sources[].url` khi URL cùng domain với website.
- Khôi phục lịch sử từ `GET /messages` khi mở chat. Trạng thái mở/đóng được nhớ trong `sessionStorage` của tab, nên sau khi tải lại trang, chat mở lại với đúng lịch sử.
- Desktop: cửa sổ 400px ở góc phải dưới. Mobile (≤ 640px): toàn màn hình, chiều cao theo `visualViewport` để ô nhập không bị bàn phím che, khóa cuộn trang nền. Nút mở nằm trên thanh điều hướng dưới của Storefront, và tự ẩn khi đè lên nút thêm vào giỏ, nút trả giá, đăng nhập để trả giá, thanh toán hoặc đặt hàng.
- Bàn phím: Enter gửi, Shift + Enter xuống dòng (bỏ qua khi đang gõ IME tiếng Việt), Esc đóng hoặc hủy xác nhận. Focus vào ô nhập khi mở và trả về nút mở khi đóng; giữ focus trong cửa sổ trên mobile. Có `role="dialog"`, `role="log"`, vùng `role="status"` cho trình đọc màn hình, và nhãn cho mọi nút biểu tượng.
- Khi khách đang đọc tin cũ, tin mới không kéo màn hình xuống mà hiện nút "Có tin nhắn mới".

### Kiểm thử frontend (giả lập AI)

`node scripts/tests/chatbox_frontend.js` (cần Chrome và `puppeteer-core`; đặt `PUPPETEER_CORE` nếu module không nằm trong `node_modules` của dự án). Script cài tạm `scripts/tests/fixtures/lc-mock-provider.php` làm mu-plugin để thay Gemini bằng AI giả lập trả lời từ dữ liệu website thật, rồi gỡ ra khi xong. REST, database và trình duyệt đều là thật; chỉ phần AI là giả lập. Một số lỗi (HTML không hợp lệ, 429, 504, treo request, nội dung độc hại) được giả lập bằng cách chặn request trong trình duyệt. Ảnh chụp lưu ở `docs/screenshots/`.

Kết quả 25/11/2026: **54/54 đạt** (desktop 1366×860, thành viên đăng nhập, mobile 390×844, hồi quy cửa hàng). Kiểm tra gồm mở/đóng, gợi ý, gửi tin, hỏi tiếp, tải lại trang, thẻ sản phẩm và phiên khớp backend, link gợi ý trả 200, thử lại khi quá tải/mất mạng với cùng `request_id`, chặn gửi lặp, các lỗi, hiển thị an toàn, trạng thái tắt/chưa cấu hình, xóa có xác nhận, chỉ báo tin mới, vị trí nút trên mobile, né nút giỏ hàng, bàn phím ảo, và các luồng giỏ hàng, thanh toán, tài khoản, trang trả giá không có lỗi JavaScript.

Lưu ý môi trường: WooCommerce đang bật chế độ "Coming soon" cho trang cửa hàng (`woocommerce_coming_soon = yes`, chỉ trang cửa hàng). Khách chưa đăng nhập chỉ thấy trang "sắp ra mắt" ở trang sản phẩm, giỏ hàng và thanh toán. Test luồng mua hàng dùng một tài khoản Shop Manager tạm.

## 7. Ghi chú kỹ thuật

### Tối ưu thời gian phản hồi

Provider gửi mức suy luận `MINIMAL` cho các model Flash hỗ trợ gồm `gemini-3.5-flash` (được Google định tuyến sang 3.6), `gemini-3.6-flash`, `gemini-3-flash-preview`, `gemini-3.1-flash-lite` và `gemini-3.5-flash-lite`. `gemini-3.8-flash` dùng `LOW`, vì model này không hỗ trợ `MINIMAL`. Các model khác giữ cấu hình suy luận mặc định. Đối chiếu [tài liệu mức suy luận của Google](https://aistudio.google.com/docs/thinking).

Dữ liệu website trong prompt dùng JSON giữ nguyên tiếng Việt và dấu gạch chéo để tránh biến nội dung thành các chuỗi Unicode escape dài. JSON schema, dữ liệu giá/tồn kho và cơ chế hạn mức vẫn được kiểm tra bằng bộ test backend.

Sau thay đổi, bộ test backend đạt **95/95**. Một lượt đo lấy dữ liệu sản phẩm mất khoảng **27 ms**. Kiểm tra thật qua HTTP REST với cấu hình model `gemini-3.5-flash` ghi nhận lời chào **7,54 giây**, tư vấn sản phẩm **12,96 giây**, cả hai trả HTTP 200. Câu tiếp nối gặp giới hạn phía Google, trả `ai_busy` HTTP 429. Đây là số đo của một lượt thử, chưa đủ để kết luận độ trễ ổn định. Lần đo trước chỉnh sửa nhận HTTP 503 sau 14,21 giây, nên chưa có phép so sánh trước/sau trên cùng một câu trả lời thành công.

- Lớp `LC_Gemini_Provider` gọi `POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` qua WordPress HTTP API. Key truyền bằng header `x-goog-api-key`. Quy tắc nằm trong `systemInstruction`, lịch sử trong `contents` với role `user`/`model`. Định dạng JSON được cấu hình bằng `generationConfig.responseFormat.text` với `mimeType = APPLICATION_JSON` và `schema`. Giới hạn phản hồi là 4096 token. Xem [tài liệu REST Gemini](https://aistudio.google.com/api-reference/generate-content).
- Model mặc định là `gemini-3.8-flash`, có thể đổi trong quản trị. Cấu hình model Claude đã lưu sẽ được đọc thành model Gemini mặc định, các hạn mức khác tiếp tục được giữ. Khi lưu lại trang cấu hình, tên model Gemini được ghi vào database.
- Gemini trả HTTP 429 được ánh xạ thành `ai_busy`, dùng header `Retry-After` nếu có. Mã `ai_quota_exceeded` tiếp tục phục vụ hạn mức do chatbot quản lý.
- Giới hạn tốc độ được đếm từ bảng tin nhắn, nên đúng trên nhiều tiến trình PHP mà không cần object cache. Khi nhiều request đến cùng lúc, số đếm có thể vượt hạn mức một vài lượt.
- Khách đăng nhập sau khi đã chat với tư cách khách sẽ bắt đầu hội thoại mới gắn với tài khoản; hội thoại khách cũ tự hết hạn theo thời gian lưu.
