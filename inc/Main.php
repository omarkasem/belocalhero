<?php
use \Firebase\JWT\JWT; 
abstract class OutdoorfMain{

    private $textDomain = 'belocalhero';
    private $errors;

    public function __construct(){
        add_filter( 'wp_rest_cache/allowed_endpoints', array($this,'addEndpointsToCache'), 10, 1);
    }

    public function verifyJWTToken($token){
        $url = get_rest_url().'jwt-auth/v1/token/validate';
        $args = array(
            'method' => 'POST',
            'headers'=>array(
                'Authorization'=>'Bearer '.$token,
            ),
        );
        $response = wp_remote_post( $url, $args );
        if(is_wp_error($response)){
            return $response;
        }
        $response = json_decode($response['body']);
        if($response->data->status === 200){
            $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
            $token = JWT::decode($token, $secret_key, array('HS256'));
            return $token->data->user->id;
        }else{
            return $response;
        }
    }

    public function getAvgRating($place_id){
        $comments = get_comments(array(
            'post_id'=>$place_id,
            'fields'=>'ids',
            'parent'=>0,
        ));
        if(empty($comments)){return;}
        $ratings = [];
        foreach($comments as $comment_id){
            $rating = intval(get_comment_meta($comment_id,'rating',true));
            if($rating !== 0){
                $ratings[] = $rating;
            }
        }
        if(empty($ratings)){
            return;
        }
        $total = array_sum($ratings);
        $avg = $total/count($ratings);
        $avg_rating = ceil($avg);
        update_post_meta($place_id,'avg_rating',intval($avg_rating));
        return $avg_rating;
    }





    public function getUserId($token){
        try {
            $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
            $token = \Firebase\JWT\JWT::decode($token, $secret_key, array('HS256'));
        } catch (\Exception $e) {
            return 'invalid_token';
        }
        return intval($token->data->user->id);
    }

    public function verifyGoogleToken($token){
        if($token == ''){
            return $this->returnError('empty_fields','Token is not found.');
        }

        return $this->verifyTokenFor3p('google',$token);
    }


    private function verifyFacebookToken($token){
        if($token == ''){
            return $this->returnError('empty_fields','Token is not found.');
        }

        return $this->verifyTokenFor3p('facebook',$token);
    }


    private function verifyTokenFor3p($type,$token){
        if($type == 'google'){
            $url = 'https://www.googleapis.com/oauth2/v3/tokeninfo?access_token='.$token;
        }
        
        if($type == 'facebook'){
            $url = 'https://graph.facebook.com/me?fields=email,id,name&access_token='.$token;
        }

        $results = wp_remote_get($url);
        $code = $results['response']['code'];


        if($code != 200){
            return $this->returnError('invalid_token','Token is invalid.');
        }
        $body = json_decode($results['body']);
        $email = $body->email;

        if(email_exists( $email )){
            $user = get_user_by('email',$email);
            return $user->ID;
        }
    }



    public function add_rest_field($post_type,$field_name,$callback,$callback_update='',$field_type='string'){
        register_rest_field( $post_type, $field_name, array(
            'get_callback' => $callback,
            'update_callback' => $callback_update,
            'schema' => array(
                'description' => __( $field_name ),
                'type'        => $field_type,
            ),
        ));
    }

    public function createEndpoint($path,$method,$callback){
        register_rest_route( 'outdoorf/v1', '/'.$path, array(
            'methods' => strtoupper($method),
            'callback' => $callback,
        ));
    }

    public function activateErrors(){
        $this->errors = new WP_Error;
    }

    public function getErrors(){
        return $this->errors;
    }

    public function addError($code,$message,$status=400){

        $this->errors->add(
            $code,
            __($message, $this->textDomain),
            array(
                'status' => $status,
            )
        );

    }

    public function returnError($code,$message,$status=400){

        return new WP_Error(
            $code,
            __($message, $this->textDomain),
            array(
                'status' => $status,
            )
        );

    }


    public function getUserAvatar($user_id){
        $image = get_field('profile_image','user_'.$user_id);
        $social_image = get_field('social_profile_image','user_'.$user_id);
        if($social_image != ''){
            return $social_image;
        }
        if($image == '' || is_wp_error($image)){
            return get_avatar_url($user_id);
        }
        if(intval($image) !== 0){
            return wp_get_attachment_url($image);
        }else{
            return $image;
        }
    }


    public function getUserData($user,$username=''){
        $user_id = $user->ID;
        $image = $this->getUserAvatar($user_id);
        if($username == ''){
            $username = $user->user_login;
        }

        return array(
            'id'=>$user_id,
            'name'=>$username,
            'email'=>$user->user_email,
            'address'=>get_field('address','user_'.$user_id),
            'country'=>get_field('country','user_'.$user_id),
            'city'=>get_field('city','user_'.$user_id),
            'profile_image'=>$image,
        );
    }

    public function get_page_id_by_template($TEMPLATE_NAME){
        $id = null;
        $pages = get_pages(array(
            'meta_key' => '_wp_page_template',
            'meta_value' => $TEMPLATE_NAME
        ));
        if(isset($pages[0])) {
           return $pages[0]->ID;
        }
        return $id;
    }

    function getAttachIDByURL($image_url) {
        global $wpdb;
        $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url )); 
            return $attachment[0]; 
    }
    


    public function uploadImage($base64_img,$image_name){
        if (filter_var($base64_img, FILTER_VALIDATE_URL) !== false) {
            return $this->getAttachIDByURL($base64_img);
        }
        $mime_ext = end(explode('/', (explode(';', $base64_img))[0]));
        $mime_type = 'image/'.$mime_ext;

        // Upload dir.
        $upload_dir  = wp_upload_dir();
        $upload_path = str_replace( '/', DIRECTORY_SEPARATOR, $upload_dir['path'] ) . DIRECTORY_SEPARATOR;

        $img             = str_replace( 'data:'.$mime_type.';base64,', '', $base64_img );
        $img             = str_replace( ' ', '+', $img );
        $decoded         = base64_decode( $img );
        $filename        = $image_name . '.'.$mime_ext;
        $file_type       = $mime_type;
        $hashed_filename = md5( $filename . microtime() ) . '_' . $filename;

        // Save the image in the uploads directory.
        $upload_file = file_put_contents( $upload_path . $hashed_filename, $decoded );

        $attachment = array(
            'post_mime_type' => $file_type,
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $hashed_filename ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'guid'           => $upload_dir['url'] . '/' . basename( $hashed_filename )
        );

        $attach_id = wp_insert_attachment( $attachment, $upload_dir['path'] . '/' . $hashed_filename );
        return $attach_id;
    }



    public function addEndpointsToCache ($allowed_endpoints){
        if ( ! isset( $allowed_endpoints[ 'outdoorf/v1' ]) ) {
            $allowed_endpoints[ 'outdoorf/v1' ][] = 'form_selections';
            $allowed_endpoints[ 'outdoorf/v1' ][] = 'map_search';
            $allowed_endpoints[ 'outdoorf/v1' ][] = 'thank_you';
            $allowed_endpoints[ 'outdoorf/v1' ][] = '404';
            $allowed_endpoints[ 'outdoorf/v1' ][] = 'home';
            $allowed_endpoints[ 'outdoorf/v1' ][] = 'cookies';
            $allowed_endpoints[ 'outdoorf/v1' ][] = 'slider';
            $allowed_endpoints[ 'outdoorf/v1/menus' ][] = 'header';
            $allowed_endpoints[ 'outdoorf/v1' ][] = 'footer';
        }
        return $allowed_endpoints;
    }


}