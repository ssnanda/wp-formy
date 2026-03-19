<?php

class WP_Formy {

	private $plugin_name;
	private $version;

	public function __construct() {
		$this->plugin_name = 'wp-formy';
		$this->version     = WP_FORMY_VERSION;

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		add_action( 'init', array( $this, 'schedule_recurring_events' ) );
	}

	private function load_dependencies() {
		require_once WP_FORMY_PLUGIN_DIR . 'admin/class-wp-formy-admin.php';
	}

	private function define_admin_hooks() {
		$plugin_admin = new WP_Formy_Admin();

		add_action( 'admin_init', array( $plugin_admin, 'handle_admin_actions' ) );
		add_action( 'admin_post_wpf_export_form', array( $plugin_admin, 'handle_export_form_request' ) );
		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );
		add_filter( 'plugin_action_links_' . WP_FORMY_PLUGIN_BASENAME, array( $plugin_admin, 'add_plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $plugin_admin, 'add_plugin_row_meta_links' ), 10, 2 );

		add_action( 'wp_ajax_wpf_save_form', array( $plugin_admin, 'ajax_save_form' ) );
		add_action( 'wp_ajax_wpf_import_form', array( $plugin_admin, 'ajax_import_form' ) );
		add_action( 'wp_ajax_wpf_sync_asana_reference_data', array( $plugin_admin, 'ajax_sync_asana_reference_data' ) );
		add_action( 'wp_formy_daily_asana_sync', array( $plugin_admin, 'sync_asana_reference_data' ) );
	}

	public function schedule_recurring_events() {
		if ( ! wp_next_scheduled( 'wp_formy_daily_asana_sync' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wp_formy_daily_asana_sync' );
		}
	}

	private function define_public_hooks() {
		add_shortcode( 'wp_formy', array( $this, 'render_form_shortcode' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_form_preview' ) );
	}

	private function get_default_form_settings() {
		$plugin_settings = function_exists( 'wp_formy_get_settings' ) ? wp_formy_get_settings() : array();

		return array(
			'submit_text'           => 'Submit',
			'notifications_enabled' => isset( $plugin_settings['default_notifications_enabled'] ) ? '1' === (string) $plugin_settings['default_notifications_enabled'] : true,
			'notification_email'    => isset( $plugin_settings['default_notification_email'] ) ? $plugin_settings['default_notification_email'] : get_option( 'admin_email' ),
			'notification_subject'  => isset( $plugin_settings['default_notification_subject'] ) ? $plugin_settings['default_notification_subject'] : 'New submission for {form_title}',
			'button_alignment'      => 'left',
			'form_description'      => '',
			'success_message'       => isset( $plugin_settings['default_success_message'] ) ? $plugin_settings['default_success_message'] : 'Form submitted successfully.',
			'confirmation_type'     => 'message',
			'redirect_url'          => '',
			'use_label_placeholders' => false,
			'custom_css'            => '',
			'asana_task_enabled'    => false,
			'asana_task_name'       => 'New form submission: {form_title}',
			'asana_task_notes'      => "A new submission was received for {form_title}.\n\n{submission_fields}",
			'asana_project_gid'     => isset( $plugin_settings['asana_project_gid'] ) ? $plugin_settings['asana_project_gid'] : '',
			'form_theme'            => 'clean',
			'background_mode'       => 'solid',
			'background_color'      => '#ffffff',
			'background_gradient_start' => '#ffffff',
			'background_gradient_end'   => '#f3f7fb',
			'primary_color'         => '#0f7ac6',
			'text_color'            => '#1f2937',
			'input_background'      => '#ffffff',
			'input_border_color'    => '#d7dce3',
			'border_radius'         => 16,
		);
	}

	private function is_honeypot_enabled() {
		$plugin_settings = function_exists( 'wp_formy_get_settings' ) ? wp_formy_get_settings() : array();

		return ! empty( $plugin_settings['honeypot_enabled'] ) && '1' === (string) $plugin_settings['honeypot_enabled'];
	}

	private function get_honeypot_field_name( $form_id ) {
		return 'wpf_hp_' . absint( $form_id );
	}

	private function get_forms_table() {
		global $wpdb;
		return $wpdb->prefix . 'formy_forms';
	}

	private function get_leads_table() {
		global $wpdb;
		return $wpdb->prefix . 'formy_leads';
	}

	private function get_form_by_id( $form_id ) {
		global $wpdb;

		$table = $this->get_forms_table();

		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( $table_exists !== $table ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$form_id
			)
		);
	}

	private function normalize_schema( $schema ) {
		if ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ) {
			return array(
				'fields'   => $schema['fields'],
				'settings' => wp_parse_args(
					isset( $schema['settings'] ) && is_array( $schema['settings'] ) ? $schema['settings'] : array(),
					$this->get_default_form_settings()
				),
			);
		}

		if ( is_array( $schema ) ) {
			return array(
				'fields'   => $schema,
				'settings' => $this->get_default_form_settings(),
			);
		}

		return array(
			'fields'   => array(),
			'settings' => $this->get_default_form_settings(),
		);
	}

	public function maybe_render_form_preview() {
		$form_id = isset( $_GET['wp_formy_preview'] ) ? absint( wp_unslash( $_GET['wp_formy_preview'] ) ) : 0;
		if ( ! $form_id ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to preview this form.', 'wp-formy' ), 403 );
		}

		$form = $this->get_form_by_id( $form_id );
		if ( ! $form || 'deleted' === $form->status ) {
			wp_die( esc_html__( 'Form not found.', 'wp-formy' ), 404 );
		}

		nocache_headers();
		$form_markup = $this->render_form_shortcode( array( 'id' => $form_id ) );
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $form->title . ' Preview' ); ?></title>
			<?php wp_head(); ?>
		</head>
		<body <?php body_class( 'wp-formy-preview-page' ); ?>>
			<div style="max-width:760px;margin:40px auto;padding:32px;background:#fff;border:1px solid #dcdcde;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.05);">
				<div style="margin-bottom:20px;color:#646970;font-size:14px;">WP Formy Preview</div>
				<?php echo $form_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		exit;
	}

	private function format_lead_value_for_display( $value ) {
		if ( is_array( $value ) ) {
			$cleaned = array_map( 'sanitize_text_field', $value );
			return implode( ', ', array_filter( $cleaned, 'strlen' ) );
		}

		return sanitize_text_field( (string) $value );
	}

	private function get_notification_recipients( $settings ) {
		$recipient_string = isset( $settings['notification_email'] ) ? (string) $settings['notification_email'] : '';
		$pieces           = preg_split( '/[\s,]+/', $recipient_string );
		$emails           = array();

		if ( is_array( $pieces ) ) {
			foreach ( $pieces as $piece ) {
				$email = sanitize_email( $piece );
				if ( '' !== $email && is_email( $email ) ) {
					$emails[] = $email;
				}
			}
		}

		if ( empty( $emails ) ) {
			$default_email = sanitize_email( get_option( 'admin_email' ) );
			if ( '' !== $default_email && is_email( $default_email ) ) {
				$emails[] = $default_email;
			}
		}

		return array_values( array_unique( $emails ) );
	}

	private function get_reply_to_header( $lead_data ) {
		foreach ( $lead_data as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			if ( empty( $field['type'] ) || 'email' !== $field['type'] || empty( $field['value'] ) ) {
				continue;
			}

			$email = sanitize_email( (string) $field['value'] );
			if ( '' !== $email && is_email( $email ) ) {
				return 'Reply-To: ' . $email;
			}
		}

		return '';
	}

	private function build_submission_fields_text( $lead_data ) {
		$lines = array();

		foreach ( $lead_data as $field_id => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$label = ! empty( $field['label'] ) ? sanitize_text_field( $field['label'] ) : sanitize_text_field( (string) $field_id );
			$value = isset( $field['value'] ) ? $this->format_lead_value_for_display( $field['value'] ) : '';

			if ( ! empty( $field['file_url'] ) ) {
				$value = esc_url_raw( $field['file_url'] );
			}

			$lines[] = $label . ': ' . ( '' !== $value ? $value : '-' );
		}

		return implode( "\n", $lines );
	}

	private function maybe_create_asana_task( $form, $lead_data, $settings ) {
		if ( empty( $settings['asana_task_enabled'] ) ) {
			return;
		}

		$plugin_settings = function_exists( 'wp_formy_get_settings' ) ? wp_formy_get_settings() : array();

		if ( empty( $plugin_settings['asana_enabled'] ) || empty( $plugin_settings['asana_personal_access_token'] ) ) {
			return;
		}

		$workspace_gid = ! empty( $plugin_settings['asana_workspace_gid'] ) ? sanitize_text_field( $plugin_settings['asana_workspace_gid'] ) : '';
		if ( '' === $workspace_gid ) {
			return;
		}

		$project_gid = ! empty( $settings['asana_project_gid'] ) ? sanitize_text_field( $settings['asana_project_gid'] ) : '';
		if ( '' === $project_gid && ! empty( $plugin_settings['asana_project_gid'] ) ) {
			$project_gid = sanitize_text_field( $plugin_settings['asana_project_gid'] );
		}

		$submission_fields = $this->build_submission_fields_text( $lead_data );
		$task_name_template = ! empty( $settings['asana_task_name'] ) ? (string) $settings['asana_task_name'] : 'New form submission: {form_title}';
		$task_notes_template = ! empty( $settings['asana_task_notes'] ) ? (string) $settings['asana_task_notes'] : "A new submission was received for {form_title}.\n\n{submission_fields}";
		$replacements = array(
			'{form_title}'        => $form->title,
			'{submission_fields}' => $submission_fields,
			'{submission_count}'  => '1',
		);

		$task_name = strtr( $task_name_template, $replacements );
		$task_notes = strtr( $task_notes_template, $replacements );

		$request_data = array(
			'name'      => sanitize_text_field( $task_name ),
			'notes'     => $task_notes,
			'workspace' => $workspace_gid,
		);

		if ( '' !== $project_gid ) {
			$request_data['projects'] = array( $project_gid );
		}

		$response = wp_remote_post(
			'https://app.asana.com/api/1.0/tasks',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . sanitize_text_field( $plugin_settings['asana_personal_access_token'] ),
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'data' => $request_data,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'WP Formy Asana task creation failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code < 200 || $response_code >= 300 ) {
			error_log( 'WP Formy Asana task creation failed with status ' . $response_code . ': ' . wp_remote_retrieve_body( $response ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	private function send_form_notification( $form, $lead_data, $settings ) {
		if ( empty( $settings['notifications_enabled'] ) ) {
			return;
		}

		$recipients = $this->get_notification_recipients( $settings );
		if ( empty( $recipients ) ) {
			return;
		}

		$subject_template = ! empty( $settings['notification_subject'] ) ? (string) $settings['notification_subject'] : 'New submission for {form_title}';
		$subject          = str_replace( '{form_title}', $form->title, $subject_template );
		$subject          = sanitize_text_field( $subject );

		$lines   = array();
		$lines[] = 'A new submission was received for "' . $form->title . '".';
		$lines[] = '';

		foreach ( $lead_data as $field_id => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$label = ! empty( $field['label'] ) ? sanitize_text_field( $field['label'] ) : sanitize_text_field( (string) $field_id );
			$value = isset( $field['value'] ) ? $this->format_lead_value_for_display( $field['value'] ) : '';

			$lines[] = $label . ': ' . ( '' !== $value ? $value : '-' );
		}

		$lines[] = '';
		$lines[] = 'Submitted: ' . current_time( 'mysql' );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$reply_to = $this->get_reply_to_header( $lead_data );
		$attachments = array();

		foreach ( $lead_data as $field ) {
			if ( ! is_array( $field ) || empty( $field['file_path'] ) ) {
				continue;
			}

			$attachments[] = $field['file_path'];
		}

		if ( '' !== $reply_to ) {
			$headers[] = $reply_to;
		}

		wp_mail( $recipients, $subject, implode( "\n", $lines ), $headers, $attachments );
	}

	private function handle_form_submission( $form, $fields, $settings ) {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return array(
				'submitted' => false,
				'success'   => false,
				'message'   => '',
			);
		}

		$form_id = absint( $form->id );
		$posted_form_id = isset( $_POST['wpf_form_id'] ) ? absint( wp_unslash( $_POST['wpf_form_id'] ) ) : 0;
		if ( $posted_form_id !== $form_id ) {
			return array(
				'submitted' => false,
				'success'   => false,
				'message'   => '',
			);
		}

		$nonce = isset( $_POST['wpf_form_nonce'] ) ? wp_unslash( $_POST['wpf_form_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wpf_submit_form_' . $form_id ) ) {
			return array(
				'submitted' => true,
				'success'   => false,
				'message'   => 'Security check failed.',
			);
		}

		if ( $this->is_honeypot_enabled() ) {
			$honeypot_field_name  = $this->get_honeypot_field_name( $form_id );
			$honeypot_field_value = isset( $_POST[ $honeypot_field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $honeypot_field_name ] ) ) : '';

			if ( '' !== $honeypot_field_value ) {
				return array(
					'submitted' => true,
					'success'   => false,
					'message'   => __( 'Spam check failed. Please try again.', 'wp-formy' ),
				);
			}
		}

		$lead_data = array();
		$errors    = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_id    = ! empty( $field['id'] ) ? $field['id'] : '';
			$field_type  = ! empty( $field['type'] ) ? $field['type'] : 'text';
			$field_label = ! empty( $field['label'] ) ? $field['label'] : $field_id;
			$required    = ! empty( $field['required'] );

			if ( ! $field_id || 'separator' === $field_type ) {
				continue;
			}

			if ( 'file' === $field_type ) {
				$has_upload = isset( $_FILES[ $field_id ] ) && ! empty( $_FILES[ $field_id ]['name'] );

				if ( $required && ! $has_upload ) {
					$errors[] = sprintf( '%s is required.', $field_label );
					continue;
				}

				if ( $has_upload ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';

					$accepted_file_types = ! empty( $field['accepted_file_types'] ) ? explode( ',', (string) $field['accepted_file_types'] ) : array( '.pdf', '.jpg', '.jpeg', '.png', '.gif', '.webp' );
					$accepted_file_types = array_map( 'trim', $accepted_file_types );
					$allowed_mimes       = array(
						'pdf'  => 'application/pdf',
						'jpg|jpeg|jpe' => 'image/jpeg',
						'png'  => 'image/png',
						'gif'  => 'image/gif',
						'webp' => 'image/webp',
					);

					$uploaded = wp_handle_upload(
						$_FILES[ $field_id ],
						array(
							'test_form' => false,
							'mimes'     => $allowed_mimes,
						)
					);

					if ( isset( $uploaded['error'] ) ) {
						$errors[] = sprintf( '%s upload failed.', $field_label );
						continue;
					}

					$file_url = isset( $uploaded['url'] ) ? esc_url_raw( $uploaded['url'] ) : '';
					$file_path = isset( $uploaded['file'] ) ? $uploaded['file'] : '';
					$file_name = isset( $_FILES[ $field_id ]['name'] ) ? sanitize_file_name( wp_unslash( $_FILES[ $field_id ]['name'] ) ) : '';
					$file_ext  = strtolower( strrchr( $file_name, '.' ) );

					if ( ! empty( $accepted_file_types ) && ! in_array( $file_ext, $accepted_file_types, true ) ) {
						$errors[] = sprintf( '%s file type is not allowed.', $field_label );
						continue;
					}

					$lead_data[ $field_id ] = array(
						'label'         => $field_label,
						'type'          => $field_type,
						'value'         => $file_url,
						'file_name'     => $file_name,
						'file_path'     => $file_path,
						'accepted_types'=> implode( ',', $accepted_file_types ),
					);
				}

				continue;
			}

			$value = isset( $_POST[ $field_id ] ) ? wp_unslash( $_POST[ $field_id ] ) : null;

			if ( is_array( $value ) ) {
				$clean_value = array_map( 'sanitize_text_field', $value );
				$is_empty    = empty( $clean_value );
			} else {
				switch ( $field_type ) {
					case 'email':
						$clean_value = sanitize_email( $value );
						break;
					case 'url':
						$clean_value = esc_url_raw( $value );
						break;
					case 'textarea':
						$clean_value = sanitize_textarea_field( $value );
						break;
					default:
						$clean_value = sanitize_text_field( $value );
						break;
				}

				$is_empty = '' === $clean_value;
			}

			if ( $required && $is_empty ) {
				$errors[] = sprintf( '%s is required.', $field_label );
			}

			$lead_data[ $field_id ] = array(
				'label' => $field_label,
				'type'  => $field_type,
				'value' => $clean_value,
			);
		}

		if ( ! empty( $errors ) ) {
			return array(
				'submitted' => true,
				'success'   => false,
				'message'   => implode( ' ', $errors ),
			);
		}

		global $wpdb;
		$leads_table = $this->get_leads_table();

		$inserted = $wpdb->insert(
			$leads_table,
			array(
				'form_id'    => $form_id,
				'lead_data'  => wp_json_encode( $lead_data ),
				'status'     => 'unread',
				'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'source_url' => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '',
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return array(
				'submitted' => true,
				'success'   => false,
				'message'   => 'Unable to save your submission.',
			);
		}

		$this->send_form_notification( $form, $lead_data, $settings );
		$this->maybe_create_asana_task( $form, $lead_data, $settings );

		return array(
			'submitted' => true,
			'success'   => true,
			'message'   => ! empty( $settings['success_message'] ) ? $settings['success_message'] : 'Form submitted successfully.',
		);
	}

	public function render_form_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'wp_formy'
		);

		$form_id = absint( $atts['id'] );
		if ( ! $form_id ) {
			return '<p>Invalid form ID.</p>';
		}

		$form = $this->get_form_by_id( $form_id );
		if ( ! $form ) {
			return '<p>Form not found.</p>';
		}

		$schema = json_decode( $form->form_schema, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return '<p>Form schema is invalid.</p>';
		}

		$normalized  = $this->normalize_schema( $schema );
		$fields      = $normalized['fields'];
		$settings    = $normalized['settings'];
		$submit_text = ! empty( $settings['submit_text'] ) ? $settings['submit_text'] : 'Submit';
		$border_radius = isset( $settings['border_radius'] ) ? min( 32, max( 0, absint( $settings['border_radius'] ) ) ) : 16;
		$background_mode = isset( $settings['background_mode'] ) ? $settings['background_mode'] : 'solid';
		$background_value = 'gradient' === $background_mode
			? 'linear-gradient(135deg, ' . ( ! empty( $settings['background_gradient_start'] ) ? $settings['background_gradient_start'] : '#ffffff' ) . ' 0%, ' . ( ! empty( $settings['background_gradient_end'] ) ? $settings['background_gradient_end'] : '#f3f7fb' ) . ' 100%)'
			: ( ! empty( $settings['background_color'] ) ? $settings['background_color'] : '#ffffff' );
		$form_theme = ! empty( $settings['form_theme'] ) ? $settings['form_theme'] : 'clean';
		$wrapper_style = sprintf(
			'--wp-formy-primary:%1$s;--wp-formy-text:%2$s;--wp-formy-input-bg:%3$s;--wp-formy-input-border:%4$s;--wp-formy-radius:%5$dpx;--wp-formy-bg:%6$s;',
			! empty( $settings['primary_color'] ) ? $settings['primary_color'] : '#0f7ac6',
			! empty( $settings['text_color'] ) ? $settings['text_color'] : '#1f2937',
			! empty( $settings['input_background'] ) ? $settings['input_background'] : '#ffffff',
			! empty( $settings['input_border_color'] ) ? $settings['input_border_color'] : '#d7dce3',
			$border_radius,
			$background_value
		);

		if ( empty( $fields ) ) {
			return '<p>This form has no fields.</p>';
		}

		$submission_result = $this->handle_form_submission( $form, $fields, $settings );

		ob_start();
		?>
		<form class="wp-formy-frontend-form wp-formy-theme-<?php echo esc_attr( $form_theme ); ?>" method="post" enctype="multipart/form-data" style="<?php echo esc_attr( $wrapper_style ); ?>padding:24px;border-radius:var(--wp-formy-radius);background:var(--wp-formy-bg);border:1px solid #dfe6ee;box-shadow:0 20px 45px rgba(18,52,77,.08);">
			<input type="hidden" name="wpf_form_id" value="<?php echo esc_attr( $form_id ); ?>">
			<?php wp_nonce_field( 'wpf_submit_form_' . $form_id, 'wpf_form_nonce' ); ?>
			<?php if ( $this->is_honeypot_enabled() ) : ?>
				<?php $honeypot_field_name = $this->get_honeypot_field_name( $form_id ); ?>
				<div class="wp-formy-honeypot" aria-hidden="true" style="position:absolute !important;left:-9999px !important;top:auto !important;width:1px !important;height:1px !important;overflow:hidden !important;">
					<label for="<?php echo esc_attr( $honeypot_field_name ); ?>"><?php esc_html_e( 'Leave this field empty', 'wp-formy' ); ?></label>
					<input
						type="text"
						id="<?php echo esc_attr( $honeypot_field_name ); ?>"
						name="<?php echo esc_attr( $honeypot_field_name ); ?>"
						value=""
						tabindex="-1"
						autocomplete="off"
					>
				</div>
			<?php endif; ?>

			<div class="wp-formy-form-title">
				<h3><?php echo esc_html( $form->title ); ?></h3>
				<?php if ( ! empty( $settings['form_description'] ) ) : ?>
					<p><?php echo esc_html( $settings['form_description'] ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $submission_result['submitted'] ) : ?>
				<div class="wp-formy-message <?php echo $submission_result['success'] ? 'success' : 'error'; ?>" style="margin-bottom:20px;padding:12px;border-radius:4px;<?php echo $submission_result['success'] ? 'background:#edfaef;color:#116329;' : 'background:#fcf0f1;color:#8a2424;'; ?>">
					<?php echo esc_html( $submission_result['message'] ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $submission_result['success'] ) : ?>
				<?php foreach ( $fields as $field ) : ?>
					<?php
					if ( ! is_array( $field ) ) {
						continue;
					}

					$field_id    = ! empty( $field['id'] ) ? $field['id'] : 'field_' . wp_generate_uuid4();
					$field_type  = ! empty( $field['type'] ) ? $field['type'] : 'text';
					$field_label = ! empty( $field['label'] ) ? $field['label'] : ucfirst( $field_type );
					$required    = ! empty( $field['required'] );
					$placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';
					$options     = ! empty( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
					$help_text   = ! empty( $field['help_text'] ) ? $field['help_text'] : '';
					$default_value = ! empty( $field['default_value'] ) ? $field['default_value'] : '';
					$css_class   = ! empty( $field['css_class'] ) ? $field['css_class'] : '';
					$accepted_file_types = ! empty( $field['accepted_file_types'] ) ? $field['accepted_file_types'] : '.pdf,.jpg,.jpeg,.png,.gif,.webp';

					$posted_value = isset( $_POST[ $field_id ] ) ? wp_unslash( $_POST[ $field_id ] ) : $default_value;
					?>
					<div class="wp-formy-field <?php echo esc_attr( $css_class ); ?>" style="margin-bottom:20px;">
						<?php if ( 'separator' !== $field_type ) : ?>
							<label for="<?php echo esc_attr( $field_id ); ?>" style="display:block; font-weight:600; margin-bottom:6px;color:var(--wp-formy-text);">
								<?php echo esc_html( $field_label ); ?>
								<?php if ( $required ) : ?>
									<span style="color:#d63638;">*</span>
								<?php endif; ?>
							</label>
						<?php endif; ?>

						<?php if ( 'textarea' === $field_type ) : ?>
							<textarea
								id="<?php echo esc_attr( $field_id ); ?>"
								name="<?php echo esc_attr( $field_id ); ?>"
								placeholder="<?php echo esc_attr( $placeholder ); ?>"
								<?php echo $required ? 'required' : ''; ?>
								style="width:100%; padding:10px;border-radius:calc(var(--wp-formy-radius) - 4px);background:var(--wp-formy-input-bg);border:1px solid var(--wp-formy-input-border);color:var(--wp-formy-text);"
							><?php echo esc_textarea( is_string( $posted_value ) ? $posted_value : '' ); ?></textarea>

						<?php elseif ( 'select' === $field_type ) : ?>
							<select
								id="<?php echo esc_attr( $field_id ); ?>"
								name="<?php echo esc_attr( $field_id ); ?>"
								<?php echo $required ? 'required' : ''; ?>
								style="width:100%; padding:10px;border-radius:calc(var(--wp-formy-radius) - 4px);background:var(--wp-formy-input-bg);border:1px solid var(--wp-formy-input-border);color:var(--wp-formy-text);"
							>
								<option value=""><?php echo esc_html( $placeholder ?: 'Select an option' ); ?></option>
								<?php foreach ( $options as $option ) : ?>
									<?php
									$option_label = is_array( $option ) && isset( $option['label'] ) ? $option['label'] : $option;
									$option_value = is_array( $option ) && isset( $option['value'] ) ? $option['value'] : $option_label;
									?>
									<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $posted_value, $option_value ); ?>>
										<?php echo esc_html( $option_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>

						<?php elseif ( 'checkboxes' === $field_type ) : ?>
							<div id="<?php echo esc_attr( $field_id ); ?>">
								<?php
								$posted_array = is_array( $posted_value ) ? $posted_value : array();
								foreach ( $options as $option ) :
									$option_label = is_array( $option ) && isset( $option['label'] ) ? $option['label'] : $option;
									$option_value = is_array( $option ) && isset( $option['value'] ) ? $option['value'] : $option_label;
									?>
									<label style="display:block; margin-bottom:6px;color:var(--wp-formy-text);">
										<input type="checkbox" name="<?php echo esc_attr( $field_id ); ?>[]" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( in_array( $option_value, $posted_array, true ) ); ?>>
										<?php echo esc_html( $option_label ); ?>
									</label>
								<?php endforeach; ?>
							</div>

						<?php elseif ( 'multiple_choice' === $field_type ) : ?>
							<div id="<?php echo esc_attr( $field_id ); ?>">
								<?php foreach ( $options as $option ) : ?>
									<?php
									$option_label = is_array( $option ) && isset( $option['label'] ) ? $option['label'] : $option;
									$option_value = is_array( $option ) && isset( $option['value'] ) ? $option['value'] : $option_label;
									?>
									<label style="display:block; margin-bottom:6px;color:var(--wp-formy-text);">
										<input type="radio" name="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $option_value ); ?>" <?php checked( $posted_value, $option_value ); ?> <?php echo $required ? 'required' : ''; ?>>
										<?php echo esc_html( $option_label ); ?>
									</label>
								<?php endforeach; ?>
							</div>

						<?php elseif ( 'separator' === $field_type ) : ?>
							<hr>

						<?php elseif ( 'file' === $field_type ) : ?>
							<input
								type="file"
								id="<?php echo esc_attr( $field_id ); ?>"
								name="<?php echo esc_attr( $field_id ); ?>"
								accept="<?php echo esc_attr( $accepted_file_types ); ?>"
								<?php echo $required ? 'required' : ''; ?>
								style="width:100%; padding:10px;border-radius:calc(var(--wp-formy-radius) - 4px);background:var(--wp-formy-input-bg);border:1px solid var(--wp-formy-input-border);color:var(--wp-formy-text);"
							>

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
							<input
								type="<?php echo esc_attr( $input_type ); ?>"
								id="<?php echo esc_attr( $field_id ); ?>"
								name="<?php echo esc_attr( $field_id ); ?>"
								value="<?php echo esc_attr( is_string( $posted_value ) ? $posted_value : '' ); ?>"
								placeholder="<?php echo esc_attr( $placeholder ); ?>"
								<?php echo $required ? 'required' : ''; ?>
								style="width:100%; padding:10px;border-radius:calc(var(--wp-formy-radius) - 4px);background:var(--wp-formy-input-bg);border:1px solid var(--wp-formy-input-border);color:var(--wp-formy-text);"
							>
						<?php endif; ?>

						<?php if ( '' !== $help_text ) : ?>
							<p style="margin-top:6px;color:#646970;font-size:12px;"><?php echo esc_html( $help_text ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<div class="wp-formy-submit" style="text-align:<?php echo esc_attr( ! empty( $settings['button_alignment'] ) ? $settings['button_alignment'] : 'left' ); ?>;">
					<button type="submit" style="background:var(--wp-formy-primary);color:#fff;border:0;border-radius:calc(var(--wp-formy-radius) - 4px);padding:12px 20px;font-weight:700;"><?php echo esc_html( $submit_text ); ?></button>
				</div>
			<?php endif; ?>
		</form>
		<?php
		return ob_get_clean();
	}

	public function run() {
	}
}
