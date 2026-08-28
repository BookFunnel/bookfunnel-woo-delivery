<?php
/**
 * Delivery block template — shared by the thank-you page and order emails.
 *
 * Uses inline styles only: this template is echoed into transactional emails,
 * which don't reliably support <style> blocks or enqueued stylesheets.
 *
 * Expects $delivery (array from BF_WC_Delivery_Link::get_delivery_data()) in scope.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;

?>
<div style="border:1px solid #ddd; border-radius:4px; padding:16px; margin:16px 0;">
	<p style="font-weight:bold; margin:0 0 8px;"><?php echo esc_html( $delivery['title'] ); ?></p>
	<p style="margin:0 0 16px;"><?php echo esc_html( $delivery['body_text'] ); ?></p>
	<p style="margin:0 0 16px;">
		<a href="<?php echo esc_url( $delivery['link_url'] ); ?>" target="_blank" rel="noopener noreferrer"
			style="display:inline-block; background:#f7941e; color:#fff; padding:10px 20px; border-radius:4px; text-decoration:none;">
			<?php echo esc_html( $delivery['button_text'] ); ?>
		</a>
	</p>
	<img src="<?php echo esc_url( $delivery['delivered_by_logo_url'] ); ?>" alt="<?php echo esc_attr( $delivery['delivered_by'] ); ?>" style="max-width:144px; height:auto;" />
</div>
