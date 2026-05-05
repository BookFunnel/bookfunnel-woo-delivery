<?php
/**
 * Admin page template.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap bf-wc-admin-page">
			<?php $bf_wc_show_connected_notice = isset( $_GET['bf_connected'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['bf_connected'] ) ) && $is_connected; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display parameter set after verified connect flow ?>

		<h1><?php echo esc_html__( 'BookFunnel', 'bookfunnel' ); ?></h1>

	<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( $settings_tab_url ); ?>" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__( 'Settings', 'bookfunnel' ); ?></a>
				<a href="<?php echo esc_url( $deliveries_tab_url ); ?>" class="nav-tab <?php echo 'deliveries' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__( 'Deliveries', 'bookfunnel' ); ?></a>
			<a href="<?php echo esc_url( $logs_tab_url ); ?>" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__( 'Logs', 'bookfunnel' ); ?></a>
	</nav>

	<?php if ( 'settings' === $active_tab ) : ?>
				<?php if ( $bf_wc_show_connected_notice ) : ?>
				<div class="notice notice-success inline"><p><?php echo esc_html__( 'Successfully connected to BookFunnel!', 'bookfunnel' ); ?></p></div>
					<?php if ( $has_ping_warning ) : ?>
					<div class="notice notice-warning inline"><p><?php echo esc_html__( 'Connected to BookFunnel, but we could not reach the delivery server. Orders will be retried automatically. Contact BookFunnel support if this persists.', 'bookfunnel' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>
		<div class="bf-wc-card bf-wc-stack">
			<div class="bf-wc-header">
				<?php if ( ! empty( $logo_url ) ) : ?>
						<img class="bf-wc-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr__( 'BookFunnel', 'bookfunnel' ); ?>" />
				<?php else : ?>
						<h2><?php echo esc_html__( 'BookFunnel', 'bookfunnel' ); ?></h2>
				<?php endif; ?>
			</div>

				<p><?php echo esc_html__( 'Connect WooCommerce orders to BookFunnel so readers automatically receive their delivery links after purchase.', 'bookfunnel' ); ?></p>

			<?php if ( ! $is_connected ) : ?>
					<p>
					<?php
					echo wp_kses(
						__( 'For the best experience, make sure you\'re signed into your BookFunnel author account before connecting. <a href="https://dashboard.bookfunnel.com/login" target="_blank" rel="noopener noreferrer">Sign in to BookFunnel →</a>', 'bookfunnel' ),
						array(
							'a' => array(
								'href'   => true,
								'target' => true,
								'rel'    => true,
							),
						)
					);
					?>
						</p>
				<p><a class="button button-primary" href="<?php echo esc_url( $connect_url ); ?>"><?php echo esc_html__( 'Connect to BookFunnel', 'bookfunnel' ); ?></a></p>
				<p><a href="<?php echo esc_url( 'https://bookfunnel.com' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Don\'t have a BookFunnel account? Sign up at bookfunnel.com', 'bookfunnel' ); ?></a></p>
			<?php else : ?>
				<p class="bf-wc-status"><span class="bf-wc-status-indicator <?php echo esc_attr( $has_ping_warning ? 'bf-wc-status--warning' : 'bf-wc-status--connected' ); ?>"></span><?php echo esc_html( $has_ping_warning ? __( 'Connected, but the last ping failed.', 'bookfunnel' ) : __( 'Connected to BookFunnel.', 'bookfunnel' ) ); ?></p>
					<details class="bf-wc-info-box">
						<summary><?php echo esc_html__( 'Previously connected WooCommerce to BookFunnel?', 'bookfunnel' ); ?></summary>
						<p>
						<?php
						echo wp_kses(
							__( 'If you set up a WooCommerce integration in your BookFunnel dashboard before installing this plugin, please <a href="https://dashboard.bookfunnel.com/sales" target="_blank" rel="noopener noreferrer">remove that connection in your BookFunnel Sales settings</a> to avoid duplicate book deliveries.', 'bookfunnel' ),
							array(
								'a' => array(
									'href'   => true,
									'target' => true,
									'rel'    => true,
								),
							)
						);
						?>
							</p>
					</details>
					<?php if ( get_option( 'bf_wc_legacy_connection_warning' ) ) : ?>
						<div class="notice notice-warning inline">
							<p><strong><?php echo esc_html__( 'Multiple WooCommerce connections may be active.', 'bookfunnel' ); ?></strong> 
							<?php
							echo wp_kses(
								__( 'Your BookFunnel account may have a previous WooCommerce connection that could cause duplicate book deliveries. Please <a href="https://dashboard.bookfunnel.com/sales" target="_blank" rel="noopener noreferrer">visit your BookFunnel Sales settings</a> and remove any manual WooCommerce connections that are not managed by this plugin.', 'bookfunnel' ),
								array(
									'a' => array(
										'href'   => true,
										'target' => true,
										'rel'    => true,
									),
								)
							);
							?>
										</p>
						</div>
					<?php endif; ?>

				<div>
						<label class="bf-wc-field-label" for="bf-wc-webhook-uid"><?php echo esc_html__( 'Webhook UID', 'bookfunnel' ); ?></label>
					<input id="bf-wc-webhook-uid" class="regular-text code bf-wc-readonly" type="text" value="<?php echo esc_attr( $webhook_uid ); ?>" readonly="readonly" />
				</div>

						<p><a href="<?php echo esc_url( 'https://dashboard.bookfunnel.com/sales' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Manage delivery settings in BookFunnel', 'bookfunnel' ); ?></a></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" class="bf-wc-stack">
					<?php settings_fields( BF_WC_Admin::SETTINGS_GROUP ); ?>
					<input type="hidden" name="bf_wc_email_injection" value="0" />
					<label for="bf-wc-email-injection">
						<input id="bf-wc-email-injection" type="checkbox" name="bf_wc_email_injection" value="1" <?php checked( $email_injection_enabled ); ?> />
							<?php echo esc_html__( 'Include BookFunnel delivery notification in order confirmation emails', 'bookfunnel' ); ?>
					</label>
						<?php submit_button( __( 'Save Settings', 'bookfunnel' ) ); ?>
				</form>

				<div class="bf-wc-actions">
					<form method="post" action="<?php echo esc_url( $settings_tab_url ); ?>">
						<?php wp_nonce_field( 'bf_wc_disconnect_action', 'bf_wc_disconnect_nonce' ); ?>
						<input type="hidden" name="bf_wc_admin_action" value="disconnect" />
							<?php submit_button( __( 'Disconnect', 'bookfunnel' ), 'delete', 'submit', false ); ?>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php elseif ( 'deliveries' === $active_tab ) : ?>
			<?php
				$bf_wc_event_type_labels = array(
					'order.completed' => __( 'Completed', 'bookfunnel' ),
					'order.cancelled' => __( 'Cancelled', 'bookfunnel' ),
					'order.refunded'  => __( 'Refunded', 'bookfunnel' ),
				);
				?>
			<div class="bf-wc-card bf-wc-stack">
				<h2><?php echo esc_html__( 'Deliveries', 'bookfunnel' ); ?></h2>

				<details class="bf-wc-info-box">
					<summary><?php echo esc_html__( 'About refunds and BookFunnel access', 'bookfunnel' ); ?></summary>
					<p><?php echo esc_html__( 'When a refund is issued for an order containing BookFunnel items, access to all BookFunnel deliveries for that order will be revoked automatically. If you need to restore access after a partial refund, you can do so manually from your BookFunnel dashboard.', 'bookfunnel' ); ?></p>
				</details>

				<?php if ( $delivery_counts['all'] > 0 ) : ?>
					<?php
					$bf_wc_delivery_filters = array(
						'all'       => __( 'All', 'bookfunnel' ),
						'pending'   => __( 'Pending', 'bookfunnel' ),
						'failed'    => __( 'Failed', 'bookfunnel' ),
						'done'      => __( 'Done', 'bookfunnel' ),
						'abandoned' => __( 'Abandoned', 'bookfunnel' ),
					);
					?>
					<div class="bf-wc-filter-bar">
						<?php foreach ( $bf_wc_delivery_filters as $bf_wc_filter_key => $bf_wc_filter_label ) : ?>
							<?php $bf_wc_filter_url = 'all' === $bf_wc_filter_key ? $deliveries_tab_url : add_query_arg( 'delivery_status', $bf_wc_filter_key, $deliveries_tab_url ); ?>
							<a class="bf-wc-filter-link <?php echo $delivery_filter === $bf_wc_filter_key ? 'bf-wc-filter-link--active' : ''; ?>" href="<?php echo esc_url( $bf_wc_filter_url ); ?>"><?php echo esc_html( sprintf( '%1$s (%2$d)', $bf_wc_filter_label, (int) $delivery_counts[ $bf_wc_filter_key ] ) ); ?></a>
						<?php endforeach; ?>
					</div>

					<?php if ( ! empty( $delivery_rows ) ) : ?>
						<table class="widefat striped">
							<thead>
							<tr>
								<th><?php echo esc_html__( 'Order ID', 'bookfunnel' ); ?></th>
								<th><?php echo esc_html__( 'Event Type', 'bookfunnel' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'bookfunnel' ); ?></th>
								<th><?php echo esc_html__( 'Attempts', 'bookfunnel' ); ?></th>
								<th><?php echo esc_html__( 'Last Updated', 'bookfunnel' ); ?></th>
								<th><?php echo esc_html__( 'Actions', 'bookfunnel' ); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $delivery_rows as $bf_wc_delivery_row ) : ?>
								<?php $bf_wc_status_class = in_array( $bf_wc_delivery_row['status'], array( 'pending', 'failed', 'done', 'abandoned' ), true ) ? $bf_wc_delivery_row['status'] : 'pending'; ?>
								<tr>
									<td><?php echo esc_html( (string) $bf_wc_delivery_row['order_id'] ); ?></td>
									<td><?php echo esc_html( isset( $bf_wc_event_type_labels[ $bf_wc_delivery_row['event_type'] ] ) ? $bf_wc_event_type_labels[ $bf_wc_delivery_row['event_type'] ] : $bf_wc_delivery_row['event_type'] ); ?></td>
									<td><span class="bf-wc-delivery-badge bf-wc-delivery-badge--<?php echo esc_attr( $bf_wc_status_class ); ?>"><?php echo esc_html( (string) $bf_wc_delivery_row['status'] ); ?></span></td>
									<td><?php echo esc_html( (string) $bf_wc_delivery_row['attempts'] ); ?></td>
									<td><?php echo esc_html( (string) $bf_wc_delivery_row['updated_at'] ); ?></td>
									<td class="bf-wc-actions-cell">
										<?php if ( in_array( $bf_wc_delivery_row['status'], array( 'failed', 'abandoned' ), true ) ) : ?>
											<form method="post" action="<?php echo esc_url( $deliveries_tab_url ); ?>">
												<?php wp_nonce_field( 'bf_wc_resend_delivery_action', 'bf_wc_resend_delivery_nonce' ); ?>
												<input type="hidden" name="bf_wc_admin_action" value="resend_delivery" />
												<input type="hidden" name="bf_wc_notification_id" value="<?php echo esc_attr( (string) $bf_wc_delivery_row['id'] ); ?>" />
												<input type="hidden" name="bf_wc_delivery_filter" value="<?php echo esc_attr( $delivery_filter ); ?>" />
											<?php submit_button( __( 'Resend', 'bookfunnel' ), 'secondary', 'submit', false ); ?>
											</form>
										<?php else : ?>
											<span aria-hidden="true">—</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p><?php echo esc_html__( 'No deliveries match this filter.', 'bookfunnel' ); ?></p>
					<?php endif; ?>

					<?php if ( $delivery_counts['abandoned'] > 0 ) : ?>
						<form method="post" action="<?php echo esc_url( $deliveries_tab_url ); ?>">
							<?php wp_nonce_field( 'bf_wc_resend_abandoned_action', 'bf_wc_resend_abandoned_nonce' ); ?>
							<input type="hidden" name="bf_wc_admin_action" value="resend_abandoned" />
							<input type="hidden" name="bf_wc_delivery_filter" value="<?php echo esc_attr( $delivery_filter ); ?>" />
								<?php submit_button( __( 'Resend all abandoned', 'bookfunnel' ), 'secondary', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				<?php else : ?>
						<p><?php echo esc_html__( 'No delivery notifications have been sent yet.', 'bookfunnel' ); ?></p>
				<?php endif; ?>
			</div>
	<?php else : ?>
		<?php include BF_WC_PLUGIN_DIR . 'templates/admin-log-table.php'; ?>
	<?php endif; ?>
</div>