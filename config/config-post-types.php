<?php
// https://posttypes.jjgrainger.co.uk/post-types/create-a-post-type
// https://codex.wordpress.org/Function_Reference/register_post_type
use PostTypes\PostType;
use PostTypes\Taxonomy;

// services
$place_options = [
    'supports' => array('title'),
    'menu_position'=>5,
    'publicly_queryable'=>false,
    'supports'=>array('title','thumbnail','comments','trackbacks','author'),
    'show_in_rest'=>true,
];
$place = new PostType('place',$place_options );
$place->icon('dashicons-admin-home');
$place->register();