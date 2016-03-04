<?php
/*
Plugin Name: Types Relationship API
Description: Makes it possible to set up custom REST API endpoints/queries based on Types post relationships
Version:     0.1
Author:      Curtiss Grymala
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! class_exists( 'Types_Relationship_API' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . '/classes/class-types-relationship-api.php' );
}
add_action( 'plugins_loaded', 'inst_types_relationship_api_obj' );
function inst_types_relationship_api_obj() {
	if ( ! function_exists( 'wpcf_bootstrap' ) )
		return;
	
	global $types_relationship_api_obj;
	$types_relationship_api_obj = new Types_Relationship_API;
}