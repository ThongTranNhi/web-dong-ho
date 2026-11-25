<?php
/**
 * Review order table - Haute Horlogerie Luxury Enterprise Edition
 *
 * @package Luxury_Watches_Atelier
 * @version 11.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="shop_table woocommerce-checkout-review-order-table lw-checkout-review-table">
	<thead>
		<tr>
			<th class="product-name"><?php esc_html_e( 'SẢN PHẨM', 'woocommerce' ); ?></th>
			<th class="product-total"><?php esc_html_e( 'GIÁ TIỀN', 'woocommerce' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			$visible = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );

			if ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) {
				$thumbnail = $_product->get_image( [54, 54], [ 'class' => 'lw-review-thumb-img', 'loading' => 'eager' ] );
				?>
				<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item lw-review-item-row', $cart_item, $cart_item_key ) ); ?>">
					<td class="product-name">
						<div class="lw-review-product-flex">
							<div class="lw-review-thumb-wrap">
								<?php echo $thumbnail ? $thumbnail : '<span class="lw-review-thumb-placeholder"></span>'; ?>
							</div>
							<div class="lw-review-meta-wrap">
								<span class="lw-review-product-name"><?php echo esc_html( $_product->get_name() ); ?></span>
								<span class="lw-review-product-qty">&times;&nbsp;<?php echo esc_html( $cart_item['quantity'] ); ?></span>
								<?php echo wc_get_formatted_cart_item_data( $cart_item ); ?>
							</div>
						</div>
					</td>
					<td class="product-total lw-review-price-cell">
						<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?>
					</td>
				</tr>
				<?php
			}
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</tbody>
	<tfoot>

		<tr class="cart-subtotal lw-review-subtotal-row">
			<th><?php esc_html_e( 'TẠM TÍNH', 'woocommerce' ); ?></th>
			<td class="lw-review-price-cell"><?php wc_cart_totals_subtotal_html(); ?></td>
		</tr>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
				<td class="lw-review-price-cell"><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

			<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>

			<tr class="woocommerce-shipping-totals shipping lw-review-shipping-row">
				<th><?php esc_html_e( 'VẬN CHUYỂN', 'woocommerce' ); ?></th>
				<td class="lw-review-price-cell"><?php wc_cart_totals_shipping_html(); ?></td>
			</tr>

			<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>

		<?php endif; ?>

		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<tr class="fee">
				<th><?php echo esc_html( $fee->name ); ?></th>
				<td class="lw-review-price-cell"><?php wc_cart_totals_fee_html( $fee ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
					<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<th><?php echo esc_html( $tax->label ); ?></th>
						<td class="lw-review-price-cell"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="tax-total">
					<th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
					<td class="lw-review-price-cell"><?php wc_cart_totals_taxes_total_html(); ?></td>
				</tr>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<tr class="order-total lw-review-total-row">
			<th><?php esc_html_e( 'TỔNG CỘNG', 'woocommerce' ); ?></th>
			<td class="lw-review-price-cell lw-review-total-amount"><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

	</tfoot>
</table>
