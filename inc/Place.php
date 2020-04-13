<?php
class OutdoorfPlace extends OutdoorfMain{

    public function __construct(){
        $this->registerHooks();
    }

    public function registerHooks(){
        add_filter('rest_prepare_place', array($this,'removeAdditionalData'), 10, 3 );
        add_action('rest_api_init',array($this,'addFeatruedImage'));
        add_action('rest_api_init',array($this,'addACFData'));
        add_action('rest_api_init',array($this,'addRelatedPlaces'));
        add_action('rest_api_init',array($this,'createPlaceLocation'));
        add_action('rest_api_init',array($this,'createAddPlace'));
        add_action('rest_api_init',array($this,'createEditPlace'));
        add_filter('rest_place_query', array($this,'restPlaceIds'), 10, 2);
        add_filter('rest_place_query', array($this,'myPlaces'), 10, 2);
        add_filter('rest_place_query', array($this,'restPlaceSearch'), 99, 2);
        add_action('rest_api_init',array($this,'testEndpoint'));
    }

    public function testEndpoint(){
        $this->createEndpoint('test_endpoint','GET',array($this,'testEndpointCallback'));
    }

    public function testEndpointCallback(){
        return $this->convertKeysToValues('type',0);
    }



    public function restPlaceSearch($args, $request){
        if (empty($request['place_search'])) {
            return $args;
        }

        $args['s'] = $request['place_search'];


        $query = new WP_Query($args);
        if(!$query->have_posts()){
            if($query->found_posts === 0){
                $args['s'] = '';
                $args['tax_query'] = '';
                $meta_query[] =
                    array(
                        'key'     => 'category',
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
        if($type == ''){
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
        
        if($_POST['edit_image'] == true){
            update_post_meta($place_id,'images',array());
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
        if($type == ''){
            return $this->returnError('add_place_rest','Type is required.');
        }

        // Insert place
        $place_id = wp_insert_post(array(
            'post_title'=>sanitize_text_field($_POST['name']),
            'post_type'=>'place',
            'post_status'=>'publish',
            'post_author'=>$user_id,
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
                if(intval($feat_attach_id) === 0){
                    $first = array_shift($all_ids);
                    set_post_thumbnail($place_id,$first);
                }
                update_post_meta($place_id,'images',$all_ids);
            }
        }


        return true;
        
    }


    public function getOriginalWord($word){
        $word = html_entity_decode($word);
		global $wpdb;
		$results = $wpdb->get_results( "SELECT string_id FROM " . $wpdb->prefix . "icl_string_translations where value = '{$word}'" );
		if(empty($results)){return $word;}
		$string_id = $results[0]->string_id;
		$results = $wpdb->get_results( "SELECT value FROM " . $wpdb->prefix . "icl_strings where id = {$string_id}" );
		if(empty($results)){return $word;}
		$value = $results[0]->value;
		return $value; 
    }

    public function getOriginalTranslation($value){
        if(is_array($value)){
            $new = [];
            if(empty($value)){return $new;}
            foreach($value as $val){
                $new[] = $this->getOriginalWord($val);
            }
            return $new;
        }else{
            return $this->getOriginalWord($value);
        }
    }


    private function updateFieldsAddPlace($place_id){
        $fields = array(
            'zipcode'=>'number',
            'email'=>'email',
            'website'=> 'text',
            'phone_number'=> 'text',
            'description'=> 'textarea',
            'hr_from'=> 'text',
            'hr_to'=> 'text',
            'type'=> 'text',
            'category'=>'array',
            'delivery'=> 'array',
            'payment_methods'=> 'array',
        );
        $imp_fields = array('type','category','delivery','payment_methods');
        foreach($fields as $name => $type){
            if(in_array($name,$imp_fields)){
                update_post_meta($place_id,$name,$this->getOriginalTranslation($_POST[$name]));
            }else{
                if($type === 'text' && isset($_POST[$name])){
                    update_post_meta($place_id,$name,sanitize_text_field($_POST[$name]));
                }elseif($type === 'number' && isset($_POST[$name])){
                    if($_POST[$name] != ''){
                        update_post_meta($place_id,$name,intval($_POST[$name]));
                    }
                }elseif($type === 'email' && isset($_POST[$name])){
                    if($_POST[$name] != ''){
                        update_post_meta($place_id,'email_address',sanitize_email($_POST[$name]));
                    }
                }elseif($type === 'array' && isset($_POST[$name])){
                    if(!empty($_POST[$name])){
                        $new_arr = [];
                        foreach($_POST[$name] as $key => $value){
                            $new_arr[$key] = sanitize_text_field($value);
                        }
                        update_post_meta($place_id,$name,$new_arr);
                    }
                }elseif($type === 'textarea' && isset($_POST[$name])){
                    if($_POST[$name] != ''){
                        update_post_meta($place_id,$name,$_POST[$name]);
                    }
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

    public function parseAddress($address){
        if(!empty($address)){
            if(!isset($address['address'])){
                $address = array(
                    'address'=>$address[0],
                    'lat'=>$address[1],
                    'lng'=>$address[2],
                );
            }
        }
        return $address;
    }

    public function parseArrayChecked($array){
        $new = [];
        if(!empty($array)){
            foreach($array as $val){
                $new[] = array(
                    'name'=>$val,
                    'checked'=>true,
                );
            }
        }
        return $new;
    }

    public function addACFData(){
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
                    if($field === 'category' || $field === 'delivery' || $field === 'payment_methods'){
                        return $this->getKeysFromValue($field,$this->getTranslation(get_field($field,$data['id'])));
                    }
                    if($field === 'address'){
                        return $this->parseAddress(get_field('address',$data['id']));
                    }
                    if($field === 'email'){
                        return $this->parseEmail($data['id']);
                    }
                    if($field === 'images'){
                        return $this->parseImages(get_field('images',$data['id']));
                    }
                    if($field === 'place_type'){
                        return $this->getKeysFromValue('type',$this->getTranslation(get_field('type',$data['id'])));
                    }
                    if($field === 'route_url'){
                        return $this->getRouteUrl(get_field('address',$data['id']));
                    }
                    if($field === 'hr_to' || $field === 'hr_from'){
                        return $this->parseDate(get_field($field,$data['id']));
                    }
                    return $this->getTranslation(get_field($field,$data['id']));
                }
            );
        }
    }

    public function parseEmail($id){
        if(get_post_meta($id,'email',true) != ''){
            return get_post_meta($id,'email',true);
        }else{
            return get_post_meta($id,'email_address',true);
        }
    }

    public function parseDate($field){
        return date('h:i a', strtotime($field));
    }
    
    public function getKeysFromValue($name,$val){
        if($val == ''){
            if($name == 'type'){return;}else{return [];}
        }

        if(isset($_GET['lang']) && $_GET['lang'] != ''){
            global $sitepress;
            $sitepress->switch_lang($_GET['lang']);
        }else{
            global $sitepress;
            $sitepress->switch_lang('de');
        }
        $keys = array(
            'type'=>'field_5e8489733e50a',
            'category'=>'field_5d3acd4b34748',
            'delivery'=>'field_5e74b55535b82',
            'payment_methods'=>'field_5e74b58435b83',
        );
        $field = get_field_object($keys[$name])['choices'];
        $array = array_values($field);

        if(is_array($val)){
            $new = [];
            if(empty($val)){return $new;}
            foreach($val as $v){
                $k = array_search(html_entity_decode($v),$array);
                $new[] = array(
                    'key'=>$k,
                    'name'=>$v,
                    'checked'=>true,
                );
            }
            return $new;
        }else{
            $k = array_search(html_entity_decode($val),$array);
            return array(
                'key'=>$k,
                'name'=>$val,
                'checked'=>true,
            );
        }
    }
    

    public function getTranslation($value){
        if(is_array($value)){
            $new = [];
            foreach($value as $val){
                $new[] = translate(html_entity_decode($val),'belocalhero');
            }
            return $new;
        }else{
            return translate(html_entity_decode($value),'belocalhero');
        }
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
                return array(
                    'name'=>get_the_title(get_post_thumbnail_id($data['id'])),
                    'url'=>get_the_post_thumbnail_url( $data['id']),
                );
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



    private function parseImages($images){
        if(empty($images)){return array();}
        $new = [];
        foreach($images as $image){
            if(isset($image['url'])){
                $image = attachment_url_to_postid($image['url']);
                $new[] = array(
                    'name'=>get_the_title($image),
                    'url'=>wp_get_attachment_image_url($image),
                );
            }else{
                $new[] = array(
                    'name'=>get_the_title($image),
                    'url'=>wp_get_attachment_image_url($image),
                );
            }
        }
        return $new;
    }



}

new OutdoorfPlace();