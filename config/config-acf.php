<?php

// Save acf fields
add_filter('acf/settings/save_json', 'ms_acf_json');
function ms_acf_json( $path ) {
    $path = get_stylesheet_directory() . '/acf-json';
    return $path;
}

// Acf options pages
function ms_acf_option_pages(){

    // register sub pages
    if ( function_exists('acf_add_options_sub_page') ) {
      acf_add_options_sub_page('General');
      acf_add_options_sub_page('Footer');
    }

    // rename top level page
    if(function_exists('acf_set_options_page_menu')){
      acf_set_options_page_menu('Global Content');
    }
  
    // set top level icon globe
    add_action('admin_head', function(){
      echo '<style type="text/css">#adminmenu .toplevel_page_acf-options-general div.wp-menu-image:before { content: "\f319"; }</style>';
    });
  
    // set menu order filters
    add_filter('custom_menu_order', 'ms_acf_menu_order');
    add_filter('menu_order', 'ms_acf_menu_order', 1);
  
  }
  add_action('init', 'ms_acf_option_pages', 5);


// Reorder global item
function ms_acf_menu_order($menu_ord){
    if (!$menu_ord) return true;  
    $menu = 'acf-options-general';
    $menu_ord = array_diff($menu_ord, array($menu));
    array_splice( $menu_ord, 1, 0, array($menu) );
    return $menu_ord;
}


// API KEY
function my_acf_google_map_api( $api ){
    $api['key'] = get_field('google_api_key','option');
    return $api;
}

add_filter('acf/fields/google_map/api', 'my_acf_google_map_api');

function ms_remove_paragraph_acf() {
  remove_filter('acf_the_content', 'wpautop' );
}
add_action('acf/init', 'ms_remove_paragraph_acf');


add_filter('acf/load_field/name=chosen_testimonials', 'acf_load_testimonials_field');
function acf_load_testimonials_field( $field ) {
    $field['choices'] = array();
    $comments = get_comments(
      array(
        'parent'=>0,
        'post_type'=>'place',
      )
    );

    if( is_array($comments) ) {
        foreach( $comments as $key => $comment ) {
            $comment_id = $comment->comment_ID;
            $rating = intval(get_comment_meta($comment_id,'rating',true));
            if($rating !== 0){
              $name = '<a href="'.get_edit_comment_link($comment_id).'">'.$comment->comment_author.' Rating '.$rating.'</a>';
              $field['choices'][ $comment_id ] = $name;
            }
        }
    }

    return $field;
}

add_filter('acf/load_field/name=category', 'ms_acf_places_category');
function ms_acf_places_category( $field ) {
    $field['choices'] = array();
    $values = array(
        __('Essen & Getränke','belocalhero'),
        __('Wellness & Beauty','belocalhero'),
        __('Lektionen','belocalhero'),
        __('Andere','belocalhero'),
        __('Haushalt','belocalhero'),
        __('Fitness','belocalhero'),
        __('Wissen','belocalhero'),
        __('Blumen & Garten','belocalhero'),
        __('Kunst & Design','belocalhero'),
        __('Handwerker','belocalhero'),
        __('Papeterie & Bücher','belocalhero'),
        __('Games','belocalhero'),
        __('Mobilität','belocalhero'),
    );
    if( !empty($values) ) {
        foreach($values as $key => $val){
            the_row();
            $value = $val;
            $label = $val;
            $field['choices'][ $val ] = $label;
        }
    }
    return $field;
}

add_filter('acf/load_field/name=delivery', 'ms_acf_places_delivery');
function ms_acf_places_delivery( $field ) {
    $field['choices'] = array();
    $values = array(
        __('Abholen','belocalhero'),
        __('Briefkastenlieferung','belocalhero'),
        __('Post','belocalhero'),
        __('Online-Streaming','belocalhero'),
    );
    if( !empty($values) ) {
        foreach($values as $val){
            the_row();
            $value = $val;
            $label = $val;
            $field['choices'][ $value ] = $label;
        }
    }
    return $field;
}



add_filter('acf/load_field/name=payment_methods', 'ms_acf_places_payment_methods');
function ms_acf_places_payment_methods( $field ) {
    $field['choices'] = array();
    $values = array(
        __('Rechnung','belocalhero'),
        __('Bargeld','belocalhero'),
        __('Banküberweisung/Twint','belocalhero'),
        __('Gratis','belocalhero'),
    );
    if( !empty($values) ) {
        foreach($values as $val){
            the_row();
            $value = $val;
            $label = $val;
            $field['choices'][ $value ] = $label;
        }
    }
    return $field;
}


add_filter('acf/load_field/key=field_5e8489733e50a', 'ms_acf_places_type');
function ms_acf_places_type( $field ) {
    $field['choices'] = array();
    $values = array(
        __('Produkt','belocalhero'),
        __('Service','belocalhero'),
        __('Andere','belocalhero'),
    );
    if( !empty($values) ) {
        foreach($values as $val){
            the_row();
            $value = $val;
            $label = $val;
            $field['choices'][ $value ] = $label;
        }
    }
    return $field;
}


add_filter('acf/fields/post_object/query', 'bs_featured_place_only_publish', 10, 3);
function bs_featured_place_only_publish( $args, $field, $post_id ) {
    $args['post_status'] = 'publish';
    return $args;
}