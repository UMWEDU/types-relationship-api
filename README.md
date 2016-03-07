# Types Relationship API

Allows you to easily set up custom WP REST API endpoints for use with the post type relationships that the Types plugin implements.

## Usage

To use this functionality, you will need to build your own plugin to extend this functionality. To do so, you would create a new class to extend the abstract class defined within this plugin.

In the constructor method, you must define values for the `$parent_type` and `$child_type` properties. If this is a one-to-many Types relationship, that's all you need to do in the constructor method.

If this is a many-to-many relationship, you will need to define things a little differently. You will need to define the `$interim_type` property as well. In this case, the `$parent_type` property should be defined to the post type that is acting as the container of the information you want to retrieve (for instance, if you use [the example in the Types documentation, with Bands and Events](https://wp-types.com/documentation/user-guides/many-to-many-post-relationship/), and you want to see which bands are playing at a specific event, you would set "event" as the `$parent_type`, "band" as the `$child_type` and "appearance" as the `$interim_type`. If, however, you wanted to see which events a band was playing, you would keep "appearance" set as the `$interim_type`, but you'd set "band" as the `$parent_type` and "event" as the `$child_type`.

You will also be required to define a method called `add_meta_data()` within your extension class. That method allows you to specify which post meta keys should be retrieved and returned along with the final post information (so, if you are retrieving bands that will be playing at an event, you would provide an array of the meta keys that you want to retrieve for each band).

Some example code might look like:

```php
/**
 * Will be accessible at /wp-json/types/relationships/v1/event/band/[event-ID]
 * The route is built like: /types/relationships/v1/{$parent_type}/{$child_type}
 */
class My_Custom_Types_Relationship_API {
	function __construct() {
		$this->parent_type  = 'event';
		$this->child_type   = 'band';
		$this->interim_type = 'appearance';
	}

	function add_meta_data( $data, $post ) {
		/**
		 * This block is strongly recommended, otherwise the API will attempt to
		 * 		retrieve this meta data for all of the post types being queried
		 */
		if ( $post->post_type != $this->child_type )
			return $data;

		/**
		 * This is the part you would actually want to define
		 * This is an associative array with the keys being the actual keys you want the
		 * 		API data to use to define these data; and the values being the keys that
		 * 		are used within the WordPress database to store these data
		 */
		return array_merge( $data, array(
			'api-key' => 'meta-data-key',
		) );
	}
}
```

That's it. Once you've defined your child class, you're basically done. You will need to make sure that you include/require the abstract class somewhere in your plugin (obviously), but once you set up the extended/child class as shown above, you will then be able to query the WP REST API for any posts that are related to another post type using the Types relationship functionality.
