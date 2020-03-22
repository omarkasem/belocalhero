<?php
// Packages
require_once 'libs/vendor/autoload.php';

// Theme
require_once 'config/config-theme.php';

// Acf
require_once 'config/config-acf.php';

// Required Plugins
require_once 'config/config-plugins.php';

// Post types & Taxonomies
require_once 'config/config-post-types.php';

// REST API CLASSES //
// Main
require_once(__DIR__.'/inc/Main.php');

// Auth
require_once(__DIR__.'/inc/Auth.php');

// Other
require_once(__DIR__.'/inc/Other.php');

// Profile
require_once(__DIR__.'/inc/Profile.php');

// Place
require_once(__DIR__.'/inc/Place.php');

// Comments
require_once(__DIR__.'/inc/Comments.php');

// Blog
require_once(__DIR__.'/inc/Blog.php');

// Filter
require_once(__DIR__.'/inc/Filter.php');


function fb_mce_external_languages($initArray){
   $initArray['spellchecker_languages'] = '+German=de, English=en';
   return $initArray;
}
add_filter('tiny_mce_before_init', 'fb_mce_external_languages');


function bs_change_lang_google_map($api){
	if(ICL_LANGUAGE_CODE === 'de'){
		$api['language'] = 'de';
	}else{
		$api['language'] = 'en';
	}
	return $api;
}
add_filter('acf/fields/google_map/api', 'bs_change_lang_google_map');