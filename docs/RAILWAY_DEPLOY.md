# Deploy Luxury Watches lên Railway (tham khảo)

> **Phương án triển khai đang được chọn là Render** – xem [RENDER_DEPLOY.md](RENDER_DEPLOY.md).
> Hướng dẫn Railway dưới đây chỉ để tham khảo; `railway.toml` vẫn được giữ trong repository.

Hướng dẫn Railway nằm trong [DOCKER_DEPLOY.md](DOCKER_DEPLOY.md):

- [Mục 9 – Deploy lên Railway](DOCKER_DEPLOY.md#9-deploy-lên-railway): service, biến môi trường, volume uploads, đưa database lên.
- [Mục 10 – Domain HTTPS](DOCKER_DEPLOY.md#10-domain-https)
- [Mục 11 – Webhook SePay](DOCKER_DEPLOY.md#11-webhook-sepay-sau-khi-deploy)

Thay đổi so với cấu hình cũ: không còn dùng `WORDPRESS_CONFIG_EXTRA`; đặt `WP_HOME`/`WP_SITEURL`
trực tiếp. Healthcheck chuyển sang `/healthz.php`. Uploads cần volume Railway.
