<?php
class OutdoorfPlace extends OutdoorfMain{

    public function __construct(){
        $this->registerHooks();
    }

    public function registerHooks(){
        add_filter('rest_prepare_place', array($this,'removeAdditionalData'), 10, 3 );
        add_action('rest_api_init',array($this,'addFeatruedImage'));
        add_action('rest_api_init',array($this,'addTranslatedId'));
        add_action('rest_api_init',array($this,'addACFData'));
        // add_action('rest_api_init',array($this,'addPlaceType'));
        add_action('rest_api_init',array($this,'addRelatedPlaces'));
        // add_action('rest_api_init',array($this,'addCommentsNumber'));
        // add_action('rest_api_init',array($this,'addCommentsNumberwithRating'));
        // add_action('rest_api_init',array($this,'addPlacesNumber'));
        // add_action('rest_api_init',array($this,'addAvgRating'));
        add_action('rest_api_init',array($this,'createPlaceLocation'));
        // add_action('rest_api_init',array($this,'createPlacesTypes'));
        // add_action('rest_api_init',array($this,'createFavorite'));
        add_action('rest_api_init',array($this,'createAddPlace'));
        add_action('rest_api_init',array($this,'createEditPlace'));
        
        // add_filter('rest_place_query', array($this,'restPlaceType'), 10, 2);
        add_filter('rest_place_query', array($this,'restPlaceIds'), 10, 2);
        add_filter('rest_place_query', array($this,'myPlaces'), 10, 2);
        // add_filter('rest_place_query', array($this,'restFavoritePlaces'), 10, 2);
        add_filter('rest_place_query', array($this,'restPlaceSearch'), 99, 2);
        
    }

    public function restPlaceSearch($args, $request){
        if (empty($request['place_search'])) {
            return $args;
        }
        if(isset($_GET['lang']) && $_GET['lang'] === 'en'){
            global $sitepress;
            $lang='en';
            $sitepress->switch_lang($lang);
        }else{
            global $sitepress;
            $lang='de';
            $sitepress->switch_lang($lang);
        }
        $args['s'] = $request['place_search'];


        $query = new WP_Query($args);
        if(!$query->have_posts()){
            if($query->found_posts === 0){
                $args['s'] = '';
                $args['tax_query'] = '';
                $meta_query[] =
                    array(
                        'key'     => 'address',
                        'value'   => $_GET['place_search'],
                        'compare' => 'LIKE',
                );
                $args['meta_query'] = $meta_query;
            }
        }


        $query = new WP_Query($args);
        if(!$query->have_posts()){
            if($query->found_posts === 0){
                $args['s'] = '';
                $args['tax_query'] = '';

                $string = str_replace(',','',$_GET['place_search']);
                $string = explode(' ',$string);
                $meta_query = [];
                if(!empty($string) && is_array($string)){
                    $string = array_filter($string);
                    $meta_query[] =
                        array(
                            'key'     => 'address',
                            'value'   => $string[0],
                            'compare' => 'LIKE',
                    );
                }
                $args['meta_query'] = $meta_query;
            }
        }

        return $args;
    }



    public function addTranslatedId(){
        $this->add_rest_field('place','translated_id',
            function($data){
                $post_lang = apply_filters( 'wpml_post_language_details', NULL,$data['id'] );
                $post_lang = $post_lang['language_code'];
                if($post_lang == 'en'){
                    $lang = 'de';
                }else{
                    $lang = 'en';
                }
                return apply_filters( 'wpml_object_id', $data['id'], 'place', false, $lang );
            }
        );
    }

    public function createEditPlace(){
        $this->createEndpoint('edit_place','POST',array($this,'EditPlaceCallback'));
    }

