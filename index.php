<?php
/**
 * Plugin Name: Simple - Restrict Post Access
 * Plugin URI: #
 * Description: Restrict specific post to specific users. So only assigned registered users will able to see the post. When activated, you will see <strong>Assign users</strong> meta box below post editor and you can select specific users for that post.This plugin will restrict access in menu, blog page, category page and single page.
 * Version: 1.0
 * Author: Sachin Solanki
 * Author URI: sachinsolanki007@gmail.com
 */

add_action( 'wp', 'wpauser_restric_access' );
function wpauser_restric_access()
{
    $post_type = get_post_type();
	if( ('post' === $post_type && is_singular()) || 'page' === $post_type )
	{
		$pcId = ( 'page' === $post_type ) ? get_the_ID() : $post->ID;
		global $post;
		$gauid = esc_html(get_post_meta($pcId, '_wpauser_meta_key', true));
		if($gauid != "") $gauidArr = explode(",",$gauid);
		$current_user = wp_get_current_user();
		$current_userid = $current_user->ID;
		if (  is_user_logged_in() && $gauid != "" && !in_array($current_userid,$gauidArr))
		{
			status_header( 404 );
			nocache_headers();
			include( get_query_template( '404' ) );
			die();
		}
	}	
}

add_filter( 'wp_nav_menu_items', 'your_custom_menu_item', 10, 2 );
function your_custom_menu_item ( $items, $args ) {
    $current_userid = "";
	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		$current_userid = $current_user->ID;
	}
	
  $termid = $args->menu->term_id;
  $menu_name = $args->menu->slug;
  $menu_items = wp_get_nav_menu_items($termid);
 //print_r($args );
 if ( count($menu_items) > 0 ) {
    $menu = wp_get_nav_menu_object( $locations[ $menu_name ] );
    $menu_items = wp_get_nav_menu_items($termid);
 
    foreach ( (array) $menu_items as $key => $menu_item ) {
		$gauidArr = [];
        $title = $menu_item->title;
        $url = $menu_item->url;
		$postId = $menu_item->object_id;
		$gauid = esc_html(get_post_meta($postId, '_wpauser_meta_key', true));
		
		if($gauid != "") $gauidArr = explode(",",$gauid);
        if(!in_array($current_userid,$gauidArr) && $gauid != "") { continue; }	
		$menu_list .= '<li><a href="' . $url . '">' . $title . '</a></li>';
    }
} else {
    $menu_list = '<li>Menu "' . $menu_name . '" not defined.</li>';
}
	
    return $menu_list;
}

function wpauser_show_only_private_post_for_logged_in_user( $cquery )
{
    if ( ! $cquery->is_main_query() || is_admin() ) {
		return;
    }
	
	$current_userid = "";
	if ( is_user_logged_in() ) {
		$current_user = wp_get_current_user();
		$current_userid = $current_user->ID;
	}	
	$regex = '(^|,)' . $current_userid . '(,|$)';
	$mquery[] = array(
	   'relation' => 'OR',
		array(
		 'key' => '_wpauser_meta_key',
		 'compare' => 'NOT EXISTS',
		 'value' => ''
		),
		array(
		 'key' => '_wpauser_meta_key',
		 'compare' => 'REGEXP',
		 'value' => $regex
		)
	);
   	$cquery->set('meta_query',$mquery);
       
}
add_action( 'pre_get_posts', 'wpauser_show_only_private_post_for_logged_in_user' );
 
function wpauser_add_custom_box()
{
    $screens = ['post', 'page'];
    foreach ($screens as $screen) {
        add_meta_box(
            'wpauser_box_id',           // Unique ID
            'Assign Users',  // Box title
            'wpauser_custom_box_html',  // Content callback, must be of type callable
            $screen                   // Post type
        );
    }
}
add_action('add_meta_boxes', 'wpauser_add_custom_box');

function wpauser_custom_box_html($post)
{
    $gauidArr = [];
	$gauid = esc_html(get_post_meta($post->ID, '_wpauser_meta_key', true));
	if($gauid != "") $gauidArr = explode(",",$gauid);
	$blogusers = get_users( [ 'role__in' => [ 'author', 'subscriber','administrator','editor','contributor' ] ] );
?>
    <label for="wpauser_field"><?php echo esc_html_e( 'Select users:', 'wpauser' ); ?></label>
    <select name="wpauser_field[]" id="wpauser_field" multiple>
        <?php foreach ( $blogusers as $user ) { 
			$auid = $user->id;
			$auname = $user->display_name;
		?>
        <option value="<?php echo esc_html( $auid ); ?>" <?php echo (in_array($auid,$gauidArr)) ? "selected" : ""; ?>><?php echo esc_html( $auname ); ?></option>
        <?php } ?>
    </select>
    <?php
}

function wpauser_save_postdata($post_id)
{
	if (array_key_exists('wpauser_field', $_POST)) {
		$wpauser_field = isset( $_POST['wpauser_field'] ) ? (array) $_POST['wpauser_field'] : array();
		$array = array_map( 'sanitize_text_field', wp_unslash( $wpauser_field ) );
		update_post_meta( $post_id, '_wpauser_meta_key', implode(",",$wpauser_field) );
    }
}
add_action('save_post', 'wpauser_save_postdata');

?>