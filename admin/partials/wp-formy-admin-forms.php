<?php
/**
 * View: Forms Listing Screen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$forms_list_table = new WP_Formy_Forms_List_Table();
$forms_list_table->process_bulk_action();
$forms_list_table->prepare_items();

$add_new_url = add_query_arg(
	array(
		'page'   => 'wp-formy',
		'action' => 'add',
	),
	admin_url( 'admin.php' )
);
?>

<div class="wrap">
	<style>
		#forms-filter .wp-list-table {
			table-layout: fixed;
		}

		#forms-filter .column-cb {
			width: 36px;
		}

		#forms-filter .column-title {
			width: 19%;
		}

		#forms-filter .column-shortcode {
			width: 16%;
		}

		#forms-filter .column-entries {
			width: 8%;
			white-space: nowrap;
		}

		#forms-filter .column-date {
			width: 14%;
		}

		#forms-filter .column-status {
			width: 10%;
		}

		#forms-filter .column-actions {
			width: 33%;
		}

		#forms-filter .column-actions .wp-formy-inline-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 6px;
			justify-content: flex-end;
		}

		#forms-filter .column-actions .button {
			margin: 0;
		}

		#forms-filter .column-shortcode code {
			display: inline-block;
			max-width: 100%;
			overflow-wrap: anywhere;
			white-space: normal;
		}

		@media (max-width: 1280px) {
			#forms-filter .column-actions {
				width: 36%;
			}

			#forms-filter .column-title {
				width: 16%;
			}
		}
	</style>

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'wp-formy' ); ?></h1>
	<a href="<?php echo esc_url( $add_new_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Form', 'wp-formy' ); ?></a>
	<a href="#" id="wpf-import-form-btn" class="page-title-action"><?php esc_html_e( 'Import Form', 'wp-formy' ); ?></a>
	<a href="#" id="wpf-export-form-btn" class="page-title-action"><?php esc_html_e( 'Export Form', 'wp-formy' ); ?></a>
	<input type="file" id="wpf-import-file" accept=".json" style="display:none;" />
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['trashed'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form moved to deleted.', 'wp-formy' ); ?></p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['restored'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form restored.', 'wp-formy' ); ?></p></div>
	<?php endif; ?>

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
	const exportBtn = document.getElementById('wpf-export-form-btn');
	const fileInput = document.getElementById('wpf-import-file');
	const formRows = document.querySelectorAll('.wp-formy-form-row');
	const statusFilters = document.querySelectorAll('.wp-formy-status-filter');
	const nonce = '<?php echo esc_js( wp_create_nonce( 'wpf_import_form' ) ); ?>';
	const exportNonce = '<?php echo esc_js( wp_create_nonce( 'wpf_export_form' ) ); ?>';
	const exportBaseUrl = '<?php echo esc_js( admin_url( 'admin-post.php?action=wpf_export_form' ) ); ?>';

	function showAlert(message) {
		window.alert(message);
	}

	function importFormFromJson(json) {
		const payload = {
			action: 'wpf_import_form',
			nonce: nonce,
			data: JSON.stringify(json)
		};

		const formData = new FormData();
		Object.keys(payload).forEach((key) => formData.append(key, payload[key]));

		fetch(ajaxurl, {
			method: 'POST',
			body: formData
		})
			.then((res) => res.json())
			.then((res) => {
				if (!res.success) {
					showAlert(res.data || '<?php echo esc_js( __( 'Import failed.', 'wp-formy' ) ); ?>');
					return;
				}

				if (res.data && res.data.edit_url) {
					window.location.href = res.data.edit_url;
					return;
				}

				showAlert('<?php echo esc_js( __( 'Form imported successfully.', 'wp-formy' ) ); ?>');
			})
			.catch(() => {
				showAlert('<?php echo esc_js( __( 'Import failed.', 'wp-formy' ) ); ?>');
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
					showAlert('<?php echo esc_js( __( 'Invalid JSON file.', 'wp-formy' ) ); ?>');
				}
			};
			reader.readAsText(file);
		});
	}

	if (exportBtn) {
		exportBtn.addEventListener('click', function(e) {
			e.preventDefault();

			const checked = Array.from(document.querySelectorAll('input[name="form_id[]"]:checked'));
			if (checked.length !== 1) {
				showAlert('<?php echo esc_js( __( 'Select exactly one form to export.', 'wp-formy' ) ); ?>');
				return;
			}

			const formId = checked[0].value;
			window.location.href = exportBaseUrl + '&form_id=' + encodeURIComponent(formId) + '&_wpnonce=' + encodeURIComponent(exportNonce);
		});
	}

	if (statusFilters.length) {
		statusFilters.forEach(function(filter) {
			filter.addEventListener('change', function() {
				const url = new URL(window.location.href);
				url.searchParams.set('page', 'wp-formy');

				if (this.value) {
					url.searchParams.set('form_status', this.value);
				} else {
					url.searchParams.delete('form_status');
				}

				url.searchParams.delete('paged');
				window.location.href = url.toString();
			});
		});
	}

	if (formRows.length) {
		formRows.forEach(function(row) {
			if (row.classList.contains('is-deleted')) {
				return;
			}

			row.style.cursor = 'pointer';

			row.addEventListener('click', function(e) {
				if (
					e.target.closest('a, button, input, select, textarea, label') ||
					e.target.tagName === 'CODE'
				) {
					return;
				}

				const editUrl = row.getAttribute('data-edit-url');
				if (editUrl) {
					window.location.href = editUrl;
				}
			});
		});
	}

})();
</script>
