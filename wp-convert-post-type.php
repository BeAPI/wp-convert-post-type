<?php
/*
Plugin Name: Convert Post Type Tools
Version: 0.1
Plugin URI: http://redmine.beapi.fr/projects/show/simple-custom-type
Description: A small WordPress plugin that allow to convert a post type to an another post type. 
Author: Amaury Balmer
Author URI: http://www.beapi.fr

----

Copyright 2012 Amaury Balmer (amaury@beapi.fr)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

add_action( 'plugins_loaded', 'init_convert_post_type', 11 );
function init_convert_post_type() {
	global $convert_post_type;

	if ( is_admin() && !class_exists('SimpleCustomTypes_Admin_Conversion') ) {
		$convert_post_type = new ConvertPostType_Admin();
	}
}

class ConvertPostType_Admin {
	/**
	 * Constructor
	 *
	 */
	function __construct() {
		global $messages;
		
		// Add message for conversion
		$messages[9991] = __('Item(s) converted on another post type with success.', 'wp-convert-post-type');
		
		// Add action on edit posts page
		add_filter( 'admin_footer', array(&$this, 'addActions') );
		add_action( 'admin_init', array(&$this, 'listenConversion' ) );
	}
	
	/**
	 * Listen POST datas for make bulk posts conversion to new post type
	 */
	function listenConversion() {
		global $pagenow, $wpdb;
		
		if ( $pagenow != 'edit.php' ) 
			return false;
		
		// Default values for CPT
		$typenow = ( isset($_REQUEST['post_type']) ) ? $_REQUEST['post_type'] : 'post';
		
		if ( isset($_REQUEST['action']) && substr($_REQUEST['action'], 0, strlen('convert_cpt')) == 'convert_cpt' ) {
			check_admin_referer( 'bulk-posts' );
			
			// Source CPT
			$source_cpt = get_post_type_object( $typenow );
			if ( !current_user_can( $source_cpt->cap->edit_posts ) )
				wp_die( __( 'Cheatin&#8217; uh?', 'wp-convert-post-type' ) );
			
			// Destination CPT
			$destination_cpt = get_post_type_object( substr($_REQUEST['action'], strlen('convert_cpt')+1) );
			if ( !current_user_can( $destination_cpt->cap->edit_posts ) )
				wp_die( __( 'Cheatin&#8217; uh?', 'wp-convert-post-type' ) );
			
			// Loop on posts
			foreach( (array) $_REQUEST['post'] as $post_id ) {
				// Change the post type
				$object = get_post_to_edit( $post_id );
				$object->post_type = $destination_cpt->name;
				wp_update_post( (array) $object );
				
				// Clean object cache
				clean_post_cache($post_id);
			}
			
			$location = 'edit.php?post_type=' . $typenow;
			if ( $referer = wp_get_referer() ) {
				if ( false !== strpos( $referer, 'edit.php' ) )
					$location = $referer;
			}
			
			$location = add_query_arg( 'message', 9991, $location );
			wp_redirect( $location );
			exit;
		}
	}
	
	/**
	 * Add JS on footer WP Admin for add option in select bulk action list
	 */
	function addActions() {
		global $pagenow;
		
		// Default values for CPT
		$current_cpt = ( isset($_REQUEST['post_type']) ) ? $_REQUEST['post_type'] : 'post';
		
		if ( $pagenow == 'edit.php' ) {
			?>
			<script type="text/javascript">
				<?php foreach( get_post_types( array('public' => true, 'show_ui' => true), 'objects' ) as $post_type ) :
					if ( $post_type->name == $current_cpt ) continue; // Not itself...
					if ( !current_user_can( $post_type->cap->edit_posts ) ) continue; // User can ?
					?>
					jQuery('div.actions select').append('<option value="convert_cpt-<?php echo esc_attr($post_type->name); ?>"><?php echo esc_html(sprintf(__('Convert to %s', 'wp-convert-post-type'), $post_type->labels->name)); ?></option>');
				<?php endforeach; ?>
			</script>
			<?php
		}
	}
}
?>