<?php

/*
	Plugin Name: Partnerize Partner
	Plugin URI: #
	Description: Load your Partnerize partner credentials for access to your performance data. You will be able to quickly add your tracking links to any new post you create.
	Version: 1.0.0
	Author: Partnerize
	Author URI: http://www.partnerize.com/
	License: GPLv2 or later
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'PARTNERIZE_PARTNER_VERSION', '1.0.0' );

require_once( plugin_dir_path( __FILE__ ) . "functions.php" );
require_once( plugin_dir_path( __FILE__ ) . "class/partnerize_tracking_list.php" );

/**
 * The hook into activation for setup
 */
function partnerize_partner_install()
{
	if ( ! current_user_can( 'activate_plugins' ) )
	{
		return;
	}

	global $wpdb;
	global $charset_collate;

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$str_table_name = $wpdb->prefix . 'partnerize_partner_participation';
	if ($wpdb->get_var("SHOW TABLES LIKE '{$str_table_name}'") !== $str_table_name)
	{
		$sql = "CREATE TABLE IF NOT EXISTS `{$str_table_name}` (
			ID int(11) AUTO_INCREMENT NOT NULL,
			auth_id int(11) NOT NULL,
			campaign_id varchar(100) NOT NULL,
			campaign_title text NOT NULL,
			camref varchar(100) NOT NULL UNIQUE,
			tracking_link text NOT NULL,
			is_cpc enum('y','n') NOT NULL DEFAULT 'n',
			PRIMARY KEY (ID)
		) $charset_collate;";
		dbDelta( $sql );
	}

	$str_table_name = $wpdb->prefix . 'partnerize_partner_auth';
	if ($wpdb->get_var("SHOW TABLES LIKE '{$str_table_name}'") !== $str_table_name)
	{
		$sql = "CREATE TABLE IF NOT EXISTS `{$str_table_name}` (
			ID int(11) AUTO_INCREMENT NOT NULL,
			wp_user_id int(11) NOT NULL UNIQUE,
			application_api_key varchar(100) NOT NULL,
			user_api_key varchar(100) NOT NULL,
			publisher_id varchar(100) NOT NULL,
			PRIMARY KEY (ID)
		) $charset_collate;";
		dbDelta( $sql );
	}

	if ( get_option( '_partnerize_partner_auth_table' ) === FALSE)
	{
		add_option( '_partnerize_partner_auth_table', 'partnerize_partner_auth' );
	}
	if ( get_option( '_partnerize_partner_participation_table' ) === FALSE)
	{
		add_option( '_partnerize_partner_participation_table', 'partnerize_partner_participation' );
	}
	if ( get_option( '_partnerize_partner_version' ) === FALSE)
	{
		add_option( '_partnerize_partner_version', PARTNERIZE_PARTNER_VERSION );
	}
}
register_activation_hook( __FILE__, 'partnerize_partner_install' );


/**
 * The hook into deactivation
 */
function partnerize_partner_uninstall()
{
	if ( ! current_user_can( 'update_plugins' ))
	{
		return;
	}

	global $wpdb;

	$sql = "DROP TABLE IF EXISTS " . $wpdb->prefix . get_option( '_partnerize_partner_auth_table' );
	$wpdb->query( $sql );

	$sql = "DROP TABLE IF EXISTS " . $wpdb->prefix . get_option( '_partnerize_partner_participation_table' );
	$wpdb->query( $sql );

	delete_option( '_partnerize_partner_auth_table' );
	delete_option( '_partnerize_partner_participation_table' );
	delete_option( '_partnerize_partner_version' );
}
register_deactivation_hook( __FILE__, 'partnerize_partner_uninstall' );


/**
 * Add the menu option to the Admin section
 */
function partnerize_partner_admin()
{
	if (has_partnerize_partner_links( get_current_user_id() ) && is_partnerize_partner_access_verified())
	{
		add_action( "add_meta_boxes", "partnerize_partner_register_meta_boxes" );
		add_filter( "mce_external_plugins", "partnerize_partner_enqueue_plugin_scripts" );
		add_filter( "mce_buttons", "partnerize_partner_register_buttons_editor" );
	}

	add_menu_page(
		"Partnerize",
		"Partnerize",
		"manage_options",
		"partnerize_partner_settings",
		"partnerize_partner_settings_page",
		plugin_dir_url( __FILE__ ) . 'assets/16x16-partnerize_icon.png'
	);
}
add_action("admin_menu", "partnerize_partner_admin");

function partnerize_partner_add_action_links( $links )
{
	$mylinks = array(
		'<a href="' . admin_url( 'admin.php?page=partnerize_partner_settings' ) . '">Settings</a>',
		);

	return array_merge( $links, $mylinks );
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'partnerize_partner_add_action_links' );


/**
 * Show the settings page in admin for credentials
 */
function partnerize_partner_settings_page()
{
	global $wpdb;

	$str_auth_table = $wpdb->prefix . get_option( '_partnerize_partner_auth_table' );
	$user_id = get_current_user_id();

	$auth_id = partnerize_partner_get_auth_value( 'ID', $user_id );

	if (is_null( $auth_id ))
	{
		$sql = "INSERT INTO `{$str_auth_table}` (wp_user_id, application_api_key, user_api_key, publisher_id) VALUES ({$user_id}, '', '', '')";
		$wpdb->query( $sql );
	}

	if (isset( $_POST['submit'] ))
	{
		partnerize_partner_update_publisher_auth( $user_id );
	}

	$application_api_key = partnerize_partner_get_auth_value( 'application_api_key', $user_id );
	$user_api_key = partnerize_partner_get_auth_value( 'user_api_key', $user_id );
	$publisher_id = partnerize_partner_get_auth_value( 'publisher_id', $user_id );

	?>
	<div class="wrap">
		<h1>Enter Partnerize API Credentials</h1>
		<form action="" method="post">
			<?php wp_nonce_field( 'partnerize_partner_api_box_nonce', 'partnerize_partner_api_box_nonce' ); ?>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="application_api_key">Application API Key</label></th>
						<td><input type="text" name="application_api_key" placeholder="<?php echo $application_api_key; ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="user_api_key">User API Key</label></th>
						<td><input type="text" name="user_api_key" placeholder="<?php echo $user_api_key; ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="publisher_id">Publisher ID</label></th>
						<td><input type="text" name="publisher_id" placeholder="<?php echo $publisher_id; ?>" /></td>
					</tr>
				</tbody>
			</table>
			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button button-primary" value="Update API Credentials">
				<input type="submit" name="refresh" id="refresh" class="button" value="Refresh Approved Campaigns">
			</p>
		</form>
	<?php

	if (isset( $_POST['refresh'] ))
	{
		partnerize_partner_refresh_publisher_campaigns();
	}

	if ( ! is_null( $auth_id ))
	{
		partnerize_partner_show_participation_details();
	}

	?>

	</div>

	<?php
}

?>