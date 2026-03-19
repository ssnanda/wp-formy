<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$forms_table = $wpdb->prefix . 'formy_forms';
$forms       = $wpdb->get_results( "SELECT id, title FROM {$forms_table} ORDER BY title ASC" );

$selected_form   = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
$selected_status = isset( $_GET['lead_status'] ) ? sanitize_text_field( wp_unslash( $_GET['lead_status'] ) ) : '';

$leads_list_table = new WP_Formy_Leads_List_Table();
$leads_list_table->process_bulk_action();
$leads_list_table->prepare_items();
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Leads', 'wp-formy' ); ?></h1>
	<hr class="wp-header-end">

	<form method="get" style="margin:16px 0;">
		<input type="hidden" name="page" value="wp-formy-leads" />

		<select name="lead_status">
			<option value=""><?php esc_html_e( 'Status', 'wp-formy' ); ?></option>
			<option value="unread" <?php selected( $selected_status, 'unread' ); ?>><?php esc_html_e( 'Unread', 'wp-formy' ); ?></option>
			<option value="read" <?php selected( $selected_status, 'read' ); ?>><?php esc_html_e( 'Read', 'wp-formy' ); ?></option>
		</select>

		<select name="form_id">
			<option value="0"><?php esc_html_e( 'All Forms', 'wp-formy' ); ?></option>
			<?php foreach ( $forms as $form ) : ?>
				<option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $selected_form, $form->id ); ?>>
					<?php echo esc_html( $form->title ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'wp-formy' ); ?></button>
	</form>

	<form method="post">
		<input type="hidden" name="page" value="wp-formy-leads" />
		<?php $leads_list_table->display(); ?>
	</form>
</div>

<script>
(function() {
	const leadRows = document.querySelectorAll('.wp-formy-lead-row');

	if (!leadRows.length) {
		return;
	}

	leadRows.forEach(function(row) {
		row.style.cursor = 'pointer';

		row.addEventListener('click', function(e) {
			if (e.target.closest('a, button, input, select, textarea, label')) {
				return;
			}

			const detailUrl = row.getAttribute('data-detail-url');
			if (detailUrl) {
				window.location.href = detailUrl;
			}
		});
	});
})();
</script>
