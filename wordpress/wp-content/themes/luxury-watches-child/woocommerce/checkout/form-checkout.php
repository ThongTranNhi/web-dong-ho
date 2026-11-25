<?php
/**
 * Checkout Form - Haute Horlogerie 2-Column Architecture
 *
 * @package Luxury_Watches_Atelier
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout lw-checkout-form" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

	<div class="lw-checkout-columns">
		<!-- Cột Trái: Thông tin giao hàng & người nhận -->
		<div class="lw-checkout-col lw-checkout-col--details" id="customer_details">
			<div class="lw-checkout-box">
				<div class="lw-checkout-box__header">
					<span class="lw-checkout-box__step">01</span>
					<h3 class="lw-checkout-box__title">Thông tin giao hàng &amp; Khách hàng</h3>
				</div>
				
				<?php if ( $checkout->get_checkout_fields() ) : ?>
					<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
					
					<div class="lw-billing-shipping-wrap">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</div>
					
					<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- Cột Phải (Sticky): Tóm tắt đơn hàng & Thanh toán -->
		<div class="lw-checkout-col lw-checkout-col--review">
			<div class="lw-checkout-review-sticky">
				<div class="lw-checkout-box lw-checkout-box--dark" id="order_review">
					<div class="lw-checkout-box__header lw-checkout-box__header--dark">
						<span class="lw-checkout-box__step lw-checkout-box__step--gold">02</span>
						<h3 class="lw-checkout-box__title lw-checkout-box__title--gold">Tóm tắt đơn hàng &amp; Thanh toán</h3>
					</div>
					
					<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
					
					<div class="woocommerce-checkout-review-order-table-wrap">
						<?php do_action( 'woocommerce_checkout_order_review' ); ?>
					</div>
					
					<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
				</div>
			</div>
		</div>
	</div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
