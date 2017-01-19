<?php
/**
 * Defines the class that will be used to set up the REST API usage for
 * 		Types post type relationships
 */
abstract class Types_Relationship_API {
	/**
	 * @var string required - the slug identifying the parent post type
	 */
	public $parent_type  = null;
	/**
	 * @var string required - the slug identifying the child post type
	 */
	public $child_type   = null;
	/**
	 * @var string optional - the slug identifying the interim (child) post type
	 * Required if you are trying to query a many-to-many post type relationship
	 * 		In that case, this would be the actual "child" post type, while the 
	 * 		parent_type and child_type properties would be the two parents of that child
	 */
	public $interim_type = null;
	public $meta_data    = null;
	/**
	 * @var string - the base route path; should not be altered
	 */
	public $namespace    = 'types/relationships';
	/**
	 * @var string - the version information to include in the route path; should not be altered
	 */
	public $version      = 'v1';
	/**
	 * @var string - the base route, which will be constructed based on the parent_type & child_type properties
	 */
	protected $route        = null;
	
	/**
	 * Begin constructing our object
	 * @uses Types_Relationship_API::set_route() to construct the base route string
	 */
	function __construct() {
		if ( ! class_exists( 'Types_Relationship_REST_Post_Controller' ) )
			require_once( plugin_dir_path( __FILE__ ) . '/class-types-relationship-rest-posts-controller.php' );
		
		$this->set_route();
		
		add_filter( 'types-relationship-api-post-data', array( $this, 'add_meta_data' ), 10, 2 );
		add_filter( 'types-relationship-api-post-taxonomies', array( $this, 'add_taxonomies' ), 10, 2 );
	}
	
	/**
	 * Set the route path for the API requests
	 */
	function set_route() {
		$this->route = "{$this->parent_type}/{$this->child_type}";
	}
	
	/**
	 * Retrieve the route path for API requests
	 */
	function get_route() {
		return $this->route;
	}
	
	/**
	 * Register any custom API routes that need to be registered
	 */
	function register_routes() {
		$root = $this->namespace;
		$version = $this->version;
		$cb_class = new \Types_Relationship_REST_Posts_Controller( $this->parent_type, $this->child_type, $this->interim_type );
		
		$rest_args = apply_filters( 'types-relationship-api-valid-rest-arguments', array(
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
			'parent_id' => array(
				'default' => 0, 
				'sanitize_callback' => 'absint', 
			), 
			'slug' => array(
				'default' => false,
				/* sanitize_title strips slashes, so that's no good */
				/*'sanitize_callback' => 'sanitize_title',*/
				'sanitize_callback' => array( $this, 'valid_slug' ), 
			)
		) );
		
		/**
		 * Set up an endpoint to retrieve all posts that share a many-to-many relationship with the specified parent post
		 * @param int $parent_id the ID of the department
		 * @param string $slug the slug of the department (if you can't provide the ID)
		 */
		register_rest_route( "{$root}/{$version}", $this->get_route() . '/(?P<id>[\d]+)', array(
			array(
				'methods'         => \WP_REST_Server::READABLE,
				'callback'        => array( $cb_class, 'get_items' ),
				'args'            => $rest_args, 
	
				'permission_callback' => array( $this, 'permissions_check' )
			),
		) );
		register_rest_route( "{$root}/{$version}", $this->get_route(), array(
			array(
				'methods'         => \WP_REST_Server::READABLE,
				'callback'        => array( $cb_class, 'get_items' ),
				'args'            => $rest_args, 
	
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
	 * Attempt to verify whether something is a valid slug for use in get_page_by_path()
	 */
	function valid_slug( $slug ) {
		if ( ! stristr( $slug, '/' ) )
			return sanitize_title( $slug );
		
		$parts = explode( '/', $slug );
		foreach ( $parts as $k=>$v ) {
			$parts[$k] = sanitize_title( $v );
		}
		
		return implode( '/', $parts );
	}
	
	/**
	 * Return the full REST route for the route that was registered
	 */
	function get_rest_url() {
		return "/wp-json/{$this->namespace}/{$this->version}/{$this->parent_type}/{$this->child_type}";
	}
	
	/**
	 * Require a function that adds appropriate meta data to the retrieved posts
	 */
	abstract protected function add_meta_data( $data, $post );
	
	/**
	 * Require a function that adds any desired taxonomies to the data returned for posts
	 */
	abstract protected function add_taxonomies( $taxes, $post );
}