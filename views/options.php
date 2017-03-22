<?php
/**
 * Options Page View.
 * @package Writing_On_GitHub
 */

?>
<div class="wrap">
	<h2><?php esc_html_e( 'Writing On GitHub', 'writing-on-github' ); ?></h2>

	<form method="post" action="options.php">
		<?php settings_fields( Writing_On_GitHub::$text_domain ); ?>
		<?php do_settings_sections( Writing_On_GitHub::$text_domain ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Webhook callback', 'writing-on-github' ); ?></th>
				<td><code><?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=wogh_push_request</code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Bulk actions', 'writing-on-github' ); ?></th>
				<td>
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'export' ) ) ); ?>">
						<?php esc_html_e( 'Export to GitHub', 'writing-on-github' ); ?>
					</a> |
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'import' ) ) ); ?>">
						<?php esc_html_e( 'Import from GitHub', 'writing-on-github' ); ?>
					</a>
				</td>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