    public function EditPlaceCallback(){
        // Validation
        if(!is_user_logged_in()){
            return $this->returnError('edit_place_rest','Sorry, you must be logged in to add a new place.',411);
        }
        $place_id = intval($_POST['place_id']);
        if($place_id === 0 || get_post_type($place_id) !== 'place'){
            return $this->returnError('edit_place_rest','Place ID is required.');
        }

        $user_id = intval(get_current_user_id());
        $post_author_id = intval(get_post_field( 'post_author', $place_id ));
        if($user_id !== $post_author_id){
            return $this->returnError('edit_place_rest','You cannot edit other people places.');
        }

        if(is_wp_error($this->validateAddPlace())){
            return $this->validateAddPlace();
        }

        // Category
        $type = $_POST['type'];
        if(empty($type)){
            return $this->returnError('edit_place_rest','Type is required.');
        }


        // Insert place
        $place_id = wp_insert_post(array(
            'ID'=>$place_id,
            'post_title'=>sanitize_text_field($_POST['name']),
            'post_type'=>'place',
            'post_status'=>'publish',
        ));

        $this->updateFieldsAddPlace($place_id);


        // Address
        $address = array('address'=>$_POST['address'],'lat'=>$_POST['lat'],'lng'=>$_POST['lng']);
        update_post_meta($place_id,'address',$address);

        // Featured Image
        if($_POST['feat_image'] != '' && $_POST['feat_image_name'] != '' && $_POST['feat_image'] != 'undefined' && $_POST['feat_image_name'] != 'undefined' ){
            $feat_attach_id = intval($this->uploadImage($_POST['feat_image'],$_POST['feat_image_name']));
            if($feat_attach_id !== 0){
                set_post_thumbnail($place_id,$feat_attach_id);
            }
        }


        // Other images
        if(!empty($_POST['other_images'])){
            $all_ids = [];
            foreach($_POST['other_images'] as $image){
                $image = $this->uploadImage($image['image'],$image['image_name']);
                if(intval($image) !== 0){
                    $all_ids[] = $image;
                }
            }
            if(!empty($all_ids)){
                if($feat_attach_id === 0){
                    $first = array_shift($all_ids);
                    set_post_thumbnail($place_id,$first);
                }
                update_post_meta($place_id,'images',$all_ids);
            }
        }



        return true;
        
    }




    public function createAddPlace(){
        $this->createEndpoint('add_place','POST',array($this,'addPlaceCallback'));
    }

    public function addPlaceCallback(){
        // Validation
        if(!is_user_logged_in()){
            return $this->returnError('add_place_rest','Sorry, you must be logged in to add a new place.',411);
        }
        $user_id = intval(get_current_user_id());
        if(is_wp_error($this->validateAddPlace())){
            return $this->validateAddPlace();
        }

        // Category
        $type = $_POST['type'];
        if(empty($type)){
            return $this->returnError('add_place_rest','Type is required.');
        }

        // Image
        // if($_POST['feat_image'] == '' || $_POST['feat_image_name'] == ''){
        //     return $this->returnError('add_place_rest','Featured image is required.');
        // }


        // Insert place
        $place_id = wp_insert_post(array(
            'post_title'=>sanitize_text_field($_POST['name']),
            'post_type'=>'place',
            'post_status'=>'publish',
            'post_author'=>$user_id,
        ));

        if(isset($_POST['lang']) && $_POST['lang'] == 'en'){
            $set_language_args = array(
                'element_id'    => $place_id,
                'element_type'=>'post_place',
                'language_code'   => 'en',
            );
            do_action( 'wpml_set_element_language_details', $set_language_args );
        }else{
            $set_language_args = array(
                'element_id'    => $place_id,
                'element_type'=>'post_place',
                'language_code'   => 'de',
            );
            do_action( 'wpml_set_element_language_details', $set_language_args );
        }

        $this->updateFieldsAddPlace($place_id);


        // Address
        $address = array('address'=>$_POST['address'],'lat'=>$_POST['lat'],'lng'=>$_POST['lng']);
        update_post_meta($place_id,'address',$address);

        // Featured Image
        if($_POST['feat_image'] != '' && $_POST['feat_image_name'] != '' && $_POST['feat_image'] != 'undefined' && $_POST['feat_image_name'] != 'undefined' ){
            $feat_attach_id = intval($this->uploadImage($_POST['feat_image'],$_POST['feat_image_name']));
            if($feat_attach_id !== 0){
                set_post_thumbnail($place_id,$feat_attach_id);
            }
        }


        // Other images
        if(!empty($_POST['other_images'])){
            $all_ids = [];
            foreach($_POST['other_images'] as $image){
                $image = $this->uploadImage($image['image'],$image['image_name']);
                if(intval($image) !== 0){
                    $all_ids[] = $image;
                }
            }
            if(!empty($all_ids)){
                if(intval($feat_attach_id) === 0){
                    $first = array_shift($all_ids);
                    set_post_thumbnail($place_id,$first);
                }
                update_post_meta($place_id,'images',$all_ids);
            }
        }


        return true;
        
    }

