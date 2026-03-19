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

$leads_table_name = $wpdb->prefix . 'formy_leads';
$lead_stats = array(
	'total'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads_table_name}" ),
	'unread' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads_table_name} WHERE status = %s", 'unread' ) ),
	'read'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads_table_name} WHERE status = %s", 'read' ) ),
);
?>

<div class="wrap">
	<style>
		.wp-formy-admin-shell {
			margin-top: 18px;
		}

		.wp-formy-admin-hero {
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			gap: 20px;
			padding: 28px 30px;
			background: linear-gradient(135deg, #fff 0%, #f8fafc 48%, #eff6ff 100%);
			border: 1px solid #dde7f2;
			border-radius: 26px;
			box-shadow: 0 22px 48px rgba(15, 23, 42, 0.06);
		}

		.wp-formy-admin-hero h1 {
			margin: 0 0 10px;
			font-size: 32px;
			line-height: 1.1;
		}

		.wp-formy-admin-hero p {
			margin: 0;
			max-width: 700px;
			color: #5f6b7a;
			font-size: 15px;
			line-height: 1.7;
		}

		.wp-formy-stats-grid {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 14px;
			margin: 18px 0 22px;
		}

		.wp-formy-stat-card {
			padding: 18px 20px;
			background: #fff;
			border: 1px solid #e4ebf3;
			border-radius: 20px;
			box-shadow: 0 10px 26px rgba(15, 23, 42, 0.04);
		}

		.wp-formy-stat-card strong {
			display: block;
			font-size: 28px;
			line-height: 1;
			color: #0f172a;
			margin-bottom: 8px;
		}

		.wp-formy-stat-card span {
			color: #64748b;
			font-weight: 600;
		}

		.wp-formy-filter-shell,
		.wp-formy-list-shell {
			background: #fff;
			border: 1px solid #e4ebf3;
			border-radius: 24px;
			box-shadow: 0 18px 42px rgba(15, 23, 42, 0.05);
		}

		.wp-formy-filter-shell {
			padding: 18px 20px;
			margin-bottom: 16px;
		}

		.wp-formy-filter-grid {
			display: flex;
			align-items: end;
			gap: 12px;
			flex-wrap: wrap;
		}

		.wp-formy-filter-grid select {
			min-width: 220px;
		}

		.wp-formy-filter-grid label {
			display: flex;
			flex-direction: column;
			gap: 8px;
			font-weight: 600;
			color: #334155;
		}

		.wp-formy-list-shell {
			padding: 20px;
		}

		.wp-formy-list-shell .wp-list-table {
			border: 0;
		}

		.wp-formy-list-shell .wp-list-table thead th,
		.wp-formy-list-shell .wp-list-table tfoot th {
			background: #f8fafc;
			padding-top: 14px;
			padding-bottom: 14px;
		}

		.wp-formy-list-shell .wp-list-table tbody td {
			padding-top: 16px;
			padding-bottom: 16px;
			vertical-align: middle;
		}

		.wp-formy-list-shell .wp-list-table tbody tr:hover {
			background: #fbfdff;
		}

		.wp-formy-entry-id-chip {
			display: inline-flex;
			padding: 6px 10px;
			border-radius: 999px;
			background: #eff6ff;
			color: #1d4ed8;
			font-weight: 700;
			font-size: 12px;
		}

		.wp-formy-form-title-cell {
			display: flex;
			flex-direction: column;
			gap: 4px;
		}

		.wp-formy-form-title-cell strong {
			font-size: 14px;
			color: #0f172a;
		}

		.wp-formy-form-title-cell span,
		.wp-formy-summary-line {
			color: #64748b;
		}

		.wp-formy-status-badge {
			display: inline-flex;
			align-items: center;
			padding: 6px 10px;
			border-radius: 999px;
			font-size: 12px;
			font-weight: 700;
		}

		.wp-formy-status-badge.unread {
			background: #fef3c7;
			color: #92400e;
		}

		.wp-formy-status-badge.read {
			background: #eff6ff;
			color: #1d4ed8;
		}

		.wp-formy-inline-note {
			margin-top: 14px;
			color: #64748b;
			font-size: 13px;
		}

		@media (max-width: 1000px) {
			.wp-formy-admin-hero {
				flex-direction: column;
			}

			.wp-formy-stats-grid {
				grid-template-columns: 1fr;
			}
		}
	</style>

	<div class="wp-formy-admin-shell">
		<div class="wp-formy-admin-hero">
			<div>
				<h1><?php esc_html_e( 'Entries', 'wp-formy' ); ?></h1>
				<p><?php esc_html_e( 'Review submissions, jump straight into editable entry details, and keep unread leads visible without losing context.', 'wp-formy' ); ?></p>
			</div>
		</div>

		<div class="wp-formy-stats-grid">
			<div class="wp-formy-stat-card"><strong><?php echo esc_html( $lead_stats['total'] ); ?></strong><span><?php esc_html_e( 'Total Entries', 'wp-formy' ); ?></span></div>
			<div class="wp-formy-stat-card"><strong><?php echo esc_html( $lead_stats['unread'] ); ?></strong><span><?php esc_html_e( 'Unread', 'wp-formy' ); ?></span></div>
			<div class="wp-formy-stat-card"><strong><?php echo esc_html( $lead_stats['read'] ); ?></strong><span><?php esc_html_e( 'Read', 'wp-formy' ); ?></span></div>
		</div>
	</div>

	<div class="wp-formy-filter-shell">
		<form method="get">
			<input type="hidden" name="page" value="wp-formy-leads" />

			<div class="wp-formy-filter-grid">
				<label>
					<span><?php esc_html_e( 'Status', 'wp-formy' ); ?></span>
					<select name="lead_status">
						<option value=""><?php esc_html_e( 'All statuses', 'wp-formy' ); ?></option>
						<option value="unread" <?php selected( $selected_status, 'unread' ); ?>><?php esc_html_e( 'Unread', 'wp-formy' ); ?></option>
						<option value="read" <?php selected( $selected_status, 'read' ); ?>><?php esc_html_e( 'Read', 'wp-formy' ); ?></option>
					</select>
				</label>

				<label>
					<span><?php esc_html_e( 'Form', 'wp-formy' ); ?></span>
					<select name="form_id">
						<option value="0"><?php esc_html_e( 'All Forms', 'wp-formy' ); ?></option>
						<?php foreach ( $forms as $form ) : ?>
							<option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $selected_form, $form->id ); ?>>
								<?php echo esc_html( $form->title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filters', 'wp-formy' ); ?></button>
			</div>
		</form>
	</div>

	<div class="wp-formy-list-shell">
		<form method="post">
			<input type="hidden" name="page" value="wp-formy-leads" />
			<?php $leads_list_table->display(); ?>
		</form>
		<p class="wp-formy-inline-note"><?php esc_html_e( 'Tip: click any row to open that entry in edit mode.', 'wp-formy' ); ?></p>
	</div>
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
