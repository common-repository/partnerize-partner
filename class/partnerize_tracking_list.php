<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Partnerize_Tracking_List extends WP_List_Table
{

	/**
	 * Wordpress created an extra nonce
	 * Simply override the parent class function
	 */
	protected function display_tablenav( $which ) {
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<div class="alignleft actions bulkactions">
				<?php $this->bulk_actions( $which ); ?>
			</div>
			<?php
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>
			<br class="clear"/>
		</div>
		<?php
	}

	/**
	 * Friendly column names
	 * @return Array the key => value pair to columns
	 */
	public function get_columns()
	{
		$arr_columns = array(
			'campaign_id'		=> 'Campaign ID',
			'campaign_title'	=> 'Campaign Title',
			'camref'			=> 'Camref',
			'tracking_link'		=> 'Tracking Link',
			'is_cpc'			=> 'CPC Allowed',
			);

		return $arr_columns;
	}

	public function prepare_items()
	{
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		$arr_data = $this->get_participation_details();

		$this->_column_headers = array($columns, $hidden, $sortable);
		usort( $arr_data, array( &$this, 'usort_reorder' ) );
		$this->items = $arr_data;
	}

	protected function column_default( $item, $column_name )
	{
		switch( $column_name ) {
			case 'campaign_id':
			case 'campaign_title':
			case 'camref':
			case 'tracking_link':
				return $item->$column_name;
			break;
			case 'is_cpc':
				if ($item->$column_name === "n")
				{
					return "No";
				}
				return "Yes";
			break;
			default:
				return print_r( $item, TRUE );
			break;
		}
	}

	protected function get_sortable_columns()
	{
		$sortable_columns = array(
			'campaign_id'		=> array( 'campaign_id', false ),
			'campaign_title'	=> array( 'campaign_title', false ),
			'is_cpc'			=> array( 'is_cpc', false )
			);

		return $sortable_columns;
	}

	private function get_participation_details()
	{
		global $wpdb;

		$str_table = $wpdb->prefix . get_option( '_partnerize_partner_auth_table' );
		$auth_id = $wpdb->get_var( "
			SELECT `ID`
			FROM `{$str_table}`
			WHERE `wp_user_id` = " . get_current_user_id()
		);

		$str_table = $wpdb->prefix . get_option( '_partnerize_partner_participation_table' );
		$sql = "
			SELECT campaign_id, campaign_title, camref, tracking_link, is_cpc
			FROM `{$str_table}`
			WHERE `auth_id` = {$auth_id}
			";

		return $wpdb->get_results( $sql );
	}

	private function usort_reorder( $a, $b )
	{
		// If no sort, default to title
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'campaign_title';
		// If no order, default to asc
		$order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
		// Determine sort order
		$result = strcmp( $a->$orderby, $b->$orderby );

		// Send final sort direction to usort
		return ( $order === 'asc' ) ? $result : -$result;
	}

}