    private function getTermBy($term_id, $taxonomy, $language) {
        global $sitepress;
     
        $translated_term_id = icl_object_id(intval($term_id), $taxonomy, true, $language);
     
        remove_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1 );
        $translated_term_object = get_term_by('id', intval($translated_term_id), $taxonomy);
        add_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1, 1 );
     
        return $translated_term_object;
    }


    private function updateFieldsAddPlace($place_id){
        $fields = array(
            'zipcode'=>'number',
            'email'=>'text',
            'website'=> 'text',
            'phone_number'=> 'text',
            'description'=> 'text',
            'hr_from'=> 'text',
            'hr_to'=> 'text',
            'type'=> 'text',
            'category'=>'array',
            'delivery'=> 'array',
            'payment_methods'=> 'array',
        );
        foreach($fields as $name => $type){
            if($type === 'text' && isset($_POST[$name])){
                update_post_meta($place_id,$name,sanitize_text_field($_POST[$name]));
            }elseif($type === 'number' && isset($_POST[$name])){
                update_post_meta($place_id,$name,intval($_POST[$name]));
            }elseif($type === 'array' && isset($_POST[$name])){
                if(!empty($_POST[$name])){
                    $new_arr = [];
                    foreach($_POST[$name] as $key => $value){
                        $new_arr[$key] = sanitize_text_field($value);
                    }
                    update_post_meta($place_id,$name,$new_arr);
                }
            }
        }
    }

    private function validateAddPlace(){
        $required_fields = array(
            'name',
            'address',
            'lat',
            'lng',
            'email',
            'description',
        );
        foreach($required_fields as $field){
            if(!isset($_POST[$field]) || $_POST[$field] == ''){
                return $this->returnError('add_place_rest','Place '.$field.' is required.');
            }
        }
    }


    public function createFavorite(){
        $this->createEndpoint('favorite','POST',array($this,'favoriteCallback'));
    }

    public function favoriteCallback(){
        // Validation
        if(!is_user_logged_in()){
            return $this->returnError('rest_favorite','Sorry, you must be logged in to favorite a place.',411);
        }
        $user_id = intval(get_current_user_id());
        if(!isset($_POST['place_id']) || get_post_type($_POST['place_id']) !== 'place'){
            return $this->returnError('rest_favorite','Place id is required.');
        }


        $place_id = intval($_POST['place_id']);
        $other_place_id = $this->getTranslatedID($place_id);

        $favorites = $this->getFavorites($user_id);
        if(!is_array($favorites)){
            $favorites = [];
        }

        $favorites_number = intval(get_post_meta($place_id,'favorites_number',true));
        if(in_array($place_id,$favorites)){
            if(($key = array_search($place_id, $favorites)) !== false) {
                unset($favorites[$key]);
            }
            if(($key = array_search($other_place_id, $favorites)) !== false) {
                unset($favorites[$key]);
            }
            $favorites_number--;
        }else{
            $favorites[] = $place_id;
            $favorites[] = $other_place_id;
            $favorites_number++;
        }

        update_post_meta($place_id,'favorites_number',$favorites_number);
        update_user_meta($user_id,'favorite_places',$favorites);

        $endpoint = rest_url("wp/v2/place/".$place_id).'?token='.$_POST['token'].'&token_type='.$_POST['token_type'];
        return json_decode(wp_remote_get($endpoint)['body']);
    }

    private function getTranslatedID($place_id){
        $post_lang = apply_filters( 'wpml_post_language_details', NULL,$place_id);
        if(is_wp_error($post_lang)){
            return $post_lang;
        }
        $post_lang = $post_lang['language_code'];
        if($post_lang == 'en'){
            $lang = 'de';
        }else{
            $lang = 'en';
        }
        return apply_filters( 'wpml_object_id', $place_id, 'place', false, $lang );
    }

    private function getFavorites($user_id){
        $favorites = get_user_meta($user_id,'favorite_places',true);
        $favorites = array_filter($favorites);

        if(empty($favorites)){return;}
        $new = [];
        foreach($favorites as $place_id){
            if(get_post($place_id) != null){
                $other_place_id = $this->getTranslatedID($place_id);
                $new[] = $place_id;
                if(intval($other_place_id) !== 0 && !in_array($other_place_id,$favorites)){
                    $new[] = $other_place_id;
                }
            }
        }
        return array_unique($new);
    }
    

    public function restFavoritePlaces($args, $request){
        if(isset($request['favorite'])){
            if(is_user_logged_in()){
                $user_id = intval(get_current_user_id());
                $favorites = $this->getFavorites($user_id);
            }
            if(empty($favorites)){
                $favorites = array(0);
            }
            
            $args['post__in'] = $favorites;
            // $args['suppress_filters'] = 1;
        }
        return $args;
    }

    public function myPlaces($args, $request){
        if (empty($request['myplaces']) && !is_user_logged_in()) {
            return $args;
        }
        $user_id = get_current_user_id();
        $args['author'] = $user_id;
        return $args;
    }


    public function restPlaceIds($args, $request){
        if (empty($request['place_ids']) || !is_array($request['place_ids'])) {
            return $args;
        }
        $args['post__in'] = $request['place_ids'];
        return $args;
    }


    public function restPlaceType($args, $request){
        if (empty($request['place_type']) || intval($request['place_type']) === 0) {
            return $args;
        }
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'place-type',
                'field'    => 'id',
                'terms'    => intval($request['place_type']),
            ),
        );

        return $args;
    }

    public function createPlaceLocation(){
        $this->createEndpoint('place_location','GET',array($this,'placeLocationCallback'));
    }

    public function placeLocationCallback(){
        if(!isset($_GET['id']) || get_post_type($_GET['id']) !== 'place'){
            return $this->returnError('place_not_found','Place is invalid.');
        }
        if(!isset($_GET['lat']) || !isset($_GET['lng'])){
            return $this->returnError('missing_parameters','Lat or lng is not found.');
        }
        $address = get_field('address',$_GET['id']);
        return $this->getRouteUrl($address);
    }


    public function createPlacesTypes(){
        $this->createEndpoint('place_types','GET',array($this,'placesTypesCallback'));
    }



    public function placesTypesCallback(){
        $lang = 'de';
        if(ICL_LANGUAGE_CODE === 'de'){
            $lang = 'en';
        }
        $terms = get_terms('place-type',array('hide_empty'=>false));
        if(empty($terms)){return;}
        $arr = [];
        foreach($terms as $term){
            $term_id = $term->term_id;
            $translated_id = apply_filters( 'wpml_object_id', $term_id, 'place-type', false,$lang);

            $arr[] = array(
                'id'=>$term_id,
                'name'=>$term->name,
                'slug'=>$term->slug,
                'active_icon'=>get_field('active_icon',$term),
                'in_active_icon'=>get_field('in_active_icon',$term),
                'translated_id'=>$translated_id,
            );
        }
        return $arr;
    }


    public function removeAdditionalData( $data, $post, $request ) {
        $_data = $data->data;
        $params = $request->get_params();
        if (isset($params['core'])){
            $unset_keys = array(
                'date',
                'date_gmt',
                'guid',
                'modified',
                'modified_gmt',
                'comment_status',
                'featured_media',
                'template',
                'ping_status',
                '_links'
            );
            foreach($unset_keys as $key){
                unset($_data[$key]);
            }
            foreach($data->get_links() as $_linkKey => $_linkVal) {
                $data->remove_link($_linkKey);
            }
        }
        
        $data->data = $_data;
        return $data;
    }


    function addACFData(){
        $fields = array(
            'size',
            'address',
            'route_url',
            'email',
            'zipcode',
            'website',
            'phone_number',
            'description',
            'images',
            'hr_from',
            'hr_to',
            'place_type',
            'category',
            'delivery',
            'payment_methods',
        );

        foreach($fields as $field){
            $this->add_rest_field('place',$field,
                function($data,$field =''){
                    if($field === 'images'){
                        return $this->parseImages(get_field('images',$data['id']));
                    }
                    if($field === 'place_type'){
                        return get_field('type',$data['id']);
                    }
                    if($field === 'features'){
                        return $this->parseFeatures(get_field('features',$data['id']));
                    }
                    if($field === 'route_url'){
                        return $this->getRouteUrl(get_field('address',$data['id']));
                    }
                    if($field === 'is_favorited'){
                        return $this->parseFavorite($data['id']);
                    }
                    return get_field($field,$data['id']);
                }
            );
        }
    }

    private function parseFavorite($post_id){
        if(!is_user_logged_in()){
            return false;
        }
        $user_id = intval(get_current_user_id());
        $favorites = $this->getFavorites($user_id);
        if(empty($favorites) || !is_array($favorites)){
            return false;
        }
        if(in_array($post_id,$favorites)){
            return true;
        }
        return false;
    }

    private function getRouteUrl($address){
        if(!is_array($address)){
            return;
        }
        if(!array_key_exists('lat',$address) && !array_key_exists('lng',$address)){
            return;
        }
        $lat = $address['lat'];
        $lng = $address['lng'];
        if($lat == '' || $lng == ''){
            return 'Address is not defined.';
        }
        if(isset($_GET['lat']) && isset($_GET['lng'])){
            if($_GET['lat'] == '' || $_GET['lng'] == ''){
                return 'User location was not sent.';
            }
            return 'https://www.google.com/maps/dir/?api=1&origin='.$_GET['lat'].','.$_GET['lng'].'&destination='.$lat.','.$lng.'';
        }
    }

    public function addFeatruedImage(){
        $this->add_rest_field('place','featured_image',
            function($data){
                return get_the_post_thumbnail_url( $data['id']);
            }
        );
    }

    public function addRelatedPlaces(){
        $this->add_rest_field('place','related_places',
            function($data){
                $post_id = $data['id'];
                $type = get_field('type',$post_id);
                if(empty($terms)){return;}
                $term = $terms[0];
                $args = array(
                    'post_type'=>'place',
                    'post_status'=>'publish',
                    'fields'=>'ids',
                    'post__not_in'=>array($post_id),
                    'meta_query' => array(
                        array(
                            'key' => 'type',
                            'value'    => $type,
                            'compare'    => 'LIKE',
                        ),
                    ),
                );
                $query = new WP_Query( $args );
                return $query->posts;
            }
        );
    }




    public function addAvgRating(){
        $this->add_rest_field('place','avg_rating',
            function($data){
                $avg_rating = get_post_meta($data['id'],'avg_rating',true);
                if($avg_rating == ''){
                    $avg_rating = $this->getAvgRating($data['id']);
                }
                return intval($avg_rating);
            }
        );
    }

    public function addCommentsNumberwithRating(){
        $this->add_rest_field('place','comments_number_rated',
            function($data){
                $args = array(
                    'post_id'=>$data['id'],
                    'meta_query' => array(
                        array(
                         'key' => 'rating',
                         'compare' => 'EXISTS' 
                        ),
                    )
                );
                $comments = get_comments($args);
                return intval(count($comments));
            }
        );
    }

    public function addCommentsNumber(){
        $this->add_rest_field('place','comments_number',
            function($data){
                return intval(get_comments_number($data['id']));
            }
        );
    }


    public function addPlacesNumber(){
        $this->add_rest_field('place','total_places',
            function($data){
                $args = array(
                    'post_type'=>'place',
                    'post_status'=>'publish',
                    'posts_per_page'=>-1,
                    'fields'=>'ids',
                );
                if(isset($_GET['place_type']) && intval($_GET['place_type']) !== 0){
                    $place_type = intval($_GET['place_type']);
                    if($place_type !== 0){
                        $args['tax_query'] = array(
                            array(
                            'taxonomy' => 'place-type',
                            'field'    => 'id',
                            'terms'    => $place_type,
                            ),
                        );
                    }
                }
                $query = new WP_Query($args);
                return intval($query->found_posts);
            }
        );
    }

    public function addPlaceType(){
        $this->add_rest_field('place','place_type',
            function($data){
                $terms = get_the_terms($data['id'],'place-type');
                if(empty($terms)){return;}
                $arr = [];
                foreach($terms as $term){
                    $arr[] = array(
                        'id'=>$term->term_id,
                        'name'=>$term->name,
                        'slug'=>$term->slug,
                        'active_icon'=>get_field('active_icon',$term),
                        'in_active_icon'=>get_field('in_active_icon',$term),
                    );
                }
                return $arr;
            }
        );
    }


    private function parseImages($images){
        if(empty($images)){return;}
        $new = [];
        foreach($images as $image){
            if(isset($image['url'])){
                $new[] = $image['url'];
            }else{
                $new[] = wp_get_attachment_image_url($image);
            }
        }
        return $new;
    }

    private function parseFeatures($features){
        if(empty($features)){return;}
        $new = [];
        $feat_icons = get_field('places_features_icons','option');
        foreach($features as $feat){
            $id = $this->searchForIconId($feat, $feat_icons);
            $new[] = array(
                'name'=>$feat,
                'icon'=>$feat_icons[$id]['icon'],
            );
        }
        return $new;
    }

    private function searchForIconId($id, $array) {
        if(!is_array($array)){
            return;
        }
        foreach ($array as $key => $val) {
           if ($val['feature'] === $id) {
               return $key;
           }
        }
        return null;
    }

    


}

new OutdoorfPlace();