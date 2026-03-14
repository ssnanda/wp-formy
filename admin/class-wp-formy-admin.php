<?php

/**
 * The admin-specific functionality of the plugin.
 */
class WP_Formy_Admin {

	public function enqueue_styles( $hook_suffix ) {
		// Only load assets on our plugin pages
		if ( strpos( $hook_suffix, 'wp-formy' ) === false ) {
			return;
		}
		
		// We will enqueue actual CSS files in the next phases
	}

	public function enqueue_scripts( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'wp-formy' ) === false ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			$js_path = WP_FORMY_PLUGIN_DIR . 'assets/js/wp-formy-builder.js';
			$version = file_exists( $js_path ) ? filemtime( $js_path ) : WP_FORMY_VERSION;
			wp_enqueue_script( 'wp-formy-builder-js', WP_FORMY_PLUGIN_URL . 'assets/js/wp-formy-builder.js', array(), $version, true );

			wp_localize_script( 'wp-formy-builder-js', 'wpFormyBuilder', array(
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
				'nonce_save' => wp_create_nonce( 'wpf_save_form' ),
				'nonce_import' => wp_create_nonce( 'wpf_import_form' ),
			) );
		}
	}

	/**
	 * Get the forms table name.
	 *
	 * @return string
	 */
	private function get_forms_table() {
		global $wpdb;
		return $wpdb->prefix . 'formy_forms';
	}

	/**
	 * AJAX handler: Save or update a form.
	 */
	public function ajax_save_form() {
		check_ajax_referer( 'wpf_save_form', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wp-formy' ) );
		}

		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$schema = isset( $_POST['schema'] ) ? wp_unslash( $_POST['schema'] ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'published';

		if ( ! $title ) {
			wp_send_json_error( __( 'Form title is required.', 'wp-formy' ) );
		}

		$schema_data = json_decode( $schema, true );
		if ( $schema && ! is_array( $schema_data ) ) {
			wp_send_json_error( __( 'Form schema is invalid.', 'wp-formy' ) );
		}

		$schema_json = wp_json_encode( $schema_data ?: array() );

		global $wpdb;
		$table = $this->get_forms_table();

		if ( $form_id ) {
			$updated = $wpdb->update(
				$table,
				array(
					'title'       => $title,
					'form_schema' => $schema_json,
					'status'      => $status,
				),
				array( 'id' => $form_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				wp_send_json_error( __( 'Unable to update form.', 'wp-formy' ) );
			}
		} else {
			$inserted = $wpdb->insert(
				$table,
				array(
					'title'       => $title,
					'form_schema' => $schema_json,
					'status'      => $status,
				),
				array( '%s', '%s', '%s' )
			);

			if ( false === $inserted ) {
				wp_send_json_error( __( 'Unable to create form.', 'wp-formy' ) );
			}

			$form_id = $wpdb->insert_id;
		}

		wp_send_json_success( array( 'form_id' => $form_id ) );
	}

	/**
	 * AJAX handler: Import form from JSON file.
	 */
	public function ajax_import_form() {
		check_ajax_referer( 'wpf_import_form', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wp-formy' ) );
		}

		$raw = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
		if ( ! $raw ) {
			wp_send_json_error( __( 'No data provided.', 'wp-formy' ) );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( __( 'Invalid JSON data.', 'wp-formy' ) );
		}

		// Detect if this is a SureForms export format
		$sureforms_payload = null;
		if ( isset( $data[0]['post'] ) && isset( $data[0]['post_meta'] ) ) {
			$sureforms_payload = $data[0];
		} elseif ( isset( $data['post'] ) && isset( $data['post_meta'] ) ) {
			$sureforms_payload = $data;
		}

		if ( $sureforms_payload ) {
			$converted = $this->convert_sureforms_export( $sureforms_payload );
			$title = $converted['title'];
			$schema = $converted['schema'];
		} else {
			$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
			$schema = isset( $data['schema'] ) ? $data['schema'] : array();
		}

		if ( ! $title ) {
			$title = __( 'Imported Form', 'wp-formy' );
		}

		// If we couldn't extract any fields, fail early to avoid creating empty forms.
		if ( ! is_array( $schema ) || empty( $schema ) ) {
			wp_send_json_error( __( 'Import failed: no form fields were detected.', 'wp-formy' ) );
		}

		$schema_json = wp_json_encode( $schema );

		global $wpdb;
		$table = $this->get_forms_table();

		$inserted = $wpdb->insert(
			$table,
			array(
				'title'       => $title,
				'form_schema' => $schema_json,
				'status'      => 'draft',
			),
			array( '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( __( 'Unable to import form.', 'wp-formy' ) );
		}

		$form_id = $wpdb->insert_id;
		$edit_url = add_query_arg( array( 'page' => 'wp-formy', 'action' => 'edit', 'form_id' => $form_id ), admin_url( 'admin.php' ) );

		wp_send_json_success( array( 'form_id' => $form_id, 'edit_url' => $edit_url ) );
	}

	/**
	 * Convert SureForms export JSON into our internal form schema
	 *
	 * @param array $sureforms
	 * @return array {
	 *   @type string $title
	 *   @type array  $schema
	 * }
	 */
	private function convert_sureforms_export( $sureforms ) {
		$title = isset( $sureforms['post']['post_title'] ) ? sanitize_text_field( $sureforms['post']['post_title'] ) : '';
		$schema = array();

		// Parse blocks from post_content
		$content = isset( $sureforms['post']['post_content'] ) ? $sureforms['post']['post_content'] : '';
		if ( function_exists( 'parse_blocks' ) ) {
			$blocks = parse_blocks( $content );
			$schema = $this->extract_sureforms_fields_from_blocks( $blocks );

			return array(
				'title'  => $title,
				'schema' => $schema,
			);
		}
		if ( preg_match_all( '/<!--\s*wp:srfm\/([a-z0-9\-]+)\s+({[\s\S]*?})\s*(?:\/)?-->/si', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$block_type = $match[1];
				$block_json = json_decode( $match[2], true );
				if ( ! is_array( $block_json ) ) {
					continue;
				}

				$field = $this->map_sureforms_block_to_field( $block_type, $block_json );
				if ( $field ) {
					$schema[] = $field;
				}
			}
		}

		return array(
			'title'  => $title,
			'schema' => $schema,
		);
	}

	/**
	 * Extract SureForms fields from parsed Gutenberg blocks.
	 *
	 * @param array $blocks
	 * @return array
	 */
	private function extract_sureforms_fields_from_blocks( $blocks ) {
		$fields = array();

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( 0 === strpos( $block['blockName'], 'srfm/' ) ) {
				$block_type = substr( $block['blockName'], strlen( 'srfm/' ) );
				$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				$field = $this->map_sureforms_block_to_field( $block_type, $attrs );
				if ( $field ) {
					$fields[] = $field;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$fields = array_merge( $fields, $this->extract_sureforms_fields_from_blocks( $block['innerBlocks'] ) );
			}
		}

		return $fields;
	}

	/**
	 * Map a SureForms block type + data to our internal field schema.
	 *
	 * @param string $block_type
	 * @param array  $data
	 * @return array|null
	 */
	private function map_sureforms_block_to_field( $block_type, $data ) {
		$map = array(
			'input'        => 'text',
			'phone'        => 'tel',
			'email'        => 'email',
			'textarea'     => 'textarea',
			'multi-choice' => 'radio',
			'dropdown'     => 'select',
			'number'       => 'number',
			'separator'    => 'separator',
		);

		if ( ! isset( $map[ $block_type ] ) ) {
			return null;
		}

		$type = $map[ $block_type ];

		// SureForms has varied export schemas across versions.
		$label = '';
		if ( ! empty( $data['label'] ) ) {
			$label = sanitize_text_field( $data['label'] );
		} elseif ( ! empty( $data['fieldLabel'] ) ) {
			$label = sanitize_text_field( $data['fieldLabel'] );
		} elseif ( ! empty( $data['title'] ) ) {
			$label = sanitize_text_field( $data['title'] );
		} elseif ( ! empty( $data['name'] ) ) {
			$label = sanitize_text_field( $data['name'] );
		}

		$required = ! empty( $data['required'] ) || ! empty( $data['isRequired'] );

		$placeholder = '';
		if ( ! empty( $data['placeholder'] ) ) {
			$placeholder = sanitize_text_field( $data['placeholder'] );
		} elseif ( ! empty( $data['placeholderText'] ) ) {
			$placeholder = sanitize_text_field( $data['placeholderText'] );
		}

		$field = array(
			'id'          => 'field_' . wp_generate_uuid4(),
			'type'        => $type,
			'label'       => $label,
			'placeholder' => $placeholder,
			'required'    => $required,
			'css_class'   => '',
		);

		// Support options for selects / radios
		$options = array();
		if ( isset( $data['options'] ) && is_array( $data['options'] ) ) {
			foreach ( $data['options'] as $opt ) {
				if ( isset( $opt['optionTitle'] ) ) {
					$options[] = sanitize_text_field( $opt['optionTitle'] );
				} elseif ( isset( $opt['label'] ) ) {
					$options[] = sanitize_text_field( $opt['label'] );
				} elseif ( isset( $opt['text'] ) ) {
					$options[] = sanitize_text_field( $opt['text'] );
				}
			}
		}

		if ( $options ) {
			$field['options'] = $options;
		}

		return $field;
	}

	/**
	 * Register the administration menu.
	 */
	public function add_plugin_admin_menu() {
		
		// Top Level Menu
		add_menu_page(
			__( 'WP Formy', 'wp-formy' ),
			__( 'WP Formy', 'wp-formy' ),
			'manage_options',
			'wp-formy',
			array( $this, 'display_forms_page' ),
			'dashicons-feedback',
			25
		);

		// Submenu: Forms (Same slug as parent makes it the default view)
		add_submenu_page(
			'wp-formy',
			__( 'Forms', 'wp-formy' ),
			__( 'Forms', 'wp-formy' ),
			'manage_options',
			'wp-formy',
			array( $this, 'display_forms_page' )
		);

		// Submenu: Leads
		add_submenu_page(
			'wp-formy',
			__( 'Leads', 'wp-formy' ),
			__( 'Leads', 'wp-formy' ),
			'manage_options',
			'wp-formy-leads',
			array( $this, 'display_leads_page' )
		);

		// Submenu: Settings
		add_submenu_page(
			'wp-formy',
			__( 'Settings', 'wp-formy' ),
			__( 'Settings', 'wp-formy' ),
			'manage_options',
			'wp-formy-settings',
			array( $this, 'display_settings_page' )
		);
	}

	public function display_forms_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		// Route to the Visual Builder if adding or editing a form
		if ( in_array( $action, array( 'add', 'edit' ), true ) ) {
			require_once WP_FORMY_PLUGIN_DIR . 'admin/partials/wp-formy-admin-builder.php';
		} else {
			// Route to the Forms Listing List Table
			require_once WP_FORMY_PLUGIN_DIR . 'admin/class-wp-formy-forms-list-table.php';
			require_once WP_FORMY_PLUGIN_DIR . 'admin/partials/wp-formy-admin-forms.php';
		}
	}

	public function display_leads_page() {
		echo '<div class="wrap"><h2>' . __( 'WP Formy - Leads', 'wp-formy' ) . '</h2><p>Loading Leads View...</p></div>';
	}

	public function display_settings_page() {
		echo '<div class="wrap"><h2>' . __( 'WP Formy - Settings', 'wp-formy' ) . '</h2><p>Loading Settings View...</p></div>';
	}
}