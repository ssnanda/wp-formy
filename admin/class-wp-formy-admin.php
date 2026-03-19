<?php

class WP_Formy_Admin {

	public function get_plugin_settings() {
		return function_exists( 'wp_formy_get_settings' ) ? wp_formy_get_settings() : array(
			'default_notification_email'     => get_option( 'admin_email' ),
			'default_notification_subject'   => 'New submission for {form_title}',
			'default_notifications_enabled' => '1',
			'default_from_name'              => get_bloginfo( 'name' ),
			'default_reply_to_mode'          => 'submitter',
			'default_success_message'        => 'Form submitted successfully.',
			'validation_mode'                => 'native',
			'require_unique_form_names'      => '1',
			'honeypot_enabled'               => '1',
			'recaptcha_site_key'             => '',
			'recaptcha_secret_key'           => '',
			'hcaptcha_site_key'              => '',
			'hcaptcha_secret_key'            => '',
			'turnstile_site_key'             => '',
			'turnstile_secret_key'           => '',
			'webhook_url'                    => '',
			'asana_enabled'                  => '0',
			'asana_personal_access_token'    => '',
			'asana_workspace_gid'            => '',
			'asana_project_gid'              => '',
			'stripe_mode'                    => 'test',
			'stripe_publishable_key'         => '',
			'stripe_secret_key'              => '',
		);
	}

	private function normalize_imported_schema( $schema ) {
		if ( isset( $schema['fields'] ) && is_array( $schema['fields'] ) ) {
			return array(
				'version'   => isset( $schema['version'] ) ? absint( $schema['version'] ) : 1,
				'source'    => isset( $schema['source'] ) ? sanitize_text_field( $schema['source'] ) : 'wp-formy',
				'fields'    => $schema['fields'],
				'settings'  => isset( $schema['settings'] ) && is_array( $schema['settings'] ) ? $schema['settings'] : array(),
				'sureforms' => isset( $schema['sureforms'] ) && is_array( $schema['sureforms'] ) ? $schema['sureforms'] : array(),
			);
		}

		if ( is_array( $schema ) ) {
			return array(
				'version'   => 1,
				'source'    => 'legacy',
				'fields'    => $schema,
				'settings'  => array(),
				'sureforms' => array(),
			);
		}

		return array(
			'version'   => 1,
			'source'    => 'wp-formy',
			'fields'    => array(),
			'settings'  => array(),
			'sureforms' => array(),
		);
	}

	private function normalize_field_for_storage( $field ) {
		if ( ! is_array( $field ) ) {
			return null;
		}

		$normalized = array(
			'id'          => isset( $field['id'] ) ? sanitize_key( $field['id'] ) : '',
			'type'        => isset( $field['type'] ) ? sanitize_key( $field['type'] ) : 'text',
			'label'       => isset( $field['label'] ) ? sanitize_text_field( $field['label'] ) : '',
			'placeholder' => isset( $field['placeholder'] ) ? sanitize_text_field( $field['placeholder'] ) : '',
			'required'    => ! empty( $field['required'] ),
			'css_class'   => isset( $field['css_class'] ) ? sanitize_html_class( $field['css_class'] ) : '',
			'width'       => isset( $field['width'] ) ? absint( $field['width'] ) : 100,
			'help_text'   => isset( $field['help_text'] ) ? sanitize_text_field( $field['help_text'] ) : '',
			'default_value' => isset( $field['default_value'] ) ? sanitize_text_field( $field['default_value'] ) : '',
			'accepted_file_types' => isset( $field['accepted_file_types'] ) ? sanitize_text_field( $field['accepted_file_types'] ) : '.pdf,.jpg,.jpeg,.png,.gif,.webp',
		);

		if ( '' === $normalized['id'] ) {
			$normalized['id'] = 'field_' . wp_generate_password( 8, false, false );
		}

		if ( ! in_array( $normalized['width'], array( 100, 50, 33, 25 ), true ) ) {
			$normalized['width'] = 100;
		}

		if ( in_array( $normalized['type'], array( 'select', 'checkboxes', 'multiple_choice' ), true ) ) {
			$normalized['options'] = array();

			if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
				foreach ( $field['options'] as $option ) {
					$label = '';
					$value = '';

					if ( is_array( $option ) ) {
						$label = isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '';
						$value = isset( $option['value'] ) ? sanitize_text_field( $option['value'] ) : '';
					} else {
						$label = sanitize_text_field( $option );
						$value = $label;
					}

					if ( '' === $label && '' === $value ) {
						continue;
					}

					$normalized['options'][] = array(
						'label' => '' !== $label ? $label : $value,
						'value' => '' !== $value ? $value : $label,
					);
				}
			}
		}

