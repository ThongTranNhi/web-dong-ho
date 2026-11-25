# Thiết kế quản lý đơn hàng trong dashboard đấu giá

## Mục tiêu

Thêm mục **Đơn hàng** vào `/quan-tri-dau-gia/` để quản trị viên cửa hàng tìm kiếm, lọc, xem chi tiết và xử lý các đơn WooCommerce mà không cần chuyển sang WP Admin.

## Phạm vi

- Danh sách đơn theo mã đơn, tên, email hoặc số điện thoại; lọc theo trạng thái.
- Trang chi tiết hiển thị khách hàng, sản phẩm, tổng tiền, phương thức thanh toán, mã giao dịch và lịch sử ghi chú.
- Cho phép chuyển sang các trạng thái WooCommerce: chờ thanh toán, tạm giữ, đang xử lý, hoàn tất, đã hủy và thất bại.
- Thao tác **Xác nhận đã thanh toán** gọi quy trình `payment_complete()` của WooCommerce, có thể lưu mã giao dịch và bắt buộc nhập lý do.
- Mọi thay đổi bắt buộc có nonce, quyền `manage_woocommerce`, hộp xác nhận và ghi chú đơn chứa người thao tác cùng lý do.

## Ngoài phạm vi

- Không chuyển tiền, thu tiền hoặc hoàn tiền thật.
- Không sửa trực tiếp bảng dữ liệu WooCommerce.
- Không cho phép thay đổi đơn từ phía khách hàng.

## Cách tiếp cận

1. Đổi trạng thái nhanh trong bảng: ít thao tác nhưng dễ nhầm và thiếu ngữ cảnh.
2. Danh sách cộng trang chi tiết: được chọn vì an toàn hơn cho thao tác thanh toán và vẫn giữ dashboard thống nhất.
3. Dẫn sang WooCommerce Admin: không đáp ứng mục tiêu quản trị trong trang riêng.

## Quy tắc an toàn

- Đơn phải tồn tại và người dùng phải có quyền sửa đơn.
- Lý do tối thiểu 5 ký tự sau khi bỏ khoảng trắng.
- Chỉ chấp nhận danh sách trạng thái đã định nghĩa; không nhận trạng thái tùy ý từ request.
- `payment_complete()` chỉ dùng cho thao tác xác nhận đã thanh toán; các thay đổi khác dùng `update_status()`.
- “Đã hoàn tiền” không được cung cấp như một thao tác vì đổi trạng thái không tự hoàn tiền.
- Sau POST luôn redirect để tránh gửi lặp khi tải lại trang.

