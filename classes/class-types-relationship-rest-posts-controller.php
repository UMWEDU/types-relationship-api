<?php
if ( ! class_exists( 'Types_Relationship_REST_Posts_Controller' ) ) {
	class Types_Relationship_REST_Posts_Controller extends \WP_REST_Posts_Controller {
		public $parent_type  = null;
		public $child_type   = null;
		public $interim_type = null;
		public $interim_posts = array();
		
		public function __construct( $parent_type=null, $child_type=null, $interim_type=null ) {
			$this->set_parent_type( $parent_type );
			$this->set_child_type( $child_type );
			$this->set_interim_type( $interim_type );
		}
		
		public function set_parent_type( $type ) {
			$this->parent_type = $type;
		}
		
		public function get_parent_type() {
			return $this->parent_type;
		}
		
		public function set_child_type( $type ) {
			$this->child_type = $type;
		}
		
		public function get_child_type() {
			return $this->child_type;
		}
		
		public function set_interim_type( $type ) {
			$this->interim_type = $type;
		}
		
		public function get_interim_type() {
			return $this->interim_type;
		}
		
		/**
		 * Set up a proper REST response from the data we gathered
		 */
		protected function create_response( $request, $args, $data ) {
			$response    = rest_ensure_response( $data );
			$count_query = new \WP_Query();
			unset( $args['paged'] );
			$query_result = $count_query->query( $args );
			$total_posts  = $count_query->found_posts;
			$response->header( 'X-WP-Total', (int) $total_posts );
			$max_pages = ceil( $total_posts / $request['per_page'] );
			$response->header( 'X-WP-TotalPages', (int) $max_pages );
			if ( $request['page'] > 1 ) {
				$prev_page = $request['page'] - 1;
				if ( $prev_page > $max_pages ) {
					$prev_page = $max_pages;
				}
				$prev_link = add_query_arg( 'page', $prev_page, rest_url( $this->base ) );
				$response->link_header( 'prev', $prev_link );
			}
			if ( $max_pages > $request['page'] ) {
				$next_page = $request['page'] + 1;
				$next_link = add_query_arg( 'page', $next_page, rest_url( $this->base ) );
				$response->link_header( 'next', $next_link );
			}
			return $response;
		}
		
		/**
		 * Retrieve the requested list of post objects
		 */
		public function get_items( $request ) {
			$params = $request->get_params();
			$params['parent']  = $this->get_parent_type();
			$params['child']   = $this->get_child_type();
			$params['interim'] = $this->get_interim_type();
			
			$args = array(
				'posts_per_page' => $params['per_page'],
				'paged'          => $params['page'],
				'orderby'        => $params['orderby'],
			);
			
			if ( empty( $params['parent_id'] ) )
				$params['parent_id'] = ! empty( $params['id'] ) ? intval( $params['id'] ) : 0;
			/**
			 * Make sure we have a numeric ID for our parent ID
			 */
			if ( empty( $params['parent_id'] ) && ! empty( $params['slug'] ) ) {
				$parent = get_page_by_path( $params['slug'] );
				if ( ! is_wp_error( $parent ) )
					$params['parent_id'] = $parent->ID;
			}
			
			if ( empty( $params['parent_id'] ) || $this->parent_type != get_post_type( $params['parent_id'] ) ) {
				return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post id.' ), array( 'status' => 404 ) );
			}
			
			/**
			 * If there is a direct parent-child relationship, prep
			 * 		our arguments for a normal query
			 * Else, we'll need to run an intermediate query to handle 
			 * 		the many-to-many post relationships
			 */
			if ( empty( $params['interim'] ) ) {
				$args['post_type'] = $params['child'];
				$args['meta_query'] = array( array(
					'key' => sprintf( '_wpcf_belongs_%s_id', $params['parent'] ), 
					'value' => $params['parent_id'], 
				) );
			} else {
				$args['post_type'] = $params['interim'];
				$args['meta_query'] = array( array(
					'key' => sprintf( '_wpcf_belongs_%s_id', $params['parent'] ), 
					'value' => $params['parent_id'], 
				) );
				
				$interims = $this->do_query( $request, $args, false );
				foreach ( $interims as $k=>$v ) {
					$tmp = get_post_meta( $k, sprintf( '_wpcf_belongs_%s_id', $params['child'] ), true );
					if ( ! empty( $tmp ) ) {
						$this->interim_posts[$tmp] = $v;
					}
				}
				
				$args['post_type'] = $params['child'];
				$args['post__in'] = array_keys( $this->interim_posts );
				unset( $args['meta_query'] );
			}
			
			return $this->do_query( $request, $args );
		}
		
		/**
		 * Prepare individual items and gather appropriate meta data
		 */
		function make_data( $post, $data ) {
			do_action( 'types-relationship-api-pre-make-data' );
			
			$image = get_post_thumbnail_id( $post->ID );
			if ( $image ) {
				$_image = wp_get_attachment_image_src( $image, 'large' );
				if ( is_array( $_image ) ) {
					$image = $_image[0];
				}
			}
			
			$data[ $post->ID ] = array(
				'name'         => $post->post_title,
				'link'         => get_permalink( $post->ID ),
				'image_markup' => get_the_post_thumbnail( $post->ID, 'large' ),
				'image_src'    => $image,
				'excerpt'      => $post->post_excerpt,
				'slug'         => $post->post_name, 
				'type'         => $post->post_type, 
			);
			
			$meta = apply_filters( 'types-relationship-api-post-data', array(), $post );
			foreach ( $meta as $key => $value ) {
				$data[ $post->ID ][$key] = get_post_meta( $post->ID, $value, true );
			}
			
			do_action( 'types-relationship-api-made-data' );
			
			return $data;
		}
		
		/**
		 * Perform a query and prep the results to be returned
		 * @param stdClass $request the original request object
		 * @param array $args the query arguments
		 * @param bool $respond whether to create a REST response from the results
		 * @return mixed either an associative array of post objects or a REST response of post objects
		 */
		protected function do_query( $request, $args, $respond=true ) {
			$posts_query  = new \WP_Query();
			$query_result = $posts_query->query( $args );
			$params = $request->get_params();
			
			if ( empty( $params['parent_id'] ) )
				$params['parent_id'] = ! empty( $params['id'] ) ? intval( $params['id'] ) : 0;
			
			$data = array();
			if ( ! empty( $query_result ) ) {
				foreach ( $query_result as $post ) {
					$data = $this->make_data( $post, $data );
					/**
					 * Add some extra meta data & filter the data based on 
					 * 		what part of the relationship we're querying/returning
					 */
					if ( $post->post_type == $this->child_type ) {
						$data[$post->ID] = apply_filters( 'types-relationship-api-final-data', $data[$post->ID], $post );
						$data[$post->ID][$params['parent']] = get_post( $params['parent_id'] );
						if ( ! empty( $params['interim'] ) && array_key_exists( $post->ID, $this->interim_posts ) ) {
							$data[$post->ID][$params['interim']] = $this->interim_posts[$post->ID];
						}
					} else if ( $post->post_type == $this->parent_type ) {
						$data[$post->ID] = apply_filters( 'types-relationship-api-parent-data', $data[$post->ID], $post );
					} else if ( $post->post_type == $this->interim_type ) {
						$data[$post->ID] = apply_filters( 'types-relationship-api-interim-data', $data[$post->ID], $post );
					}
				}
			}
			
			if ( $respond ) {
				$final =  $this->create_response( $request, $args, $data );
				return $final;
			} else {
				return $data;
			}
		}
	}
}