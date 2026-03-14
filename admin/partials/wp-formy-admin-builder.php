<?php
/**
 * View: Add/Edit Visual Builder
 *
 * This takes over the entire screen for a modern distraction-free experience.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$back_url = admin_url( 'admin.php?page=wp-formy' );

$form_id = isset( $_GET['form_id'] ) ? intval( $_GET['form_id'] ) : 0;
$initial_data = array(
	'form_id' => 0,
	'title'   => '',
	'schema'  => array(),
);

if ( $form_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'formy_forms';
	$form  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $form_id ) );

	if ( $form ) {
		$initial_data['form_id'] = $form_id;
		$initial_data['title']   = $form->title;
		$initial_data['schema']  = json_decode( $form->form_schema, true ) ?: array();
	}
}

?>
<script>
window.wpFormyInitialData = <?php echo wp_json_encode( $initial_data ); ?>;
</script>

<!-- Injecting modern full-screen CSS -->
<style>
	#wpadminbar, #adminmenuwrap, #adminmenuback, #wpfooter { display: none !important; }
	#wpcontent, #wpfooter { margin-left: 0 !important; padding-left: 0 !important; }
	html.wp-toolbar { padding-top: 0 !important; }
	
	.wp-formy-builder-wrap {
		position: fixed;
		top: 0; left: 0; right: 0; bottom: 0;
		background: #f0f0f1;
		z-index: 999999;
		display: flex;
		flex-direction: column;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	}

	/* Top Toolbar */
	.wpf-toolbar {
		height: 60px;
		background: #ffffff;
		border-bottom: 1px solid #e2e4e7;
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 20px;
		box-shadow: 0 1px 3px rgba(0,0,0,0.05);
	}
	.wpf-toolbar-left, .wpf-toolbar-right { display: flex; align-items: center; gap: 10px; }
	.wpf-toolbar-center { flex: 1; text-align: center; }
	
	.wpf-close-btn { text-decoration: none; color: #50575e; font-size: 20px; line-height: 1; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 4px; background: #f6f7f7; }
	.wpf-close-btn:hover { background: #e2e4e7; color: #1d2327; }
	.wpf-form-title-input { font-size: 16px; font-weight: 600; text-align: center; border: 1px solid transparent; background: transparent; padding: 6px 12px; border-radius: 4px; transition: 0.2s; width: 300px; }
	.wpf-form-title-input:hover, .wpf-form-title-input:focus { border-color: #2271b1; background: #fff; outline: none; }

	.wpf-btn { padding: 6px 14px; font-size: 13px; font-weight: 500; border-radius: 4px; cursor: pointer; text-decoration: none; }
	.wpf-btn-secondary { background: #f6f7f7; border: 1px solid #dcdde1; color: #2271b1; }
	.wpf-btn-secondary:hover { background: #f0f0f1; border-color: #c3c4c7; }
	.wpf-btn-primary { background: #2271b1; border: 1px solid #2271b1; color: #fff; }
	.wpf-btn-primary:hover { background: #135e96; border-color: #135e96; }

	/* Main Body Layout */
	.wpf-body { display: flex; flex: 1; overflow: hidden; }

	/* Left Sidebar - Fields Library */
	.wpf-sidebar-left {
		width: 280px;
		background: #ffffff;
		border-right: 1px solid #e2e4e7;
		overflow-y: auto;
		display: flex;
		flex-direction: column;
	}
	.wpf-sidebar-header { padding: 15px 20px; border-bottom: 1px solid #f0f0f1; font-weight: 600; font-size: 14px; }
	.wpf-fields-search { padding: 15px 20px; border-bottom: 1px solid #f0f0f1; }
	.wpf-fields-search input { width: 100%; border-radius: 4px; border: 1px solid #dcdde1; padding: 6px 10px; font-size: 13px; }
	.wpf-fields-grid { display: flex; flex-wrap: wrap; padding: 15px; gap: 10px; }
	.wpf-field-btn { 
		width: calc(50% - 5px); background: #f6f7f7; border: 1px solid #dcdde1; border-radius: 4px; 
		padding: 12px 10px; text-align: center; font-size: 12px; color: #50575e; cursor: grab; transition: 0.2s;
	}
	.wpf-field-btn:hover { background: #fff; border-color: #2271b1; color: #2271b1; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

	/* Center Canvas */
	.wpf-canvas-area {
		flex: 1;
		overflow-y: auto;
		padding: 40px;
		display: flex;
		justify-content: center;
		align-items: flex-start;
	}
	.wpf-canvas {
		width: 100%;
		max-width: 750px;
		background: #ffffff;
		border-radius: 6px;
		box-shadow: 0 2px 10px rgba(0,0,0,0.05);
		padding: 40px;
		min-height: 300px;
	}
	.wpf-empty-state {
		border: 2px dashed #c3c4c7;
		border-radius: 6px;
		padding: 60px 20px;
		text-align: center;
		color: #50575e;
		background: #f6f7f7;
	}
	.wpf-canvas-area.drag-over .wpf-canvas { border: 2px dashed #2271b1; background: #f0f6fc; }
	.wpf-canvas-field { padding: 15px; border: 1px solid transparent; border-radius: 4px; position: relative; margin-bottom: 10px; cursor: pointer; transition: 0.2s; background: #fff; }
	.wpf-canvas-field:hover { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
	.wpf-canvas-field.active { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; background: #f0f6fc; }
	.wpf-field-actions { position: absolute; top: -12px; right: 10px; display: none; background: #fff; border: 1px solid #2271b1; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden; }
	.wpf-canvas-field:hover .wpf-field-actions, .wpf-canvas-field.active .wpf-field-actions { display: flex; }
	.wpf-action-btn { background: none; border: none; padding: 4px 8px; cursor: pointer; font-size: 11px; color: #50575e; border-right: 1px solid #e2e4e7; }
	.wpf-action-btn:last-child { border-right: none; }
	.wpf-action-btn:hover { background: #f6f7f7; color: #1d2327; }
	.wpf-action-btn.delete { color: #d63638; }
	.wpf-action-btn.delete:hover { background: #fcf0f1; }

	/* Right Sidebar - Settings */
	.wpf-sidebar-right {
		width: 320px;
		background: #ffffff;
		border-left: 1px solid #e2e4e7;
		overflow-y: auto;
	}
	.wpf-settings-tabs { display: flex; border-bottom: 1px solid #e2e4e7; }
	.wpf-tab { flex: 1; text-align: center; padding: 15px 0; font-size: 13px; font-weight: 500; color: #50575e; cursor: pointer; border-bottom: 2px solid transparent; }
	.wpf-tab.active { color: #2271b1; border-bottom-color: #2271b1; }
	.wpf-settings-panels { position: relative; }
	.wpf-settings-panel { display: none; padding: 20px; }
	.wpf-settings-panel.active { display: block; }
	.wpf-setting-row { margin-bottom: 20px; }
	.wpf-setting-row label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px; }
	.wpf-setting-row input[type="text"], .wpf-setting-row textarea { width: 100%; border: 1px solid #dcdde1; border-radius: 4px; padding: 8px; font-size: 13px; }
</style>

<div class="wp-formy-builder-wrap">
	
	<!-- Top Toolbar -->
	<div class="wpf-toolbar">
		<div class="wpf-toolbar-left">
			<a href="<?php echo esc_url( $back_url ); ?>" class="wpf-close-btn" title="Back to Forms">✕</a>
			<button class="wpf-btn wpf-btn-secondary">⚙ Settings</button>
		</div>
		<div class="wpf-toolbar-center">
			<input type="text" id="wpf-form-title" class="wpf-form-title-input" value="<?php echo esc_attr( $initial_data['title'] ?: 'Untitled Form' ); ?>" placeholder="Enter Form Title...">
		</div>
		<div class="wpf-toolbar-right">
			<button class="wpf-btn wpf-btn-secondary">Save Draft</button>
			<button id="wpf-save-form-btn" class="wpf-btn wpf-btn-primary">Publish Form</button>
		</div>
	</div>

	<!-- Main Body -->
	<div class="wpf-body">
		
		<!-- Left Sidebar (Fields Library) -->
		<div class="wpf-sidebar-left">
			<div class="wpf-sidebar-header">Add Fields</div>
			<div class="wpf-fields-search">
				<input type="text" placeholder="Search fields...">
			</div>
			<div class="wpf-fields-grid">
				<?php
				$fields = array( 'Text', 'Email', 'URL', 'Textarea', 'Dropdown', 'Checkboxes', 'Multiple Choice', 'Number', 'Phone', 'Address', 'Date', 'Separator' );
				foreach ( $fields as $field ) {
					$type = sanitize_key( str_replace( ' ', '_', strtolower( $field ) ) );
					echo '<div class="wpf-field-btn" draggable="true" data-type="' . esc_attr( $type ) . '" data-label="' . esc_attr( $field ) . '">❖ ' . esc_html( $field ) . '</div>';
				}
				?>
			</div>
		</div>

		<!-- Center Canvas -->
		<div class="wpf-canvas-area" id="wpf-dropzone">
			<div class="wpf-canvas" id="wpf-canvas-fields">
				<div class="wpf-empty-state">
					<h3>No fields here yet.</h3>
					<p>Drag and drop fields from the left sidebar to start building your form.</p>
				</div>
			</div>
		</div>

		<!-- Right Sidebar (Inspector) -->
		<div class="wpf-sidebar-right">
			<div class="wpf-settings-tabs">
				<div class="wpf-tab active" data-target="form">Form Settings</div>
				<div class="wpf-tab" data-target="field">Field Settings</div>
			</div>
			<div class="wpf-settings-panels">
				<!-- Form Settings Panel -->
				<div id="wpf-panel-form" class="wpf-settings-panel active">
					<div class="wpf-setting-row">
						<label>Submit Button Text</label>
						<input type="text" id="wpf-form-submit-text" value="Submit">
					</div>
					<div class="wpf-setting-row">
						<label><input type="checkbox" id="wpf-form-notifications" checked> Enable Admin Notifications</label>
					</div>
				</div>
				<!-- Field Settings Panel -->
				<div id="wpf-panel-field" class="wpf-settings-panel">
					<p style="color: #646970; font-size: 13px;">Select a field in the canvas to edit its settings.</p>
				</div>
			</div>
		</div>

	</div>
</div>