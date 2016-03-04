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
	public $route        = null;
	
	/**
	 * Begin constructing our object
	 */
	function __construct() {
		if ( ! class_exists( 'Types_Relationship_REST_Post_Controller' ) )
			require_once( plugin_dir_path( __FILE__ ) . '/class-types-relationship-rest-posts-controller.php' );
	}
	
	/**
	 * Register any custom API routes that need to be registered
	 */
	function register_routes() {
		$root = $this->namespace;
		$version = $this->version;
		$cb_class = new \Types_Relationship_REST_Posts_Controller( $this->parent_type, $this->child_type, $this->interim_type );
		
		/**
		 * Set up an endpoint to retrieve all posts that share a many-to-many relationship with the specified parent post
		 * @param int $parent_id the ID of the department
		 * @param string $slug the slug of the department (if you can't provide the ID)
		 */
		register_rest_route( "{$root}/{$version}", "/{$this->parent_type}/{$this->child_type}", array(
			array(
				'methods'         => \WP_REST_Server::READABLE,
				'callback'        => array( $cb_class, 'get_items' ),
				'args'            => array(
					'per_page' => array(
						'default' => 10,
						'sanitize_callback' => 'absint',
					),
					'orderby' => array(
						'default' => 'title', 
						'sanitize_callback' => array( $this, 'valid_orderby' ), 
					), 
					'page' => array(
						'default' => 1,
						'sanitize_callback' => 'absint',
					),
					'parent' => array(
						'default' => $this->parent_type,
						'sanitize_callback' => array( $this, 'valid_post_types' ),
					),
					'child' => array( 
						'default' => $this->child_type, 
						'sanitize_callback' => array( $this, 'valid_post_types' ), 
					), 
					'interim' => array(
						'default' => $this->interim_type, 
						'sanitize_callback' => array( $this, 'valid_post_types' ), 
					), 
					'parent_id' => array(
						'default' => 0, 
						'sanitize_callback' => 'absint', 
					), 
					'slug' => array(
						'default' => false,
						'sanitize_callback' => 'sanitize_title',
					)
				),
	
				'permission_callback' => array( $this, 'permissions_check' )
			),
		) );
	}
	
	/**
	 * Placeholder function to eventually perform some sort of permissions check
	 */
	function permissions_check() {
		return true;
	}
	
	/**
	 * This is a placeholder function until I figure out a way 
	 * 		to validate that a post type is actually registered/available
	 */
	function valid_post_types( $type ) {
		return $type;
	}
	
	/**
	 * Verify that the orderby parameter is a valid key by which to order posts
	 */
	function valid_orderby( $key ) {
		$keys = array( 
			'none', 
			'ID', 
			'author', 
			'title', 
			'name', 
			'type', 
			'date', 
			'modified', 
			'parent', 
			'rand', 
			'comment_count', 
			'menu_order', 
			'post__in'
		);
		
		if ( in_array( $key, $keys ) )
			return $key;
		
		return 'date';
	}
	
	/**
	 * Return the full REST route for the route that was registered
	 */
	function get_rest_url() {
		return "/wp-json/{$this->namespace}/{$this->version}/{$this->parent_type}/{$this->child_type}";
	}
}