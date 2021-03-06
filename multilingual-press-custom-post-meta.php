<?php
/**
 * Plugin Name: MultilingualPress Custom Post Meta add-on
 * Plugin URI:  http://www.keraweb.nl
 * Description: Add post meta sync functionality to MultilingualPress
 * Author:      Jory Hogeveen
 * Author URI:  http://www.keraweb.nl
 * Version:     0.1
 * Text Domain: multilingualpress
 * Domain Path: /languages
 * License:     The awesome licence
 * Network:     true
 */

defined( 'ABSPATH' ) or die('Move along, nothing to see here.');


class Multilingualpress_Custom_Post_Meta_Sync {
	
	/*
	 * @var array
	 * 
	 * The meta keys to sync
	 */
	private $mlp_sync_custom_post_meta = array();
	
	/*
	 * @var array
	 * 
	 * The available meta keys for all available post types
	 */
	private $post_types_meta_keys = array();
	
	/*
	 * @var array
	 * 
	 * The linked posts with blog id's
	 */
	private $remote_site_posts = array();
	
	/*
	 * @var array
	 * 
	 * The post meta key's and values to sync with other languages
	 */
	private $send_post_meta = array();
	
	
	/*public function __construct() {
	}*/
	
	public function setup() {
		
		$this->mlp_sync_custom_post_meta = get_option('mlp_sync_custom_post_meta');
		if ($this->mlp_sync_custom_post_meta == false) {
			add_option('mlp_sync_custom_post_meta', '', '', 'yes');
		}
				
		$this->post_types_meta_keys = generate_post_type_meta_keys();
		add_action('mlp_modules_add_fields', array($this, 'admin_settings_page'));
		add_action('mlp_modules_save_fields', array($this, 'admin_settings_save'), 10, 1);
		
		/*add_action('mlp_translation_meta_box_bottom', array ( $this, 'show_field' ), 2, 3);*/
		add_filter('mlp_pre_save_post_meta', array ( $this, 'register_meta_fields' ), 10, 2);
		add_filter('mlp_pre_insert_post_meta', array ( $this, 'save_meta' ), 10, 2);
				
	}
	
	/*public function show_field(WP_Post $post, $remote_site_id, WP_Post $remote_post = NULL) {

		//$value = '';

		if ( NULL !== $remote_post ) {
			switch_to_blog( $remote_site_id );
			
				$this->remote_site_posts[$remote_site_id] = $remote_post->ID;
			//$value = get_post_meta( $remote_post->ID, $this->meta_key, TRUE );
			restore_current_blog();
		}

		//$value = esc_attr( $value );

	}*/
	
	public function register_meta_fields( array $post_meta ) {
		
		if ( ! empty ( $_POST ) ) {
			
			$blog_id = get_current_blog_id();
			$post_id = intval($_POST['post_ID']);
			$type = 'post';
			
			if (function_exists('mlp_get_linked_elements')) {
				$interlinked_posts = mlp_get_linked_elements( $post_id, $type, $blog_id );
			} else {
				return $post_meta;
			}
			
			unset($interlinked_posts[$blog_id]);
						
			$new_post_meta = $_POST['meta'];
			$sync_post_meta = $this->mlp_sync_custom_post_meta[$_POST['post_type']];
			
			foreach ($new_post_meta as $meta_id => $meta_info) {
				if (array_key_exists($meta_info['key'], $sync_post_meta) && $sync_post_meta[$meta_info['key']] == 1) {
					//logValue('found', $meta_info['key']);
					
					$key = 'meta';
					$value = $meta_info;
					
					foreach ($interlinked_posts as $linked_site => $linked_post) {
						//$this->send_post_meta[$linked_site]['meta'][] = $linked_post;
						
						/*
						 * Comment until support multiple values with same key
						 */
						//$this->send_post_meta[$linked_site][$meta_id] = $meta_info;
						//$post_meta[$meta_id] = $meta_info;
						$this->send_post_meta[$linked_site] = $meta_info['value'];
						$post_meta[$meta_info['key']] = $meta_info['value'];
					}
				}
			}
		}
		return $post_meta;
	}

	/*
	 * Save meta keys and values to linked posts
	 *
	 * @param 	array 	$post_meta
	 * @param 	array 	$save_context
	 */
	public function save_meta( array $post_meta, array $save_context ) {
		
		$site_id = $save_context[ 'target_blog_id' ];

		if ( empty ( $this->send_post_meta[ $site_id ] ) )
			return $post_meta;

		$post_meta[ $this->meta_key ] = $this->send_post_meta[ $site_id ];

		return $post_meta;
	}

	
	/*
	 * Update sync settings in option
	 *
	 * @param 	$return = $_POST object
	 */
	public function admin_settings_save($return) {
		if ($return['mlp_cpm']) {
			update_option('mlp_sync_custom_post_meta', $return['mlp_cpm']);
		}
	}
	
	/*
	 * Add post meta settings on the MLP settings page
	 */
	public function admin_settings_page() {
		
		$c = '';
		$c .= '<div class="mlp-extra-settings-box" id="mlp-cpt-meta-settings">';
		$c .= '<h4>Custom Post Meta Translator Settings</h4>';
		
		foreach ($this->post_types_meta_keys as $post_type => $post_meta) {
			if ($post_meta) {
				$c .= '<p><strong>'.ucfirst($post_type).'</strong></p>';
				foreach ($post_meta as $meta_key) {
					$checked = false;
					if (isset($this->mlp_sync_custom_post_meta[$post_type][$meta_key]) && $this->mlp_sync_custom_post_meta[$post_type][$meta_key] == 1) {
					$checked = ' checked="checked"';
					}
					$c .= '<div class="mlp-block-wrapper"><label for="mlp_cpm_'.$post_type.'_'.$meta_key.'" class="mlp-block-label">
						<input type="checkbox" value="1" name="mlp_cpm['.$post_type.']['.$meta_key.']" id="mlp_cpt_'.$post_type.'_'.$meta_key.'"'.$checked.'> 
						'.$meta_key.'
					</label></div>';
				}
			}
		}
				
		$c .= '</div>';
		
		echo $c;
		
	}
	
}

add_action( 'mlp_and_wp_loaded', array( new Multilingualpress_Custom_Post_Meta_Sync(), 'setup' ) );


function generate_post_type_meta_keys() {
    global $wpdb;
	
	$post_types = get_post_types(array('public'=>true));
	$meta_total = array();
	
    foreach ($post_types as $post_type) {
		$query = "
			SELECT DISTINCT($wpdb->postmeta.meta_key) 
			FROM $wpdb->posts 
			LEFT JOIN $wpdb->postmeta 
			ON $wpdb->posts.ID = $wpdb->postmeta.post_id 
			WHERE $wpdb->posts.post_type = '%s' 
			AND $wpdb->postmeta.meta_key != '' 
			AND $wpdb->postmeta.meta_key NOT RegExp '(^[_0-9].+$)' 
			AND $wpdb->postmeta.meta_key NOT RegExp '(^[0-9]+$)'
		";
		$meta_keys = $wpdb->get_col($wpdb->prepare($query, $post_type));
		$meta_total[$post_type] = $meta_keys;
		
		/*
		 * Need review if this is needed
		 */
		//set_transient($post_type.'_meta_keys', $meta_keys, 60*60*24); # 1 Day Expiration
	}
    return $meta_total;
}
