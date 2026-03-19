<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$lead_id          = isset( $_GET['lead_id'] ) ? absint( wp_unslash( $_GET['lead_id'] ) ) : 0;
$leads_table      = $wpdb->prefix . 'formy_leads';
$forms_table      = $wpdb->prefix . 'formy_forms';
$lead_notes_table = $wpdb->prefix . 'formy_lead_notes';
$admin            = new WP_Formy_Admin();

$lead = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT l.*, f.title AS form_title
		FROM {$leads_table} l
		LEFT JOIN {$forms_table} f ON l.form_id = f.id
		WHERE l.id = %d",
		$lead_id
	)
);

if ( ! $lead ) {
	echo '<div class="wrap"><h1>' . esc_html__( 'Lead not found.', 'wp-formy' ) . '</h1></div>';
	return;
}

$form        = $lead->form_id ? $admin->get_form_record( $lead->form_id ) : null;
$form_fields = $lead->form_id ? $admin->get_form_schema_fields( $lead->form_id ) : array();
$notes       = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$lead_notes_table} WHERE lead_id = %d ORDER BY created_at DESC",
		$lead_id
	)
);

$data = json_decode( $lead->lead_data, true );
if ( ! is_array( $data ) ) {
	$data = array();
}

$field_map = array();
foreach ( $form_fields as $field ) {
	if ( is_array( $field ) && ! empty( $field['id'] ) ) {
		$field_map[ $field['id'] ] = $field;
	}
}

$orphan_fields = array();
foreach ( $data as $field_key => $field_value ) {
	if ( ! isset( $field_map[ $field_key ] ) ) {
		$orphan_fields[ $field_key ] = $field_value;
	}
}

$back_url = admin_url( 'admin.php?page=wp-formy-leads' );
$form_edit_url = $form ? add_query_arg(
	array(
		'page'    => 'wp-formy',
		'action'  => 'edit',
		'form_id' => absint( $form->id ),
	),
	admin_url( 'admin.php' )
) : '';

$toggle_action = ( 'unread' === $lead->status ) ? 'mark_read' : 'mark_unread';
$toggle_label  = ( 'unread' === $lead->status ) ? __( 'Mark as Read', 'wp-formy' ) : __( 'Mark as Unread', 'wp-formy' );
$toggle_url    = wp_nonce_url(
	add_query_arg(
		array(
			'page'        => 'wp-formy-leads',
			'view'        => 'detail',
			'lead_action' => $toggle_action,
			'lead_id'     => $lead_id,
		),
		admin_url( 'admin.php' )
	),
	'wpf_lead_action_' . $lead_id
);

$delete_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'        => 'wp-formy-leads',
			'lead_action' => 'delete',
			'lead_id'     => $lead_id,
		),
		admin_url( 'admin.php' )
	),
	'wpf_lead_action_' . $lead_id
);
?>