		return $normalized;
	}

	private function sanitize_schema_for_storage( $schema ) {
		$normalized = $this->normalize_imported_schema( $schema );
		$fields     = array();
		$plugin_settings = $this->get_plugin_settings();

		foreach ( $normalized['fields'] as $field ) {
			$sanitized_field = $this->normalize_field_for_storage( $field );
			if ( null !== $sanitized_field ) {
				$fields[] = $sanitized_field;
			}
		}

		return array(
			'version'   => isset( $normalized['version'] ) ? absint( $normalized['version'] ) : 1,
			'source'    => isset( $normalized['source'] ) ? sanitize_text_field( $normalized['source'] ) : 'wp-formy',
			'fields'    => $fields,
			'settings'  => array(
				'submit_text'           => isset( $normalized['settings']['submit_text'] ) ? sanitize_text_field( $normalized['settings']['submit_text'] ) : 'Submit',
				'notifications_enabled' => isset( $normalized['settings']['notifications_enabled'] ) ? (bool) $normalized['settings']['notifications_enabled'] : true,
				'notification_email'    => isset( $normalized['settings']['notification_email'] ) ? sanitize_text_field( $normalized['settings']['notification_email'] ) : $plugin_settings['default_notification_email'],
				'notification_subject'  => isset( $normalized['settings']['notification_subject'] ) ? sanitize_text_field( $normalized['settings']['notification_subject'] ) : $plugin_settings['default_notification_subject'],
				'button_alignment'      => isset( $normalized['settings']['button_alignment'] ) ? sanitize_key( $normalized['settings']['button_alignment'] ) : 'left',
				'form_description'      => isset( $normalized['settings']['form_description'] ) ? sanitize_textarea_field( $normalized['settings']['form_description'] ) : '',
				'success_message'       => isset( $normalized['settings']['success_message'] ) ? sanitize_textarea_field( $normalized['settings']['success_message'] ) : ( isset( $plugin_settings['default_success_message'] ) ? $plugin_settings['default_success_message'] : 'Form submitted successfully.' ),
				'confirmation_type'     => isset( $normalized['settings']['confirmation_type'] ) && in_array( sanitize_key( $normalized['settings']['confirmation_type'] ), array( 'message', 'redirect' ), true ) ? sanitize_key( $normalized['settings']['confirmation_type'] ) : 'message',
				'redirect_url'          => isset( $normalized['settings']['redirect_url'] ) ? esc_url_raw( $normalized['settings']['redirect_url'] ) : '',
				'use_label_placeholders' => ! empty( $normalized['settings']['use_label_placeholders'] ),
				'custom_css'            => isset( $normalized['settings']['custom_css'] ) ? wp_strip_all_tags( $normalized['settings']['custom_css'] ) : '',
				'asana_task_enabled'    => ! empty( $normalized['settings']['asana_task_enabled'] ),
				'asana_task_name'       => isset( $normalized['settings']['asana_task_name'] ) ? sanitize_text_field( $normalized['settings']['asana_task_name'] ) : 'New form submission: {form_title}',
				'asana_task_notes'      => isset( $normalized['settings']['asana_task_notes'] ) ? sanitize_textarea_field( $normalized['settings']['asana_task_notes'] ) : "A new submission was received for {form_title}.\n\n{submission_fields}",
				'asana_project_gid'     => isset( $normalized['settings']['asana_project_gid'] ) ? sanitize_text_field( $normalized['settings']['asana_project_gid'] ) : '',
				'form_theme'            => isset( $normalized['settings']['form_theme'] ) && in_array( sanitize_key( $normalized['settings']['form_theme'] ), array( 'clean', 'soft', 'contrast' ), true ) ? sanitize_key( $normalized['settings']['form_theme'] ) : 'clean',
				'background_mode'       => isset( $normalized['settings']['background_mode'] ) && in_array( sanitize_key( $normalized['settings']['background_mode'] ), array( 'solid', 'gradient' ), true ) ? sanitize_key( $normalized['settings']['background_mode'] ) : 'solid',
				'background_color'      => isset( $normalized['settings']['background_color'] ) ? sanitize_hex_color( $normalized['settings']['background_color'] ) : '#ffffff',
				'background_gradient_start' => isset( $normalized['settings']['background_gradient_start'] ) ? sanitize_hex_color( $normalized['settings']['background_gradient_start'] ) : '#ffffff',
				'background_gradient_end'   => isset( $normalized['settings']['background_gradient_end'] ) ? sanitize_hex_color( $normalized['settings']['background_gradient_end'] ) : '#f3f7fb',
				'primary_color'         => isset( $normalized['settings']['primary_color'] ) ? sanitize_hex_color( $normalized['settings']['primary_color'] ) : '#0f7ac6',
				'text_color'            => isset( $normalized['settings']['text_color'] ) ? sanitize_hex_color( $normalized['settings']['text_color'] ) : '#1f2937',
				'input_background'      => isset( $normalized['settings']['input_background'] ) ? sanitize_hex_color( $normalized['settings']['input_background'] ) : '#ffffff',
				'input_border_color'    => isset( $normalized['settings']['input_border_color'] ) ? sanitize_hex_color( $normalized['settings']['input_border_color'] ) : '#d7dce3',
				'border_radius'         => isset( $normalized['settings']['border_radius'] ) ? min( 32, max( 0, absint( $normalized['settings']['border_radius'] ) ) ) : 16,
			),
			'sureforms' => isset( $normalized['sureforms'] ) && is_array( $normalized['sureforms'] ) ? $normalized['sureforms'] : array(),
		);
	}

	private function delete_forms_and_related_data( $form_ids, $permanent = false ) {
		global $wpdb;

		$form_ids = array_filter( array_map( 'absint', (array) $form_ids ) );
		if ( empty( $form_ids ) ) {
			return;
		}

		$forms_table      = $this->get_forms_table();
		$leads_table      = $this->get_leads_table();
		$lead_notes_table = $this->get_lead_notes_table();
		$placeholders     = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );

		$lead_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$leads_table} WHERE form_id IN ({$placeholders})",
				$form_ids
			)
		);

		if ( $permanent && ! empty( $lead_ids ) ) {
			$lead_placeholders = implode( ',', array_fill( 0, count( $lead_ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$lead_notes_table} WHERE lead_id IN ({$lead_placeholders})",
					$lead_ids
				)
			);
		}

		if ( $permanent ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$leads_table} WHERE form_id IN ({$placeholders})",
					$form_ids
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$forms_table} WHERE id IN ({$placeholders})",
					$form_ids
				)
			);
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$forms_table} SET status = %s, updated_at = %s WHERE id IN ({$placeholders})",
				array_merge( array( 'deleted', current_time( 'mysql' ) ), $form_ids )
			)
		);
	}

	public function get_form_preview_url( $form_id ) {
		return add_query_arg(
			array(
				'wp_formy_preview' => absint( $form_id ),
			),
			home_url( '/' )
		);
	}

	private function get_form_edit_url( $form_id ) {
		return add_query_arg(
			array(
				'page'    => 'wp-formy',
				'action'  => 'edit',
				'form_id' => absint( $form_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	private function duplicate_form( $form_id ) {
		global $wpdb;

		$table = $this->get_forms_table();
		$form  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$form_id
			)
		);

		if ( ! $form ) {
			return 0;
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'title'       => sprintf( __( '%s Copy', 'wp-formy' ), $form->title ),
				'form_schema' => $form->form_schema,
				'status'      => 'draft',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	private function export_form( $form_id ) {
		global $wpdb;

		$table = $this->get_forms_table();
		$form  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$form_id
			),
			ARRAY_A
		);

		if ( ! $form ) {
			wp_die( esc_html__( 'Form not found.', 'wp-formy' ) );
		}

		$payload = array(
			'title'  => $form['title'],
			'status' => $form['status'],
			'schema' => json_decode( $form['form_schema'], true ),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $form['title'] . '.json' ) );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
		exit;
	}

	public function handle_export_form_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-formy' ) );
		}

		$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $form_id || ! wp_verify_nonce( $nonce, 'wpf_export_form' ) ) {
			wp_die( esc_html__( 'Invalid export request.', 'wp-formy' ) );
		}

		$this->export_form( $form_id );
	}

	private function update_form_status( $form_id, $status ) {
		global $wpdb;

		$allowed_statuses = array( 'draft', 'published', 'deleted' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return false;
		}

		return false !== $wpdb->update(
			$this->get_forms_table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $form_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	private function handle_form_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['form_action'], $_GET['form_id'], $_GET['_wpnonce'] ) ) {
			return;
		}

		$form_action = sanitize_text_field( wp_unslash( $_GET['form_action'] ) );
		$form_id     = absint( wp_unslash( $_GET['form_id'] ) );
		$nonce       = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'wpf_form_action_' . $form_id ) ) {
			return;
		}

		$redirect = admin_url( 'admin.php?page=wp-formy' );

		if ( 'delete' === $form_action ) {
			$this->delete_forms_and_related_data( array( $form_id ) );
			$redirect = add_query_arg( array( 'page' => 'wp-formy', 'form_status' => 'deleted', 'trashed' => 1 ), admin_url( 'admin.php' ) );
		} elseif ( 'restore' === $form_action ) {
			$this->update_form_status( $form_id, 'draft' );
			$redirect = add_query_arg( array( 'page' => 'wp-formy', 'restored' => 1 ), admin_url( 'admin.php' ) );
		} elseif ( 'duplicate' === $form_action ) {
			$new_form_id = $this->duplicate_form( $form_id );
			if ( $new_form_id ) {
				$redirect = add_query_arg(
					array(
						'page'      => 'wp-formy',
						'action'    => 'edit',
						'form_id'   => $new_form_id,
						'duplicated'=> 1,
					),
					admin_url( 'admin.php' )
				);
			}
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public function handle_admin_actions() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'wp-formy' === $page ) {
			$this->handle_form_actions();
		} elseif ( 'wp-formy-leads' === $page ) {
			$this->handle_lead_actions();
		} elseif ( 'wp-formy-settings' === $page ) {
			$this->handle_settings_save();
		}
	}

	private function handle_settings_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['wp_formy_settings_nonce'] ) ) {
			return;
		}

		check_admin_referer( 'wp_formy_save_settings', 'wp_formy_settings_nonce' );

		$section    = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'general';
		$subsection = isset( $_GET['subsection'] ) ? sanitize_key( wp_unslash( $_GET['subsection'] ) ) : '';

		$settings = array(
			'default_notification_email'     => isset( $_POST['default_notification_email'] ) ? sanitize_text_field( wp_unslash( $_POST['default_notification_email'] ) ) : get_option( 'admin_email' ),
			'default_notification_subject'   => isset( $_POST['default_notification_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['default_notification_subject'] ) ) : 'New submission for {form_title}',
			'default_notifications_enabled'  => isset( $_POST['default_notifications_enabled'] ) ? '1' : '0',
			'default_from_name'              => isset( $_POST['default_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['default_from_name'] ) ) : get_bloginfo( 'name' ),
			'default_reply_to_mode'          => isset( $_POST['default_reply_to_mode'] ) && in_array( sanitize_key( wp_unslash( $_POST['default_reply_to_mode'] ) ), array( 'submitter', 'site' ), true ) ? sanitize_key( wp_unslash( $_POST['default_reply_to_mode'] ) ) : 'submitter',
			'default_success_message'        => isset( $_POST['default_success_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['default_success_message'] ) ) : 'Form submitted successfully.',
			'validation_mode'                => isset( $_POST['validation_mode'] ) && in_array( sanitize_key( wp_unslash( $_POST['validation_mode'] ) ), array( 'native', 'friendly' ), true ) ? sanitize_key( wp_unslash( $_POST['validation_mode'] ) ) : 'native',
			'require_unique_form_names'      => isset( $_POST['require_unique_form_names'] ) ? '1' : '0',
			'honeypot_enabled'               => isset( $_POST['honeypot_enabled'] ) ? '1' : '0',
			'recaptcha_site_key'             => isset( $_POST['recaptcha_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_site_key'] ) ) : '',
			'recaptcha_secret_key'           => isset( $_POST['recaptcha_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_secret_key'] ) ) : '',
			'hcaptcha_site_key'              => isset( $_POST['hcaptcha_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hcaptcha_site_key'] ) ) : '',
			'hcaptcha_secret_key'            => isset( $_POST['hcaptcha_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hcaptcha_secret_key'] ) ) : '',
			'turnstile_site_key'             => isset( $_POST['turnstile_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['turnstile_site_key'] ) ) : '',
			'turnstile_secret_key'           => isset( $_POST['turnstile_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['turnstile_secret_key'] ) ) : '',
			'webhook_url'                    => isset( $_POST['webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['webhook_url'] ) ) : '',
			'asana_enabled'                  => isset( $_POST['asana_enabled'] ) ? '1' : '0',
			'asana_personal_access_token'    => isset( $_POST['asana_personal_access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['asana_personal_access_token'] ) ) : '',
			'asana_workspace_gid'            => isset( $_POST['asana_workspace_gid'] ) ? sanitize_text_field( wp_unslash( $_POST['asana_workspace_gid'] ) ) : '',
			'asana_project_gid'              => isset( $_POST['asana_project_gid'] ) ? sanitize_text_field( wp_unslash( $_POST['asana_project_gid'] ) ) : '',
			'stripe_mode'                    => isset( $_POST['stripe_mode'] ) && in_array( sanitize_key( wp_unslash( $_POST['stripe_mode'] ) ), array( 'test', 'live' ), true ) ? sanitize_key( wp_unslash( $_POST['stripe_mode'] ) ) : 'test',
			'stripe_publishable_key'         => isset( $_POST['stripe_publishable_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_publishable_key'] ) ) : '',
			'stripe_secret_key'              => isset( $_POST['stripe_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_secret_key'] ) ) : '',
		);

		update_option( 'wp_formy_settings', $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'wp-formy-settings',
					'section'          => $section,
					'subsection'       => $subsection,
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function enqueue_styles( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'wp-formy' ) === false ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			$css_rel_path = 'admin/partials/wp-formy-builder.css';
			$css_path     = WP_FORMY_PLUGIN_DIR . $css_rel_path;
			$version      = file_exists( $css_path ) ? filemtime( $css_path ) : WP_FORMY_VERSION;

			wp_enqueue_style(
				'wp-formy-builder',
				WP_FORMY_PLUGIN_URL . 'admin/partials/wp-formy-builder.css',
				array(),
				$version
			);
		}

		if ( isset( $_GET['page'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['page'] ) ), array( 'wp-formy', 'wp-formy-leads' ), true ) ) {
			wp_add_inline_style(
				'wp-admin',
				'
				.wp-formy-status-badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600}
				.wp-formy-status-badge.unread{background:#fff4e5;color:#b26a00;border:1px solid #f0c36d}
				.wp-formy-status-badge.read{background:#ecf7ed;color:#1e7e34;border:1px solid #9ad3a3}
				.wp-formy-lead-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;margin-top:16px}
				.wp-formy-lead-grid{display:grid;grid-template-columns:2fr 1fr;gap:20px}
				.wp-formy-lead-meta-table{width:100%;border-collapse:collapse}
				.wp-formy-lead-meta-table th,.wp-formy-lead-meta-table td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}
				.wp-formy-note{border:1px solid #e5e5e5;border-radius:8px;padding:12px;margin-bottom:12px;background:#fafafa}
				.wp-formy-inline-actions{display:flex;gap:8px;align-items:center}
				.wp-formy-inline-actions .button-link-delete{color:#b32d2e}
				.wp-formy-summary-line{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:420px}
				'
			);
		}
	}

	public function enqueue_scripts( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'wp-formy' ) === false ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			$js_rel_path = 'admin/wp-formy-builder.js';
			$js_path     = WP_FORMY_PLUGIN_DIR . $js_rel_path;
			$version     = file_exists( $js_path ) ? filemtime( $js_path ) : WP_FORMY_VERSION;

			wp_enqueue_script(
				'wp-formy-builder-js',
				WP_FORMY_PLUGIN_URL . $js_rel_path,
				array(),
				$version,
				true
			);

			wp_localize_script(
				'wp-formy-builder-js',
				'wpFormyBuilder',
				array(
					'ajaxurl'      => admin_url( 'admin-ajax.php' ),
					'nonce_save'   => wp_create_nonce( 'wpf_save_form' ),
					'nonce_import' => wp_create_nonce( 'wpf_import_form' ),
				)
			);
		}
	}

	private function get_forms_table() {
		global $wpdb;
		return $wpdb->prefix . 'formy_forms';
	}

	private function get_leads_table() {
		global $wpdb;
		return $wpdb->prefix . 'formy_leads';
	}

	private function get_lead_notes_table() {
		global $wpdb;
		return $wpdb->prefix . 'formy_lead_notes';
	}

	public function get_form_record( $form_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_forms_table()} WHERE id = %d",
				absint( $form_id )
			)
		);
	}

	public function get_form_schema_fields( $form_id ) {
		$form = $this->get_form_record( $form_id );
		if ( ! $form || empty( $form->form_schema ) ) {
			return array();
		}

		$decoded = json_decode( $form->form_schema, true );
		if ( isset( $decoded['fields'] ) && is_array( $decoded['fields'] ) ) {
			return $decoded['fields'];
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	private function sanitize_lead_value_for_field( $field, $posted_value, $existing_value ) {
		$field_type = isset( $field['type'] ) ? $field['type'] : 'text';
		$field_id   = isset( $field['id'] ) ? $field['id'] : '';
		$label      = ! empty( $field['label'] ) ? $field['label'] : $field_id;
		$required   = ! empty( $field['required'] );

		if ( 'separator' === $field_type || '' === $field_id ) {
			return array( 'skip' => true );
		}

		if ( 'file' === $field_type ) {
			$has_upload = isset( $_FILES[ $field_id ] ) && ! empty( $_FILES[ $field_id ]['name'] );
			$current    = is_array( $existing_value ) ? $existing_value : array();

			if ( ! $has_upload ) {
				if ( $required && empty( $current['value'] ) ) {
					return array( 'error' => sprintf( __( '%s is required.', 'wp-formy' ), $label ) );
				}

				return array(
					'value' => array(
						'label'          => $label,
						'type'           => $field_type,
						'value'          => isset( $current['value'] ) ? $current['value'] : '',
						'file_name'      => isset( $current['file_name'] ) ? $current['file_name'] : '',
						'file_path'      => isset( $current['file_path'] ) ? $current['file_path'] : '',
						'accepted_types' => isset( $current['accepted_types'] ) ? $current['accepted_types'] : '',
					),
				);
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';

			$accepted_file_types = ! empty( $field['accepted_file_types'] ) ? explode( ',', (string) $field['accepted_file_types'] ) : array( '.pdf', '.jpg', '.jpeg', '.png', '.gif', '.webp' );
			$accepted_file_types = array_map( 'trim', $accepted_file_types );
			$allowed_mimes       = array(
				'pdf'          => 'application/pdf',
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'gif'          => 'image/gif',
				'webp'         => 'image/webp',
			);

			$uploaded = wp_handle_upload(
				$_FILES[ $field_id ],
				array(
					'test_form' => false,
					'mimes'     => $allowed_mimes,
				)
			);

			if ( isset( $uploaded['error'] ) ) {
				return array( 'error' => sprintf( __( '%s upload failed.', 'wp-formy' ), $label ) );
			}

			$file_url  = isset( $uploaded['url'] ) ? esc_url_raw( $uploaded['url'] ) : '';
			$file_path = isset( $uploaded['file'] ) ? $uploaded['file'] : '';
			$file_name = isset( $_FILES[ $field_id ]['name'] ) ? sanitize_file_name( wp_unslash( $_FILES[ $field_id ]['name'] ) ) : '';
			$file_ext  = strtolower( strrchr( $file_name, '.' ) );

			if ( ! empty( $accepted_file_types ) && ! in_array( $file_ext, $accepted_file_types, true ) ) {
				return array( 'error' => sprintf( __( '%s file type is not allowed.', 'wp-formy' ), $label ) );
			}

			return array(
				'value' => array(
					'label'          => $label,
					'type'           => $field_type,
					'value'          => $file_url,
					'file_name'      => $file_name,
					'file_path'      => $file_path,
					'accepted_types' => implode( ',', $accepted_file_types ),
				),
			);
		}

		if ( is_array( $posted_value ) ) {
			$clean_value = array_map( 'sanitize_text_field', $posted_value );
			$is_empty    = empty( array_filter( $clean_value, 'strlen' ) );
		} else {
			switch ( $field_type ) {
				case 'email':
					$clean_value = sanitize_email( $posted_value );
					break;
				case 'url':
					$clean_value = esc_url_raw( $posted_value );
					break;
				case 'textarea':
					$clean_value = sanitize_textarea_field( $posted_value );
					break;
				default:
					$clean_value = sanitize_text_field( $posted_value );
					break;
			}

			$is_empty = '' === $clean_value;
		}

		if ( $required && $is_empty ) {
			return array( 'error' => sprintf( __( '%s is required.', 'wp-formy' ), $label ) );
		}

		return array(
			'value' => array(
				'label' => $label,
				'type'  => $field_type,
				'value' => $clean_value,
			),
		);
	}

	private function update_lead_entry( $lead_id ) {
		global $wpdb;

		$lead = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_leads_table()} WHERE id = %d",
				$lead_id
			)
		);

		if ( ! $lead ) {
			return array( 'success' => false, 'message' => __( 'Entry not found.', 'wp-formy' ) );
		}

		$form = $this->get_form_record( $lead->form_id );
		if ( ! $form ) {
			return array( 'success' => false, 'message' => __( 'This entry is linked to a form that no longer exists.', 'wp-formy' ) );
		}

		$fields         = $this->get_form_schema_fields( $lead->form_id );
		$existing_data  = json_decode( $lead->lead_data, true );
		$existing_data  = is_array( $existing_data ) ? $existing_data : array();
		$updated_data   = $existing_data;
		$errors         = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['id'] ) ) {
				continue;
			}

			$field_id      = $field['id'];
			$posted_value  = isset( $_POST[ $field_id ] ) ? wp_unslash( $_POST[ $field_id ] ) : null;
			$existing_value = isset( $existing_data[ $field_id ] ) ? $existing_data[ $field_id ] : array();
			$result        = $this->sanitize_lead_value_for_field( $field, $posted_value, $existing_value );

			if ( ! empty( $result['skip'] ) ) {
				continue;
			}

			if ( ! empty( $result['error'] ) ) {
				$errors[] = $result['error'];
				continue;
			}

			$updated_data[ $field_id ] = $result['value'];
		}

		if ( ! empty( $errors ) ) {
			return array(
				'success' => false,
				'message' => implode( ' ', $errors ),
			);
		}

		$updated = $wpdb->update(
			$this->get_leads_table(),
			array( 'lead_data' => wp_json_encode( $updated_data ) ),
			array( 'id' => $lead_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return array( 'success' => false, 'message' => __( 'Unable to update entry.', 'wp-formy' ) );
		}

		return array( 'success' => true, 'message' => __( 'Entry updated.', 'wp-formy' ) );
	}

	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'WP Formy', 'wp-formy' ),
			__( 'WP Formy', 'wp-formy' ),
			'manage_options',
			'wp-formy',
			array( $this, 'display_forms_page' ),
			'dashicons-feedback',
			25
		);

		add_submenu_page(
			'wp-formy',
			__( 'Forms', 'wp-formy' ),
			__( 'Forms', 'wp-formy' ),
			'manage_options',
			'wp-formy',
			array( $this, 'display_forms_page' )
		);

		add_submenu_page(
			'wp-formy',
			__( 'Leads', 'wp-formy' ),
			__( 'Leads', 'wp-formy' ),
			'manage_options',
			'wp-formy-leads',
			array( $this, 'display_leads_page' )
		);

		add_submenu_page(
			'wp-formy',
			__( 'Settings', 'wp-formy' ),
			__( 'Settings', 'wp-formy' ),
			'manage_options',
			'wp-formy-settings',
			array( $this, 'display_settings_page' )
		);

		add_submenu_page(
			'wp-formy',
			__( 'About', 'wp-formy' ),
			__( 'About', 'wp-formy' ),
			'manage_options',
			'wp-formy-about',
			array( $this, 'display_about_page' )
		);
	}

	public function display_forms_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-formy' ) );
		}
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			require_once WP_FORMY_PLUGIN_DIR . 'admin/partials/wp-formy-admin-builder.php';
		} else {
			require_once WP_FORMY_PLUGIN_DIR . 'admin/class-wp-formy-forms-list-table.php';
			require_once WP_FORMY_PLUGIN_DIR . 'admin/partials/wp-formy-admin-forms.php';
		}
	}

	public function display_leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-formy' ) );
		}

		$view    = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : 'list';
		$lead_id = isset( $_GET['lead_id'] ) ? absint( wp_unslash( $_GET['lead_id'] ) ) : 0;

		if ( 'detail' === $view && $lead_id ) {
			require_once WP_FORMY_PLUGIN_DIR . 'admin/partials/wp-formy-admin-lead-details.php';
			return;
		}

		require_once WP_FORMY_PLUGIN_DIR . 'admin/class-wp-formy-leads-list-table.php';
		require_once WP_FORMY_PLUGIN_DIR . 'admin/partials/wp-formy-admin-leads.php';
	}

	private function handle_lead_actions() {
		global $wpdb;

		$leads_table      = $this->get_leads_table();
		$lead_notes_table = $this->get_lead_notes_table();

		if ( isset( $_POST['wpf_update_lead_id'], $_POST['wpf_update_lead_nonce'] ) ) {
			$lead_id = absint( wp_unslash( $_POST['wpf_update_lead_id'] ) );

			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpf_update_lead_nonce'] ) ), 'wpf_update_lead_' . $lead_id ) ) {
				$result = $this->update_lead_entry( $lead_id );
				$redirect = add_query_arg(
					array(
						'page'         => 'wp-formy-leads',
						'view'         => 'detail',
						'lead_id'      => $lead_id,
						'entry-updated'=> $result['success'] ? '1' : '0',
						'entry-message'=> rawurlencode( $result['message'] ),
					),
					admin_url( 'admin.php' )
				);

				wp_safe_redirect( $redirect );
				exit;
			}
		}

		if ( isset( $_POST['wpf_add_note_lead_id'], $_POST['wpf_lead_note'], $_POST['_wpnonce'] ) ) {
			$lead_id = absint( wp_unslash( $_POST['wpf_add_note_lead_id'] ) );

			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wpf_add_lead_note_' . $lead_id ) ) {
				$note = sanitize_textarea_field( wp_unslash( $_POST['wpf_lead_note'] ) );

				if ( '' !== $note ) {
					$wpdb->insert(
						$lead_notes_table,
						array(
							'lead_id'     => $lead_id,
							'note'        => $note,
							'created_by'  => get_current_user_id(),
							'created_at'  => current_time( 'mysql' ),
						),
						array( '%d', '%s', '%d', '%s' )
					);
				}

				wp_safe_redirect(
					add_query_arg(
						array(
							'page'         => 'wp-formy-leads',
							'view'         => 'detail',
							'lead_id'      => $lead_id,
							'note-updated' => '' !== $note ? '1' : '0',
							'note-message' => rawurlencode( '' !== $note ? __( 'Note added.', 'wp-formy' ) : __( 'Please enter a note before saving.', 'wp-formy' ) ),
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
		}

		if ( isset( $_GET['lead_action'], $_GET['lead_id'], $_GET['_wpnonce'] ) ) {
			$action  = sanitize_text_field( wp_unslash( $_GET['lead_action'] ) );
			$lead_id = absint( wp_unslash( $_GET['lead_id'] ) );
			$nonce   = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

			if ( ! wp_verify_nonce( $nonce, 'wpf_lead_action_' . $lead_id ) ) {
				return;
			}

			if ( 'mark_read' === $action ) {
				$wpdb->update(
					$leads_table,
					array( 'status' => 'read' ),
					array( 'id' => $lead_id ),
					array( '%s' ),
					array( '%d' )
				);
			} elseif ( 'mark_unread' === $action ) {
				$wpdb->update(
					$leads_table,
					array( 'status' => 'unread' ),
					array( 'id' => $lead_id ),
					array( '%s' ),
					array( '%d' )
				);
			} elseif ( 'delete' === $action ) {
				$wpdb->delete( $lead_notes_table, array( 'lead_id' => $lead_id ), array( '%d' ) );
				$wpdb->delete( $leads_table, array( 'id' => $lead_id ), array( '%d' ) );
			}

			$redirect = admin_url( 'admin.php?page=wp-formy-leads' );

			if ( isset( $_GET['view'] ) && 'detail' === sanitize_text_field( wp_unslash( $_GET['view'] ) ) && 'delete' !== $action ) {
				$redirect = add_query_arg(
					array(
						'page'    => 'wp-formy-leads',
						'view'    => 'detail',
						'lead_id' => $lead_id,
					),
					admin_url( 'admin.php' )
				);
			}

			wp_safe_redirect( $redirect );
			exit;
		}
	}

	public function display_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-formy' ) );
		}

		$section    = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'general';
		$subsection = isset( $_GET['subsection'] ) ? sanitize_key( wp_unslash( $_GET['subsection'] ) ) : '';

		$settings = $this->get_plugin_settings();
		$sections = array(
			'general'      => array(
				'label' => __( 'General Settings', 'wp-formy' ),
				'icon'  => 'admin-generic',
			),
			'validation'   => array(
				'label' => __( 'Form Validation', 'wp-formy' ),
				'icon'  => 'shield',
			),
			'spam'         => array(
				'label' => __( 'Spam Protection', 'wp-formy' ),
				'icon'  => 'warning',
				'children' => array(
					'recaptcha' => __( 'reCAPTCHA', 'wp-formy' ),
					'hcaptcha'  => __( 'hCaptcha', 'wp-formy' ),
					'turnstile' => __( 'Turnstile', 'wp-formy' ),
					'honeypot'  => __( 'Honeypot', 'wp-formy' ),
				),
			),
			'integrations' => array(
				'label' => __( 'Integrations', 'wp-formy' ),
				'icon'  => 'admin-links',
			),
			'payments'     => array(
				'label' => __( 'Payments', 'wp-formy' ),
				'icon'  => 'cart',
				'children' => array(
					'general' => __( 'General', 'wp-formy' ),
					'methods' => __( 'Payment Methods', 'wp-formy' ),
				),
			),
		);

		if ( ! isset( $sections[ $section ] ) ) {
			$section = 'general';
		}

		if ( empty( $subsection ) && ! empty( $sections[ $section ]['children'] ) ) {
			$subsection = array_key_first( $sections[ $section ]['children'] );
		}
		?>
		<div class="wrap">
			<style>
				.wp-formy-settings-shell{margin-top:18px;background:#f7f7f9;border:1px solid #e5e7eb;border-radius:24px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,.06)}
				.wp-formy-settings-topbar{display:flex;align-items:center;gap:28px;padding:0 28px;background:#fff;border-bottom:1px solid #eceef2;min-height:74px}
				.wp-formy-settings-brand{display:flex;align-items:center;gap:14px;margin-right:8px}
				.wp-formy-settings-brand-badge{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;font-size:18px;letter-spacing:.04em}
				.wp-formy-settings-brand-title{font-size:28px;font-weight:700;color:#1f2937}
				.wp-formy-settings-tabs{display:flex;align-items:center;gap:28px;flex-wrap:wrap}
				.wp-formy-settings-tabs a{position:relative;padding:24px 0;color:#4b5563;text-decoration:none;font-size:17px;font-weight:600}
				.wp-formy-settings-tabs a.is-active{color:#111827}
				.wp-formy-settings-tabs a.is-active:after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:3px;border-radius:999px;background:#ea580c}
				.wp-formy-settings-layout{display:grid;grid-template-columns:320px 1fr;min-height:720px}
				.wp-formy-settings-sidebar{background:#fff;border-right:1px solid #eceef2;padding:28px 0}
				.wp-formy-settings-menu{display:flex;flex-direction:column;gap:8px}
				.wp-formy-settings-link{display:flex;align-items:center;gap:14px;padding:14px 28px;color:#4b5563;text-decoration:none;font-size:18px;font-weight:600}
				.wp-formy-settings-link .dashicons{font-size:22px;width:22px;height:22px}
				.wp-formy-settings-link.is-active{color:#111827}
				.wp-formy-settings-group{margin-top:12px}
				.wp-formy-settings-sublinks{margin:10px 0 0 52px;padding-left:18px;border-left:1px solid #e5e7eb;display:flex;flex-direction:column;gap:8px}
				.wp-formy-settings-sublinks a{padding:12px 16px;border:1px solid transparent;border-radius:16px;color:#4b5563;text-decoration:none;font-size:16px}
				.wp-formy-settings-sublinks a.is-active{border-color:#fb923c;background:#fff7ed;color:#111827}
				.wp-formy-settings-content{padding:52px 56px}
				.wp-formy-settings-head h2{margin:0 0 10px;font-size:28px;line-height:1.2;color:#111827}
				.wp-formy-settings-head p{margin:0;color:#6b7280;font-size:16px;max-width:920px}
				.wp-formy-settings-card{margin-top:28px;background:#fff;border:1px solid #eceef2;border-radius:24px;padding:28px 30px;box-shadow:0 10px 30px rgba(15,23,42,.04)}
				.wp-formy-settings-card h3{margin:0 0 8px;font-size:20px;color:#111827}
				.wp-formy-settings-card > p{margin:0 0 24px;color:#6b7280;font-size:15px}
				.wp-formy-settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px}
				.wp-formy-settings-field label{display:block;margin-bottom:8px;font-weight:600;color:#111827}
				.wp-formy-settings-field input[type="text"],.wp-formy-settings-field input[type="url"],.wp-formy-settings-field textarea,.wp-formy-settings-field select{width:100%;min-height:46px;border:1px solid #d1d5db;border-radius:14px;padding:11px 14px;background:#fff}
				.wp-formy-settings-field textarea{min-height:96px}
				.wp-formy-settings-help{margin-top:8px;color:#6b7280;font-size:13px}
				.wp-formy-settings-checkbox{display:flex;align-items:flex-start;gap:12px;padding:16px 18px;border:1px solid #eceef2;border-radius:18px;background:#fcfcfd}
				.wp-formy-settings-checkbox input{margin-top:2px}
				.wp-formy-settings-checkbox strong{display:block;color:#111827;margin-bottom:2px}
				.wp-formy-settings-note{margin-top:18px;padding:18px 20px;border-radius:18px;background:#f9fafb;color:#4b5563;font-size:15px}
				.wp-formy-settings-pill{display:inline-flex;align-items:center;padding:8px 12px;border-radius:999px;background:#fff7ed;color:#c2410c;font-weight:700;font-size:12px;letter-spacing:.04em;text-transform:uppercase}
				.wp-formy-settings-actions{margin-top:28px;display:flex;align-items:center;gap:14px}
				.wp-formy-settings-actions .button-primary{background:#ea580c;border-color:#ea580c;padding:0 18px;min-height:42px}
				@media (max-width: 1100px){
					.wp-formy-settings-layout{grid-template-columns:1fr}
					.wp-formy-settings-sidebar{border-right:0;border-bottom:1px solid #eceef2}
					.wp-formy-settings-grid{grid-template-columns:1fr}
				}
			</style>

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-formy' ); ?></p></div>
			<?php endif; ?>

			<div class="wp-formy-settings-shell">
				<div class="wp-formy-settings-topbar">
					<div class="wp-formy-settings-brand">
						<div class="wp-formy-settings-brand-badge">F</div>
						<div class="wp-formy-settings-brand-title"><?php esc_html_e( 'Settings', 'wp-formy' ); ?></div>
					</div>
					<nav class="wp-formy-settings-tabs" aria-label="<?php esc_attr_e( 'Settings tabs', 'wp-formy' ); ?>">
						<?php foreach ( $sections as $section_key => $section_config ) : ?>
							<?php
							$section_url = add_query_arg(
								array(
									'page'       => 'wp-formy-settings',
									'section'    => $section_key,
									'subsection' => ! empty( $section_config['children'] ) ? array_key_first( $section_config['children'] ) : '',
								),
								admin_url( 'admin.php' )
							);
							?>
							<a href="<?php echo esc_url( $section_url ); ?>" class="<?php echo $section === $section_key ? 'is-active' : ''; ?>"><?php echo esc_html( $section_config['label'] ); ?></a>
						<?php endforeach; ?>
					</nav>
				</div>

				<div class="wp-formy-settings-layout">
					<aside class="wp-formy-settings-sidebar">
						<nav class="wp-formy-settings-menu" aria-label="<?php esc_attr_e( 'Settings navigation', 'wp-formy' ); ?>">
							<?php foreach ( $sections as $section_key => $section_config ) : ?>
								<?php
								$section_url = add_query_arg(
									array(
										'page'       => 'wp-formy-settings',
										'section'    => $section_key,
										'subsection' => ! empty( $section_config['children'] ) ? array_key_first( $section_config['children'] ) : '',
									),
									admin_url( 'admin.php' )
								);
								?>
								<div class="wp-formy-settings-group">
									<a href="<?php echo esc_url( $section_url ); ?>" class="wp-formy-settings-link <?php echo $section === $section_key ? 'is-active' : ''; ?>">
										<span class="dashicons dashicons-<?php echo esc_attr( $section_config['icon'] ); ?>"></span>
										<span><?php echo esc_html( $section_config['label'] ); ?></span>
									</a>
									<?php if ( ! empty( $section_config['children'] ) ) : ?>
										<div class="wp-formy-settings-sublinks">
											<?php foreach ( $section_config['children'] as $child_key => $child_label ) : ?>
												<?php
												$child_url = add_query_arg(
													array(
														'page'       => 'wp-formy-settings',
														'section'    => $section_key,
														'subsection' => $child_key,
													),
													admin_url( 'admin.php' )
												);
												?>
												<a href="<?php echo esc_url( $child_url ); ?>" class="<?php echo $section === $section_key && $subsection === $child_key ? 'is-active' : ''; ?>"><?php echo esc_html( $child_label ); ?></a>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</nav>
					</aside>

					<div class="wp-formy-settings-content">
						<form method="post">
				<?php wp_nonce_field( 'wp_formy_save_settings', 'wp_formy_settings_nonce' ); ?>
							<div class="wp-formy-settings-head">
								<?php if ( 'general' === $section ) : ?>
									<h2><?php esc_html_e( 'General Settings', 'wp-formy' ); ?></h2>
									<p><?php esc_html_e( 'Set the defaults WP Formy should use for notifications, reply behavior, and submission feedback across your forms.', 'wp-formy' ); ?></p>
								<?php elseif ( 'validation' === $section ) : ?>
									<h2><?php esc_html_e( 'Form Validation', 'wp-formy' ); ?></h2>
									<p><?php esc_html_e( 'Control how validation feels for visitors and keep the form builder opinionated about unique form names.', 'wp-formy' ); ?></p>
								<?php elseif ( 'spam' === $section ) : ?>
									<h2><?php esc_html_e( 'Spam Protection', 'wp-formy' ); ?></h2>
									<p><?php esc_html_e( 'Configure the anti-spam services you want available in WP Formy. Start with Honeypot, then wire in a challenge provider only if needed.', 'wp-formy' ); ?></p>
								<?php elseif ( 'integrations' === $section ) : ?>
									<h2><?php esc_html_e( 'Integrations', 'wp-formy' ); ?></h2>
									<p><?php esc_html_e( 'Prepare outbound hooks and future service connections so your form submissions can feed the rest of your stack.', 'wp-formy' ); ?></p>
								<?php elseif ( 'payments' === $section ) : ?>
									<h2><?php esc_html_e( 'Payments', 'wp-formy' ); ?></h2>
									<p><?php esc_html_e( 'Connect and manage payment gateways so forms can eventually collect payments without leaving your site.', 'wp-formy' ); ?></p>
								<?php endif; ?>
							</div>

							<?php if ( 'general' === $section ) : ?>
								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Notifications', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'Default email delivery', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'These values seed new forms automatically, while still letting you override them per form in the builder.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-grid">
										<div class="wp-formy-settings-field">
											<label for="default_notification_email"><?php esc_html_e( 'Default Notification Email', 'wp-formy' ); ?></label>
											<input name="default_notification_email" id="default_notification_email" type="text" value="<?php echo esc_attr( $settings['default_notification_email'] ); ?>">
											<div class="wp-formy-settings-help"><?php esc_html_e( 'Comma-separated addresses are supported.', 'wp-formy' ); ?></div>
										</div>
										<div class="wp-formy-settings-field">
											<label for="default_notification_subject"><?php esc_html_e( 'Default Notification Subject', 'wp-formy' ); ?></label>
											<input name="default_notification_subject" id="default_notification_subject" type="text" value="<?php echo esc_attr( $settings['default_notification_subject'] ); ?>">
											<div class="wp-formy-settings-help"><?php esc_html_e( 'Use {form_title} to insert the form name.', 'wp-formy' ); ?></div>
										</div>
										<div class="wp-formy-settings-field">
											<label for="default_from_name"><?php esc_html_e( 'From Name', 'wp-formy' ); ?></label>
											<input name="default_from_name" id="default_from_name" type="text" value="<?php echo esc_attr( $settings['default_from_name'] ); ?>">
											<div class="wp-formy-settings-help"><?php esc_html_e( 'Displayed in outgoing notifications when your mailer supports it.', 'wp-formy' ); ?></div>
										</div>
										<div class="wp-formy-settings-field">
											<label for="default_reply_to_mode"><?php esc_html_e( 'Reply-To Behavior', 'wp-formy' ); ?></label>
											<select name="default_reply_to_mode" id="default_reply_to_mode">
												<option value="submitter" <?php selected( $settings['default_reply_to_mode'], 'submitter' ); ?>><?php esc_html_e( 'Use submitter email when available', 'wp-formy' ); ?></option>
												<option value="site" <?php selected( $settings['default_reply_to_mode'], 'site' ); ?>><?php esc_html_e( 'Keep site mail headers only', 'wp-formy' ); ?></option>
											</select>
										</div>
									</div>
									<div class="wp-formy-settings-checkbox" style="margin-top:22px;">
										<input name="default_notifications_enabled" id="default_notifications_enabled" type="checkbox" value="1" <?php checked( '1' === (string) $settings['default_notifications_enabled'] ); ?>>
										<div>
											<strong><?php esc_html_e( 'Enable notifications by default', 'wp-formy' ); ?></strong>
											<span><?php esc_html_e( 'Every new form starts with notifications turned on unless you switch it off in the builder.', 'wp-formy' ); ?></span>
										</div>
									</div>
								</div>

								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Submission UX', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'Default success state', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'Give new forms a cleaner completion experience right out of the gate.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-field">
										<label for="default_success_message"><?php esc_html_e( 'Success Message', 'wp-formy' ); ?></label>
										<textarea name="default_success_message" id="default_success_message"><?php echo esc_textarea( $settings['default_success_message'] ); ?></textarea>
										<div class="wp-formy-settings-help"><?php esc_html_e( 'Used as the starting success message when building a new form.', 'wp-formy' ); ?></div>
									</div>
								</div>
							<?php elseif ( 'validation' === $section ) : ?>
								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Validation', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'How forms should validate', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'Keep validation strict enough for clean data while still feeling friendly for visitors.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-grid">
										<div class="wp-formy-settings-field">
											<label for="validation_mode"><?php esc_html_e( 'Validation Style', 'wp-formy' ); ?></label>
											<select name="validation_mode" id="validation_mode">
												<option value="native" <?php selected( $settings['validation_mode'], 'native' ); ?>><?php esc_html_e( 'Native browser validation', 'wp-formy' ); ?></option>
												<option value="friendly" <?php selected( $settings['validation_mode'], 'friendly' ); ?>><?php esc_html_e( 'Friendly inline validation', 'wp-formy' ); ?></option>
											</select>
										</div>
									</div>
									<div class="wp-formy-settings-checkbox" style="margin-top:22px;">
										<input name="require_unique_form_names" id="require_unique_form_names" type="checkbox" value="1" <?php checked( '1' === (string) $settings['require_unique_form_names'] ); ?>>
										<div>
											<strong><?php esc_html_e( 'Require unique form names', 'wp-formy' ); ?></strong>
											<span><?php esc_html_e( 'Prevents duplicate titles so your admin stays easy to scan and export reliably.', 'wp-formy' ); ?></span>
										</div>
									</div>
								</div>
							<?php elseif ( 'spam' === $section && 'recaptcha' === $subsection ) : ?>
								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Spam Protection', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'reCAPTCHA', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'Add your Google reCAPTCHA keys here so forms can opt into challenge protection later.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-grid">
										<div class="wp-formy-settings-field">
											<label for="recaptcha_site_key"><?php esc_html_e( 'Site Key', 'wp-formy' ); ?></label>
											<input name="recaptcha_site_key" id="recaptcha_site_key" type="text" value="<?php echo esc_attr( $settings['recaptcha_site_key'] ); ?>">
										</div>
										<div class="wp-formy-settings-field">
											<label for="recaptcha_secret_key"><?php esc_html_e( 'Secret Key', 'wp-formy' ); ?></label>
											<input name="recaptcha_secret_key" id="recaptcha_secret_key" type="text" value="<?php echo esc_attr( $settings['recaptcha_secret_key'] ); ?>">
										</div>
									</div>
								</div>
							<?php elseif ( 'spam' === $section && 'hcaptcha' === $subsection ) : ?>
								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Spam Protection', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'hCaptcha', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'Store hCaptcha credentials here so the builder can offer it as an available protection method.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-grid">
										<div class="wp-formy-settings-field">
											<label for="hcaptcha_site_key"><?php esc_html_e( 'Site Key', 'wp-formy' ); ?></label>
											<input name="hcaptcha_site_key" id="hcaptcha_site_key" type="text" value="<?php echo esc_attr( $settings['hcaptcha_site_key'] ); ?>">
										</div>
										<div class="wp-formy-settings-field">
											<label for="hcaptcha_secret_key"><?php esc_html_e( 'Secret Key', 'wp-formy' ); ?></label>
											<input name="hcaptcha_secret_key" id="hcaptcha_secret_key" type="text" value="<?php echo esc_attr( $settings['hcaptcha_secret_key'] ); ?>">
										</div>
									</div>
								</div>
							<?php elseif ( 'spam' === $section && 'turnstile' === $subsection ) : ?>
								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Spam Protection', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'Turnstile', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'Cloudflare Turnstile is a good fit when you want lighter friction than reCAPTCHA while still blocking junk submissions.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-grid">
										<div class="wp-formy-settings-field">
											<label for="turnstile_site_key"><?php esc_html_e( 'Site Key', 'wp-formy' ); ?></label>
											<input name="turnstile_site_key" id="turnstile_site_key" type="text" value="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>">
										</div>
										<div class="wp-formy-settings-field">
											<label for="turnstile_secret_key"><?php esc_html_e( 'Secret Key', 'wp-formy' ); ?></label>
											<input name="turnstile_secret_key" id="turnstile_secret_key" type="text" value="<?php echo esc_attr( $settings['turnstile_secret_key'] ); ?>">
										</div>
									</div>
								</div>
							<?php elseif ( 'spam' === $section && 'honeypot' === $subsection ) : ?>
								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Spam Protection', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'Honeypot', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'Honeypot is the quiet default. It catches simple bots without making real people solve anything.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-checkbox">
										<input name="honeypot_enabled" id="honeypot_enabled" type="checkbox" value="1" <?php checked( '1' === (string) $settings['honeypot_enabled'] ); ?>>
										<div>
											<strong><?php esc_html_e( 'Enable Honeypot by default', 'wp-formy' ); ?></strong>
											<span><?php esc_html_e( 'New forms will start with invisible spam trapping enabled.', 'wp-formy' ); ?></span>
										</div>
									</div>
								</div>
							<?php elseif ( 'integrations' === $section ) : ?>
								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Integrations', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'Asana', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'Connect Asana once here, then enable task creation on any form from the builder.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-checkbox" style="margin-bottom:22px;">
										<input name="asana_enabled" id="asana_enabled" type="checkbox" value="1" <?php checked( '1' === (string) $settings['asana_enabled'] ); ?>>
										<div>
											<strong><?php esc_html_e( 'Enable Asana integration', 'wp-formy' ); ?></strong>
											<span><?php esc_html_e( 'When enabled, forms can create Asana tasks after successful submissions.', 'wp-formy' ); ?></span>
										</div>
									</div>
									<div class="wp-formy-settings-grid">
										<div class="wp-formy-settings-field">
											<label for="asana_personal_access_token"><?php esc_html_e( 'Personal Access Token', 'wp-formy' ); ?></label>
											<input name="asana_personal_access_token" id="asana_personal_access_token" type="text" value="<?php echo esc_attr( $settings['asana_personal_access_token'] ); ?>">
											<div class="wp-formy-settings-help"><?php esc_html_e( 'Create this in Asana and keep it private. WP Formy uses it to create tasks through the Asana API.', 'wp-formy' ); ?></div>
										</div>
										<div class="wp-formy-settings-field">
											<label for="asana_workspace_gid"><?php esc_html_e( 'Workspace GID', 'wp-formy' ); ?></label>
											<input name="asana_workspace_gid" id="asana_workspace_gid" type="text" value="<?php echo esc_attr( $settings['asana_workspace_gid'] ); ?>">
											<div class="wp-formy-settings-help"><?php esc_html_e( 'Required when creating tasks. This is the numeric Asana workspace ID.', 'wp-formy' ); ?></div>
										</div>
										<div class="wp-formy-settings-field">
											<label for="asana_project_gid"><?php esc_html_e( 'Default Project GID', 'wp-formy' ); ?></label>
											<input name="asana_project_gid" id="asana_project_gid" type="text" value="<?php echo esc_attr( $settings['asana_project_gid'] ); ?>">
											<div class="wp-formy-settings-help"><?php esc_html_e( 'Optional. If provided, new tasks will be added to this project unless a form overrides it.', 'wp-formy' ); ?></div>
										</div>
									</div>
								</div>

								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Integrations', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'Outbound hooks', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'If you want a lightweight second integration, start with a webhook so submissions can be mirrored elsewhere.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-field">
										<label for="webhook_url"><?php esc_html_e( 'Default Webhook URL', 'wp-formy' ); ?></label>
										<input name="webhook_url" id="webhook_url" type="url" value="<?php echo esc_attr( $settings['webhook_url'] ); ?>">
										<div class="wp-formy-settings-help"><?php esc_html_e( 'Not active yet, but saved now so we can wire delivery into submissions next.', 'wp-formy' ); ?></div>
									</div>
									<div class="wp-formy-settings-note"><?php esc_html_e( 'Future-friendly direction: webhook, CRM sync, and automation providers can all live here without changing the main settings structure again.', 'wp-formy' ); ?></div>
								</div>
							<?php elseif ( 'payments' === $section && 'general' === $subsection ) : ?>
								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Payments', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'Payment environment', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'Decide whether future payment-enabled forms should point at test or live credentials by default.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-field" style="max-width:280px;">
										<label for="stripe_mode"><?php esc_html_e( 'Stripe Mode', 'wp-formy' ); ?></label>
										<select name="stripe_mode" id="stripe_mode">
											<option value="test" <?php selected( $settings['stripe_mode'], 'test' ); ?>><?php esc_html_e( 'Test', 'wp-formy' ); ?></option>
											<option value="live" <?php selected( $settings['stripe_mode'], 'live' ); ?>><?php esc_html_e( 'Live', 'wp-formy' ); ?></option>
										</select>
									</div>
								</div>
							<?php elseif ( 'payments' === $section && 'methods' === $subsection ) : ?>
								<div class="wp-formy-settings-card">
									<span class="wp-formy-settings-pill"><?php esc_html_e( 'Payments', 'wp-formy' ); ?></span>
									<h3><?php esc_html_e( 'Stripe', 'wp-formy' ); ?></h3>
									<p><?php esc_html_e( 'Save Stripe keys now so we can enable payment collection in the builder without reshaping the settings page later.', 'wp-formy' ); ?></p>
									<div class="wp-formy-settings-grid">
										<div class="wp-formy-settings-field">
											<label for="stripe_publishable_key"><?php esc_html_e( 'Publishable Key', 'wp-formy' ); ?></label>
											<input name="stripe_publishable_key" id="stripe_publishable_key" type="text" value="<?php echo esc_attr( $settings['stripe_publishable_key'] ); ?>">
										</div>
										<div class="wp-formy-settings-field">
											<label for="stripe_secret_key"><?php esc_html_e( 'Secret Key', 'wp-formy' ); ?></label>
											<input name="stripe_secret_key" id="stripe_secret_key" type="text" value="<?php echo esc_attr( $settings['stripe_secret_key'] ); ?>">
										</div>
									</div>
									<div class="wp-formy-settings-note"><?php esc_html_e( 'Connection UI and live payment processing are not wired yet, but the settings foundation is now in place.', 'wp-formy' ); ?></div>
								</div>
							<?php endif; ?>

							<div class="wp-formy-settings-actions">
								<?php submit_button( __( 'Save Settings', 'wp-formy' ), 'primary', 'submit', false ); ?>
								<span style="color:#6b7280;"><?php esc_html_e( 'Changes are stored site-wide for WP Formy.', 'wp-formy' ); ?></span>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function display_about_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-formy' ) );
		}
		?>
		<div class="wrap">
			<style>
				.wp-formy-about-shell{max-width:980px;margin-top:20px;background:linear-gradient(180deg,#fff 0%,#f8fafc 100%);border:1px solid #e5e7eb;border-radius:28px;overflow:hidden;box-shadow:0 22px 48px rgba(15,23,42,.06)}
				.wp-formy-about-hero{padding:40px 44px;background:linear-gradient(135deg,#f97316 0%,#ea580c 60%,#fb923c 100%);color:#fff}
				.wp-formy-about-badge{display:inline-flex;align-items:center;justify-content:center;width:54px;height:54px;border-radius:16px;background:rgba(255,255,255,.16);font-size:28px;font-weight:800;letter-spacing:.04em}
				.wp-formy-about-hero h1{margin:18px 0 10px;font-size:36px;line-height:1.1;color:#fff}
				.wp-formy-about-hero p{margin:0;max-width:720px;font-size:17px;line-height:1.6;color:rgba(255,255,255,.92)}
				.wp-formy-about-body{padding:34px 44px}
				.wp-formy-about-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px}
				.wp-formy-about-card{padding:24px;border:1px solid #e5e7eb;border-radius:22px;background:#fff}
				.wp-formy-about-card h2{margin:0 0 10px;font-size:20px;color:#111827}
				.wp-formy-about-card p{margin:0;color:#4b5563;line-height:1.7}
				.wp-formy-about-meta{margin-top:24px;padding:24px;border:1px solid #e5e7eb;border-radius:22px;background:#fff}
				.wp-formy-about-meta table{width:100%;border-collapse:collapse}
				.wp-formy-about-meta th,.wp-formy-about-meta td{padding:12px 0;border-bottom:1px solid #f1f5f9;text-align:left;vertical-align:top}
				.wp-formy-about-meta tr:last-child th,.wp-formy-about-meta tr:last-child td{border-bottom:0}
				.wp-formy-about-meta th{width:180px;color:#6b7280;font-weight:600}
				.wp-formy-about-meta a{text-decoration:none}
				@media (max-width: 900px){.wp-formy-about-grid{grid-template-columns:1fr}.wp-formy-about-hero,.wp-formy-about-body{padding:26px}}
			</style>

			<div class="wp-formy-about-shell">
				<div class="wp-formy-about-hero">
					<div class="wp-formy-about-badge">F</div>
					<h1><?php esc_html_e( 'About WP Formy', 'wp-formy' ); ?></h1>
					<p><?php esc_html_e( 'WP Formy is a WordPress form builder focused on practical workflows: clean form creation, editable entries, notifications, integrations, and a builder experience you can keep shaping to match your process.', 'wp-formy' ); ?></p>
				</div>

				<div class="wp-formy-about-body">
					<div class="wp-formy-about-grid">
						<div class="wp-formy-about-card">
							<h2><?php esc_html_e( 'What It Handles', 'wp-formy' ); ?></h2>
							<p><?php esc_html_e( 'Build forms visually, collect entries, edit submissions later, send email notifications, export and import forms, and plug submission workflows into tools like Asana.', 'wp-formy' ); ?></p>
						</div>
						<div class="wp-formy-about-card">
							<h2><?php esc_html_e( 'Who Built It', 'wp-formy' ); ?></h2>
							<p><?php esc_html_e( 'WP Formy is developed by itSpector. The plugin is intended to stay useful, flexible, and grounded in real client work instead of being shaped around a paid upsell funnel.', 'wp-formy' ); ?></p>
						</div>
					</div>

					<div class="wp-formy-about-meta">
						<table>
							<tbody>
								<tr>
									<th><?php esc_html_e( 'Plugin', 'wp-formy' ); ?></th>
									<td><?php echo esc_html( 'WP Formy ' . WP_FORMY_VERSION ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Developer', 'wp-formy' ); ?></th>
									<td><a href="https://itspector.com" target="_blank" rel="noopener noreferrer">itSpector</a></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Repository', 'wp-formy' ); ?></th>
									<td><a href="https://github.com/ssnanda/wp-formy" target="_blank" rel="noopener noreferrer">github.com/ssnanda/wp-formy</a></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Update URI', 'wp-formy' ); ?></th>
									<td><code>https://github.com/ssnanda/wp-formy</code></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function ajax_save_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wp-formy' ), 403 );
		}

		check_ajax_referer( 'wpf_save_form', 'nonce' );

		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$form_id     = isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0;
		$status      = isset( $_POST['status'] ) && 'draft' === sanitize_text_field( wp_unslash( $_POST['status'] ) ) ? 'draft' : 'published';
		$schema_json = isset( $_POST['schema'] ) ? wp_unslash( $_POST['schema'] ) : '';

		if ( '' === $title ) {
			wp_send_json_error( __( 'Please enter a form title.', 'wp-formy' ), 400 );
		}

		if ( 'Untitled Form' === $title ) {
			wp_send_json_error( __( 'Please choose a unique form name instead of the default title.', 'wp-formy' ), 400 );
		}

		$schema = json_decode( $schema_json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $schema ) ) {
			wp_send_json_error( __( 'Invalid form schema.', 'wp-formy' ), 400 );
		}

		$sanitized_schema = $this->sanitize_schema_for_storage( $schema );

		global $wpdb;
		$table = $this->get_forms_table();
		$existing_form_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE title = %s AND id != %d LIMIT 1",
				$title,
				$form_id
			)
		);

		if ( $existing_form_id > 0 ) {
			wp_send_json_error( __( 'Form name must be unique.', 'wp-formy' ), 400 );
		}

		$data  = array(
			'title'       => $title,
			'form_schema' => wp_json_encode( $sanitized_schema ),
			'status'      => $status,
			'updated_at'  => current_time( 'mysql' ),
		);

		if ( $form_id > 0 ) {
			$updated = $wpdb->update(
				$table,
				$data,
				array( 'id' => $form_id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				wp_send_json_error( __( 'Unable to save form.', 'wp-formy' ), 500 );
			}
		} else {
			$data['created_at'] = current_time( 'mysql' );

			$inserted = $wpdb->insert(
				$table,
				$data,
				array( '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false === $inserted ) {
				wp_send_json_error( __( 'Unable to save form.', 'wp-formy' ), 500 );
			}

			$form_id = (int) $wpdb->insert_id;
		}

		wp_send_json_success(
			array(
				'form_id'  => $form_id,
				'edit_url' => add_query_arg(
					array(
						'page'    => 'wp-formy',
						'action'  => 'edit',
						'form_id' => $form_id,
					),
					admin_url( 'admin.php' )
				),
				'preview_url' => $this->get_form_preview_url( $form_id ),
			)
		);
	}

	public function ajax_import_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wp-formy' ), 403 );
		}

		check_ajax_referer( 'wpf_import_form', 'nonce' );

		$raw_data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
		$decoded  = json_decode( $raw_data, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			wp_send_json_error( __( 'Invalid import payload.', 'wp-formy' ), 400 );
		}

		$title = '';
		if ( isset( $decoded['title'] ) && '' !== trim( (string) $decoded['title'] ) ) {
			$title = sanitize_text_field( $decoded['title'] );
		}

		if ( '' === $title && isset( $decoded['name'] ) && '' !== trim( (string) $decoded['name'] ) ) {
			$title = sanitize_text_field( $decoded['name'] );
		}

		if ( '' === $title ) {
			$title = __( 'Imported Form', 'wp-formy' );
		}

		$schema            = isset( $decoded['schema'] ) && is_array( $decoded['schema'] ) ? $decoded['schema'] : $decoded;
		$sanitized_schema  = $this->sanitize_schema_for_storage( $schema );

		global $wpdb;
		$table    = $this->get_forms_table();
		$inserted = $wpdb->insert(
			$table,
			array(
				'title'       => $title,
				'form_schema' => wp_json_encode( $sanitized_schema ),
				'status'      => 'draft',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( __( 'Unable to import form.', 'wp-formy' ), 500 );
		}

		$form_id = (int) $wpdb->insert_id;

		wp_send_json_success(
			array(
				'form_id'  => $form_id,
				'edit_url' => add_query_arg(
					array(
						'page'    => 'wp-formy',
						'action'  => 'edit',
						'form_id' => $form_id,
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}

	public function bulk_delete_forms( $form_ids ) {
		$this->delete_forms_and_related_data( $form_ids );
	}
}
