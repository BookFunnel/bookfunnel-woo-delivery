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
		<div class="bf-wc-filter-bar" id="bf-wc-log-toolbar" data-site-url="<?php echo esc_attr( site_url() ); ?>">
			<label for="bf-wc-log-filter" class="screen-reader-text"><?php echo esc_html__( 'Filter logs', 'bookfunnel' ); ?></label>
			<select id="bf-wc-log-filter">
				<option value="7d"><?php echo esc_html__( 'Last 7 days', 'bookfunnel' ); ?></option>
				<option value="30d"><?php echo esc_html__( 'Last 30 days', 'bookfunnel' ); ?></option>
				<option value="50"><?php echo esc_html__( 'Last 50 entries', 'bookfunnel' ); ?></option>
				<option value="100"><?php echo esc_html__( 'Last 100 entries', 'bookfunnel' ); ?></option>
				<option value="errors"><?php echo esc_html__( 'Errors only', 'bookfunnel' ); ?></option>
			</select>
			<button type="button" id="bf-wc-log-copy" class="button"><?php echo esc_html__( 'Copy logs', 'bookfunnel' ); ?></button>
			<a id="bf-wc-log-email" class="button" href="<?php echo esc_url( 'mailto:support@bookfunnel.com?subject=' . rawurlencode( sprintf( 'BookFunnel WooCommerce Support Log — %s', site_url() ) ) ); ?>"><?php echo esc_html__( 'Email support', 'bookfunnel' ); ?></a>
		</div>
		<p class="bf-wc-note"><?php echo esc_html__( 'Click the link above to open an email to BookFunnel support, then paste your copied logs into the body. Or email us directly at support@bookfunnel.com.', 'bookfunnel' ); ?></p>

		<table class="widefat striped" id="bf-wc-log-table">
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
				<tr data-timestamp="<?php echo esc_attr( $bf_wc_row['timestamp'] ); ?>" data-level="<?php echo esc_attr( $bf_wc_level ); ?>">
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
		<p class="bf-wc-note" id="bf-wc-log-empty-filter" style="display:none;"><?php echo esc_html__( 'No log entries match this filter.', 'bookfunnel' ); ?></p>
	<?php endif; ?>
</div>