<div class="wrap">
	<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
		<div>
			<a href="<?php echo esc_url( $back_url ); ?>" style="text-decoration:none;">&larr; <?php esc_html_e( 'Back to Leads', 'wp-formy' ); ?></a>
			<h1 style="margin-top:10px;"><?php echo esc_html( 'Entry #' . $lead_id ); ?></h1>
		</div>
		<div class="wp-formy-inline-actions">
			<a class="button" href="<?php echo esc_url( $toggle_url ); ?>"><?php echo esc_html( $toggle_label ); ?></a>
			<?php if ( $form_edit_url ) : ?>
				<a class="button" href="<?php echo esc_url( $form_edit_url ); ?>"><?php esc_html_e( 'Edit Form', 'wp-formy' ); ?></a>
			<?php endif; ?>
			<a class="button button-link-delete" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('Delete this lead?');"><?php esc_html_e( 'Delete', 'wp-formy' ); ?></a>
		</div>
	</div>

	<?php if ( isset( $_GET['entry-message'] ) ) : ?>
		<?php $message = sanitize_text_field( wp_unslash( $_GET['entry-message'] ) ); ?>
		<div class="notice <?php echo ( isset( $_GET['entry-updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['entry-updated'] ) ) ) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['note-message'] ) ) : ?>
		<?php $note_message = sanitize_text_field( wp_unslash( $_GET['note-message'] ) ); ?>
		<div class="notice <?php echo ( isset( $_GET['note-updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['note-updated'] ) ) ) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
			<p><?php echo esc_html( $note_message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $form ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'This entry is linked to a form that no longer exists, so it cannot be edited safely.', 'wp-formy' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $orphan_fields ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'This entry still contains data for fields that are no longer present on the form.', 'wp-formy' ); ?>
				<?php if ( $form_edit_url ) : ?>
					<a href="<?php echo esc_url( $form_edit_url ); ?>"><?php esc_html_e( 'Open the form editor to correct the schema.', 'wp-formy' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="wp-formy-lead-grid">
		<div>
			<div class="wp-formy-lead-card">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Entry Data', 'wp-formy' ); ?></h2>

				<?php if ( $form ) : ?>
					<form method="post" enctype="multipart/form-data">
						<?php wp_nonce_field( 'wpf_update_lead_' . $lead_id, 'wpf_update_lead_nonce' ); ?>
						<input type="hidden" name="wpf_update_lead_id" value="<?php echo esc_attr( $lead_id ); ?>">

						<table class="wp-formy-lead-meta-table">
							<tbody>
								<?php foreach ( $form_fields as $field ) : ?>
									<?php
									if ( ! is_array( $field ) || empty( $field['id'] ) ) {
										continue;
									}

									$field_id    = $field['id'];
									$field_type  = ! empty( $field['type'] ) ? $field['type'] : 'text';
									$field_label = ! empty( $field['label'] ) ? $field['label'] : $field_id;
									$placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';
									$required    = ! empty( $field['required'] );
									$options     = ! empty( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
									$help_text   = ! empty( $field['help_text'] ) ? $field['help_text'] : '';
									$current     = isset( $data[ $field_id ] ) && is_array( $data[ $field_id ] ) ? $data[ $field_id ] : array();
									$value       = isset( $current['value'] ) ? $current['value'] : '';
									$file_name   = isset( $current['file_name'] ) ? $current['file_name'] : '';
									?>
									<?php if ( 'separator' === $field_type ) : ?>
										<tr>
											<td colspan="2"><hr></td>
										</tr>
										<?php continue; ?>
									<?php endif; ?>
									<tr>
										<th style="width:220px;">
											<?php echo esc_html( $field_label ); ?>
											<?php if ( $required ) : ?><span style="color:#d63638;">*</span><?php endif; ?>
										</th>
										<td>
											<?php if ( 'textarea' === $field_type ) : ?>
												<textarea name="<?php echo esc_attr( $field_id ); ?>" rows="4" style="width:100%;"><?php echo esc_textarea( is_string( $value ) ? $value : '' ); ?></textarea>
											<?php elseif ( 'select' === $field_type ) : ?>
												<select name="<?php echo esc_attr( $field_id ); ?>" style="width:100%;">
													<option value=""><?php echo esc_html( $placeholder ?: __( 'Select an option', 'wp-formy' ) ); ?></option>
													<?php foreach ( $options as $option ) : ?>
														<?php
														$option_label = is_array( $option ) && isset( $option['label'] ) ? $option['label'] : $option;
														$option_value = is_array( $option ) && isset( $option['value'] ) ? $option['value'] : $option_label;
														?>
														<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
													<?php endforeach; ?>
												</select>
											<?php elseif ( 'checkboxes' === $field_type ) : ?>
												<?php $checked_values = is_array( $value ) ? $value : array(); ?>
												<?php foreach ( $options as $option ) : ?>
													<?php
													$option_label = is_array( $option ) && isset( $option['label'] ) ? $option['label'] : $option;
													$option_value = is_array( $option ) && isset( $option['value'] ) ? $option['value'] : $option_label;
													?>
													<label style="display:block;margin-bottom:6px;">
														<input type="checkbox" name="<?php echo esc_attr( $field_id ); ?>[]" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( in_array( $option_value, $checked_values, true ) ); ?>>
														<?php echo esc_html( $option_label ); ?>
													</label>
												<?php endforeach; ?>
											<?php elseif ( 'multiple_choice' === $field_type ) : ?>
												<?php foreach ( $options as $option ) : ?>
													<?php
													$option_label = is_array( $option ) && isset( $option['label'] ) ? $option['label'] : $option;
													$option_value = is_array( $option ) && isset( $option['value'] ) ? $option['value'] : $option_label;
													?>
													<label style="display:block;margin-bottom:6px;">
														<input type="radio" name="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( $value, $option_value ); ?>>
														<?php echo esc_html( $option_label ); ?>
													</label>
												<?php endforeach; ?>
											<?php elseif ( 'file' === $field_type ) : ?>
												<?php if ( ! empty( $value ) ) : ?>
													<div style="margin-bottom:8px;">
														<a href="<?php echo esc_url( $value ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $file_name ? $file_name : basename( (string) $value ) ); ?></a>
													</div>
												<?php endif; ?>
												<input type="file" name="<?php echo esc_attr( $field_id ); ?>" style="width:100%;">
												<p class="description" style="margin-top:6px;"><?php esc_html_e( 'Upload a new file to replace the current one, or leave empty to keep the existing file.', 'wp-formy' ); ?></p>
											<?php else : ?>
												<?php
												$input_type_map = array(
													'email'   => 'email',
													'url'     => 'url',
													'number'  => 'number',
													'phone'   => 'tel',
													'tel'     => 'tel',
													'date'    => 'date',
													'address' => 'text',
													'text'    => 'text',
												);
												$input_type = isset( $input_type_map[ $field_type ] ) ? $input_type_map[ $field_type ] : 'text';
												?>
												<input type="<?php echo esc_attr( $input_type ); ?>" name="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( is_string( $value ) ? $value : '' ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" style="width:100%;">
											<?php endif; ?>

											<?php if ( '' !== $help_text ) : ?>
												<p class="description" style="margin-top:6px;"><?php echo esc_html( $help_text ); ?></p>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<?php if ( ! empty( $orphan_fields ) ) : ?>
							<div style="margin-top:24px;">
								<h3><?php esc_html_e( 'Removed / Legacy Fields', 'wp-formy' ); ?></h3>
								<p class="description"><?php esc_html_e( 'These values are still stored on the entry, but their fields no longer exist on the current form schema.', 'wp-formy' ); ?></p>
								<table class="wp-formy-lead-meta-table">
									<tbody>
										<?php foreach ( $orphan_fields as $field_key => $field ) : ?>
											<?php
											$label = is_array( $field ) && ! empty( $field['label'] ) ? $field['label'] : $field_key;
											$value = is_array( $field ) && isset( $field['value'] ) ? $field['value'] : '';
											if ( is_array( $value ) ) {
												$value = implode( ', ', $value );
											}
											?>
											<tr>
												<th style="width:220px;"><?php echo esc_html( $label ); ?></th>
												<td><?php echo nl2br( esc_html( (string) $value ) ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>

						<p style="margin-top:18px;">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Entry', 'wp-formy' ); ?></button>
						</p>
					</form>
				<?php else : ?>
					<p><?php esc_html_e( 'The linked form no longer exists, so this entry cannot be edited safely.', 'wp-formy' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="wp-formy-lead-card">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Entry Info', 'wp-formy' ); ?></h2>
				<table class="wp-formy-lead-meta-table">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Entry', 'wp-formy' ); ?></th>
							<td><?php echo esc_html( '#' . $lead_id ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Form name', 'wp-formy' ); ?></th>
							<td><?php echo esc_html( $lead->form_title ? $lead->form_title : __( '(Form deleted)', 'wp-formy' ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'IP', 'wp-formy' ); ?></th>
							<td><?php echo esc_html( $lead->ip_address ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'URL', 'wp-formy' ); ?></th>
							<td>
								<?php if ( ! empty( $lead->source_url ) ) : ?>
									<a href="<?php echo esc_url( $lead->source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $lead->source_url ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Browser / Device', 'wp-formy' ); ?></th>
							<td><?php echo esc_html( $lead->user_agent ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Status', 'wp-formy' ); ?></th>
							<td><span class="wp-formy-status-badge <?php echo esc_attr( $lead->status ); ?>"><?php echo esc_html( ucfirst( $lead->status ) ); ?></span></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Submitted on', 'wp-formy' ); ?></th>
							<td>
								<?php
								echo esc_html(
									wp_date(
										get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
										strtotime( $lead->created_at )
									)
								);
								?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div>
			<div class="wp-formy-lead-card">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Notes', 'wp-formy' ); ?></h2>

				<form method="post" style="margin-bottom:16px;">
					<?php wp_nonce_field( 'wpf_add_lead_note_' . $lead_id ); ?>
					<input type="hidden" name="wpf_add_note_lead_id" value="<?php echo esc_attr( $lead_id ); ?>" />
					<textarea name="wpf_lead_note" rows="4" style="width:100%;" placeholder="<?php esc_attr_e( 'Add an internal note...', 'wp-formy' ); ?>"></textarea>
					<p style="margin-top:10px;">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Note', 'wp-formy' ); ?></button>
					</p>
				</form>

				<?php if ( empty( $notes ) ) : ?>
					<p><?php esc_html_e( 'No notes yet.', 'wp-formy' ); ?></p>
				<?php else : ?>
					<?php foreach ( $notes as $note ) : ?>
						<div class="wp-formy-note">
							<div style="font-size:12px;color:#666;margin-bottom:6px;">
								<?php
								echo esc_html(
									wp_date(
										get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
										strtotime( $note->created_at )
									)
								);
								?>
							</div>
							<div><?php echo nl2br( esc_html( $note->note ) ); ?></div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
