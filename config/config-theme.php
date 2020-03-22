<?php
// Theme
define('OTF_THEME','Outdoor_Family');
define('OTF_VERSION',1.0);
define('NEWSLETTER_URL','https://us4.api.mailchimp.com/3.0/lists/9c06414782/members');
define('NEWSLETTER_API_KEY','28f30c6d483c173adf918bb3d8d03d61-us4');


// Allow uploading files like svg
define('ALLOW_UNFILTERED_UPLOADS', true);

function filter_site_upload_size_limit( $size ) {
	$size = 60 * 1024 * 1024;
    return $size;
}
add_filter( 'upload_size_limit', 'filter_site_upload_size_limit', 20 );


// Theme Support
add_theme_support('post-thumbnails');

// Menus
register_nav_menus([
    'header_menu'   => 'Main Menu',
]);

function ms_allow_svg_files($mimes = array()) {
  	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
}
add_action('upload_mimes', 'ms_allow_svg_files');


add_filter( 'enter_title_here', 'ms_change_enter_title_here' );
function ms_change_enter_title_here( $title ){
    $screen = get_current_screen();
    if($screen->post_type === 'place') {
        $title = 'Enter Place Name';
    }
    return $title;
}