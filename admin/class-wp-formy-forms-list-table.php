<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WP_Formy_Forms_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'form',
			'plural'   => 'forms',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'        => '<input type="checkbox" />',
			'title'     => __( 'Title', 'wp-formy' ),
			'shortcode' => __( 'Shortcode', 'wp-formy' ),
			'entries'   => __( 'Entries Count', 'wp-formy' ),
			'date'      => __( 'Date & Time', 'wp-formy' ),
			'status'    => __( 'Status', 'wp-formy' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
			'date'  => array( 'created_at', false ),
		);
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'shortcode':
				return '<code>[wp_formy id="' . absint( $item['id'] ) . '"]</code>';
			case 'entries':
				// Placeholder for actual leads count query.
				return '0';
			case 'date':
				return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] ) ) );
			case 'status':
				$status = ucfirst( esc_html( $item['status'] ) );
				$color  = $item['status'] === 'published' ? 'green' : 'gray';
				return "<span style='color: {$color}; font-weight: bold;'>{$status}</span>";
			default:
				return print_r( $item, true );
		}
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="form_id[]" value="%s" />',
			absint( $item['id'] )
		);
	}

	protected function column_title( $item ) {
		$edit_url   = add_query_arg( array( 'page' => 'wp-formy', 'action' => 'edit', 'form_id' => $item['id'] ), admin_url( 'admin.php' ) );
		$delete_url = wp_nonce_url( add_query_arg( array( 'page' => 'wp-formy', 'action' => 'delete', 'form_id' => $item['id'] ), admin_url( 'admin.php' ) ), 'delete_form_' . $item['id'] );
		
		$actions = array(
			'edit'      => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), __( 'Edit', 'wp-formy' ) ),
			'preview'   => sprintf( '<a href="#">%s</a>', __( 'Preview', 'wp-formy' ) ),
			'duplicate' => sprintf( '<a href="#">%s</a>', __( 'Duplicate', 'wp-formy' ) ),
			'export'    => sprintf( '<a href="#">%s</a>', __( 'Export', 'wp-formy' ) ),
			'delete'    => sprintf( '<a href="%s" style="color: #a00;">%s</a>', esc_url( $delete_url ), __( 'Delete', 'wp-formy' ) ),
		);

		return sprintf(
			'<strong><a class="row-title" href="%1$s">%2$s</a></strong>%3$s',
			esc_url( $edit_url ),
			esc_html( $item['title'] ),
			$this->row_actions( $actions )
		);
	}

	public function get_bulk_actions() {
		return array(
			'bulk-delete' => __( 'Delete', 'wp-formy' ),
		);
	}

	public function prepare_items() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'formy_forms';

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Verify table exists before querying to avoid errors on fresh setups
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			$this->items = array();
			return;
		}

		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$order   = isset( $_GET['order'] ) && strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) === 'asc' ? 'ASC' : 'DESC';

		$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$table_name}" );

		$this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}
}