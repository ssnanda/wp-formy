<?php
/**
 * View: Forms Listing Screen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$forms_list_table = new WP_Formy_Forms_List_Table();
$forms_list_table->prepare_items();

$add_new_url = add_query_arg( array( 'page' => 'wp-formy', 'action' => 'add' ), admin_url( 'admin.php' ) );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'wp-formy' ); ?></h1>
	<a href="<?php echo esc_url( $add_new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Form', 'wp-formy' ); ?></a>
	<a href="#" id="wpf-import-form-btn" class="page-title-action"><?php esc_html_e( 'Import Form', 'wp-formy' ); ?></a>
	<input type="file" id="wpf-import-file" accept=".json" style="display:none;" />
	<hr class="wp-header-end">

	<form id="forms-filter" method="get">
		<input type="hidden" name="page" value="wp-formy" />
		<?php 
		$forms_list_table->search_box( __( 'Search Forms', 'wp-formy' ), 'search_id' );
		$forms_list_table->display(); 
		?>
	</form>
</div>

<script>
(function() {
	const importBtn = document.getElementById('wpf-import-form-btn');
	const fileInput = document.getElementById('wpf-import-file');
	const nonce = '<?php echo wp_create_nonce( 'wpf_import_form' ); ?>';

	function showAlert(message) {
		window.alert(message);
	}

	function importFormFromJson(json) {
		const payload = {
			action: 'wpf_import_form',
			nonce: nonce,
			data: json,
		};

		const formData = new FormData();
		Object.keys(payload).forEach(key => formData.append(key, payload[key]));

		fetch(ajaxurl, {
			method: 'POST',
			body: formData,
		})
			.then(res => res.json())
			.then(res => {
				if (!res.success) {
					showAlert(res.data || '<?php echo esc_js( esc_html__( 'Import failed.', 'wp-formy' ) ); ?>');
					return;
				}
				if (res.data && res.data.edit_url) {
					window.location.href = res.data.edit_url;
				} else {
					showAlert('<?php echo esc_js( esc_html__( 'Form imported successfully.', 'wp-formy' ) ); ?>');
				}
			})
			.catch(() => {
				showAlert('<?php echo esc_js( esc_html__( 'Import failed.', 'wp-formy' ) ); ?>');
			});
	}

	if (importBtn && fileInput) {
		importBtn.addEventListener('click', function(e) {
			e.preventDefault();
			fileInput.click();
		});

		fileInput.addEventListener('change', function() {
			const file = this.files[0];
			if (!file) {
				return;
			}

			const reader = new FileReader();
			reader.onload = function(event) {
				try {
					const json = JSON.parse(event.target.result);
					importFormFromJson(json);
				} catch (err) {
					showAlert('<?php echo esc_js( esc_html__( 'Invalid JSON file.', 'wp-formy' ) ); ?>');
				}
			};
			reader.readAsText(file);
		});
	}
})();
</script>