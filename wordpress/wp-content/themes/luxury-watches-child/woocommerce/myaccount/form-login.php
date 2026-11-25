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
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14 13-7.5 7.5c-.8.8-2 .8-2.8 0s-.8-2 0-2.8L11 10.2"/><path d="m16 16 6-6"/><path d="m8 8 6-6"/><path d="m9 7 8 8"/><path d="m21 11-8-8"/></svg>
				</span>
				<div>
					<strong>Đặc quyền tham gia Sàn đấu giá live</strong>
					<span>Đặt giá trực tiếp, theo dõi lịch sử trả giá các phiên độc bản</span>
				</div>
			</div>
			
			<div class="lw-account-benefit-item">
				<span class="lw-benefit-icon">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
				</span>
				<div>
					<strong>Hồ sơ bảo dưỡng &amp; Bảo hành 5 năm</strong>
					<span>Theo dõi lịch sử lau dầu, căn chỉnh sai số máy cơ trọn đời</span>
				</div>
			</div>
			
			<div class="lw-account-benefit-item">
				<span class="lw-benefit-icon">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
				</span>
				<div>
					<strong>Ưu tiên bàn giao hỏa tốc 2 giờ</strong>
					<span>Miễn phí 100% vận chuyển chuyên biệt và tiếp đón tại phòng VIP</span>
				</div>
			</div>
			
			<div class="lw-account-benefit-item">
				<span class="lw-benefit-icon">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></svg>
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
