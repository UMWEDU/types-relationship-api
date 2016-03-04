<?php
/**
 * Defines the class that will be used to set up the REST API usage for
 * 		Types post type relationships
 */
abstract class Types_Relationship_API {
	public $parent_type  = null;
	public $child_type   = null;
	public $interim_type = null;
	public $meta_data    = null;
	public $namespace    = 'types/relationships';
	public $version      = 'v1';
	
	/**
	 * Begin constructing our object
	 */
	function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}
	
	/**
	 * Register any custom API routes that need to be registered
	 */
	function register_routes() {
	}
}