<?php
/**
 * Admin log table template.
 *
 * @package BookFunnelWooCommerce
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="bf-wc-card bf-wc-stack">
	<p class="bf-wc-note"><?php echo esc_html__( 'Logs are retained according to your WooCommerce log settings. Log history is important for troubleshooting with BookFunnel support.', 'bookfunnel' ); ?></p>
	<p><a class="bf-wc-log-link" href="<?php echo esc_url( $native_logs_url ); ?>"><?php echo esc_html__( 'Open the native WooCommerce log viewer', 'bookfunnel' ); ?></a></p>

	<?php if ( empty( $log_rows ) ) : ?>
		<p><?php echo esc_html__( 'No BookFunnel log entries were found.', 'bookfunnel' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
			<tr>
				<th><?php echo esc_html__( 'Timestamp', 'bookfunnel' ); ?></th>
				<th><?php echo esc_html__( 'Level', 'bookfunnel' ); ?></th>
				<th><?php echo esc_html__( 'Message', 'bookfunnel' ); ?></th>
				<th><?php echo esc_html__( 'Context', 'bookfunnel' ); ?></th>
			</tr>
			</thead>
			<tbody>
				<?php foreach ( $log_rows as $bf_wc_row ) : ?>
					<?php $bf_wc_level = strtolower( (string) $bf_wc_row['level'] ); ?>
				<tr>
						<td><?php echo esc_html( $bf_wc_row['timestamp'] ); ?></td>
						<td><span class="bf-wc-log-badge bf-wc-log-badge--<?php echo esc_attr( in_array( $bf_wc_level, array( 'info', 'warning', 'error' ), true ) ? $bf_wc_level : 'info' ); ?>"><?php echo esc_html( $bf_wc_level ); ?></span></td>
						<td><?php echo esc_html( $bf_wc_row['message'] ); ?></td>
					<td>
							<?php if ( '' !== trim( $bf_wc_row['context'] ) ) : ?>
							<details>
								<summary><?php echo esc_html__( 'View context', 'bookfunnel' ); ?></summary>
									<pre class="bf-wc-log-context"><?php echo esc_html( $bf_wc_row['context'] ); ?></pre>
							</details>
						<?php else : ?>
							&mdash;
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
