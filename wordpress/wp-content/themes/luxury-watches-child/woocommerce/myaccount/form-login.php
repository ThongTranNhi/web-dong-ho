<?php
/**
 * Customer login form, customized for Haute Horlogerie VIP Member Portal.
 *
 * @package Luxury_Watches_Atelier
 * @version 9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_customer_login_form' );
?>

<div class="lw-account-portal">
	<!-- Cột Trái: Đặc quyền Atelier VIP Club -->
	<div class="lw-account-vip-card">
		<span class="lw-account-kicker">WATCHSTORE ATELIER · VIP CLUB</span>
		<h2 class="lw-account-vip-title">Không Gian Dành Riêng Cho Nhà Sưu Tầm</h2>
		<p class="lw-account-vip-desc">Đăng nhập tài khoản thành viên để tận hưởng trọn vẹn hệ sinh thái dịch vụ Haute Horlogerie chuẩn 5 sao.</p>
		
		<div class="lw-account-benefits">
			<div class="lw-account-benefit-item">
				<span class="lw-benefit-icon">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14 13 5 5m-4.5-2.5 2 2"/><path d="m16 8-8 8"/><path d="m3 21 3-3"/><path d="m9.5 6.5 2 2-3.5 3.5-2-2 3.5-3.5z"/><path d="m14.5 1.5 5 5-2 2-5-5 2-2z"/></svg>
				</span>
				<div>
					<strong>Đặc quyền tham gia Sàn đấu giá live</strong>
					<span>Đặt giá trực tiếp, theo dõi lịch sử trả giá các phiên độc bản</span>
				</div>
			</div>
			
			<div class="lw-account-benefit-item">
				<span class="lw-benefit-icon">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
				</span>
				<div>
					<strong>Hồ sơ bảo dưỡng &amp; Bảo hành 5 năm</strong>
					<span>Theo dõi lịch sử lau dầu, căn chỉnh sai số máy cơ trọn đời</span>
				</div>
			</div>
			
			<div class="lw-account-benefit-item">
				<span class="lw-benefit-icon">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
				</span>
				<div>
					<strong>Ưu tiên bàn giao hỏa tốc 2 giờ</strong>
					<span>Miễn phí 100% vận chuyển chuyên biệt và tiếp đón tại phòng VIP</span>
				</div>
			</div>
			
			<div class="lw-account-benefit-item">
				<span class="lw-benefit-icon">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
				</span>
				<div>
					<strong>Chuyên viên Concierge phục vụ riêng</strong>
					<span>Tư vấn chọn size, tìm kiếm phiên bản hiếm theo yêu cầu cá nhân</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Cột Phải: Khung Đăng Nhập Hội Viên -->
	<div class="lw-account-login-card">
		<span class="lw-account-login-kicker">CỔNG BẢO MẬT KHÁCH HÀNG</span>
		<h2 class="lw-account-login-title">Đăng Nhập Hội Viên</h2>
		
		<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>
			<?php do_action( 'woocommerce_login_form_start' ); ?>
			
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="username">Email hoặc tên đăng nhập&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" placeholder="email@domain.com" value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" />
			</p>
			
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password">Mật khẩu truy cập&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" placeholder="••••••••" required aria-required="true" />
			</p>

			<?php do_action( 'woocommerce_login_form' ); ?>
			
			<div class="lw-login-action-row">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span>Ghi nhớ mật khẩu</span>
				</label>
				<p class="woocommerce-LostPassword lost_password">
					<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Quên mật khẩu?</a>
				</p>
			</div>
			
			<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
			<button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="Đăng nhập">ĐĂNG NHẬP HỘI VIÊN</button>
			
			<?php do_action( 'woocommerce_login_form_end' ); ?>
		</form>
	</div>
</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
