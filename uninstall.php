<?php
/**
 * Uninstall routine: removes plugin data when "Delete data on uninstall" is enabled.
 *
 * @package Zenvy_Free_Shipping_Progress_Bar
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete the settings option for the current site when the user opted in.
 *
 * @return void
 */
function zenvy_fspb_uninstall_current_site() {
	$settings = get_option( 'zenvy_fspb_settings' );

	if ( is_array( $settings ) && isset( $settings['delete_data'] ) && 'yes' === $settings['delete_data'] ) {
		delete_option( 'zenvy_fspb_settings' );
	}
}

if ( is_multisite() ) {
	$zenvy_fspb_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $zenvy_fspb_site_ids as $zenvy_fspb_site_id ) {
		switch_to_blog( $zenvy_fspb_site_id );
		zenvy_fspb_uninstall_current_site();
		restore_current_blog();
	}
} else {
	zenvy_fspb_uninstall_current_site();
}
