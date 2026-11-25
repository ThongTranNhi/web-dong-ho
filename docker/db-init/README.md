# Import database khi khởi tạo

Đặt file dump (`.sql` hoặc `.sql.gz`) vào thư mục này **trước** lần chạy
`docker compose up` đầu tiên. MySQL chỉ import khi volume database còn trống,
nên khởi động lại hay build lại sẽ không import lần nữa.

- File `.sql` bị Git và Docker build bỏ qua: không bao giờ commit dump chứa dữ liệu thật.
- Database đã có dữ liệu thì dùng `scripts/docker/db-import.sh` thay vì thư mục này.

Chi tiết: `docs/DOCKER_DEPLOY.md`.
