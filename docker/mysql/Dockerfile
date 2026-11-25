# MySQL 8.4 cho Render Private Service.
# Disk Render gắn ở /var/lib/mysql; dữ liệu nằm trong /var/lib/mysql/data vì thư mục gốc của
# disk có thể chứa lost+found, khiến MySQL từ chối khởi tạo.
FROM mysql:8.4.13

COPY render.cnf /etc/mysql/conf.d/render.cnf
RUN chmod 644 /etc/mysql/conf.d/render.cnf

EXPOSE 3306
