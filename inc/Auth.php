<?php
use \Firebase\JWT\JWT; 
class OutdoorfAuth extends OutdoorfMain{

    public function __construct(){
        $this->registerHooks();
        $this->activateErrors();
    }


    private function registerHooks(){
        add_action('rest_api_init',array($this,'createRegister'));
        add_action('rest_api_init',array($this,'createLogin'));
        add_action('rest_api_init',array($this,'loginWithGoogle'));
        add_action('rest_api_init',array($this,'loginWithFacebook'));
        add_filter('jwt_auth_token_before_dispatch',array($this,'addUserToJWT'),10,2);

    }



    /**
     * @return function callback
     */
    public function createRegister(){
        $this->createEndpoint('register','POST',array($this,'registerCallback'));
    }

    /**
     * @param  array of post request
     * @return object of wp error class
     */
    private function validateRegister($post){
        
        if(empty($post)){
            $this->addError('empty_fields','Request is empty.');
        }


        // Email
        if(!isset($post['email']) || $post['email'] == ''){
            $this->addError('missing_fields','Email is required.');
        }

        if(!is_email($post['email'])){
            $this->addError('invalid_field','Email is invalid.');
        }

        if(email_exists($post['email'])){
            $this->addError('invalid_field','Email exists, please choose another one.');
        }

        // Password
        if(!isset($post['password']) || $post['password'] == ''){
            $this->addError('missing_fields','Password is required.');
        }


        if(strlen($post['password']) < 6){
            $this->addError('invalid_field','Password minimum length is 6 charachters.');
        }
        return $this->getErrors();
    }

    /**
     * @return array of JWT Token
     */
    public function registerCallback(){
        $validation = $this->validateRegister($_POST);

        if(is_wp_error( $validation ) && ! empty( $validation->errors )){
            return $validation;
        }

        $user_id = $this->createUser();
        $user = get_user_by('id',$user_id);
        $token = $this->createToken($user);
        $data = $this->getUserData($user);

        $data = array_merge($data,$token);
        return $data;
    }


    /**
     * @return integer of user id
     */
    private function createUser(){
        $args = array(
            'user_login'=>sanitize_email($_POST['email']),
            'user_email'=>sanitize_email($_POST['email']),
            'user_pass'=>$_POST['password'],
        );
        $user_id = wp_insert_user($args);
        $this->saveUserFields($user_id);
        return $user_id;
    }


    /**
     * @param  integer of user id
     */
    private function saveUserFields($user_id){
        if(intval($user_id) !== 0){
            $fields = array(
                'address',
                'country',
                'city',
            );
            foreach($fields as $field){
                update_user_meta($user_id,$field,sanitize_text_field($_POST[$field]));
            }
        }
    }

    private function createToken( $user ) {
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        /** First thing, check the secret key if not exist return a error*/
        if (!$secret_key) {
            return new \WP_Error(
                'jwt_auth_bad_config',
                __('JWT is not configurated properly, please contact the admin', 'wp-api-jwt-auth'),
                array(
                    'status' => 403,
                )
            );
        }
        /** Valid credentials, the user exists create the according Token */
        $issuedAt = time();
        $notBefore = apply_filters('jwt_auth_not_before', $issuedAt, $issuedAt);
        $expire = apply_filters('jwt_auth_expire', $issuedAt + (DAY_IN_SECONDS * 7), $issuedAt);
        // $expire = apply_filters('jwt_auth_expire', $issuedAt + 120, $issuedAt);
        $token = array(
            'iss' => get_bloginfo('url'),
            'iat' => $issuedAt,
            'nbf' => $notBefore,
            'exp' => $expire,
            'data' => array(
                'user' => array(
                    'id' => $user->ID,
                ),
            ),
        );
        /** Let the user modify the token data before the sign. */
        $token = JWT::encode(apply_filters('jwt_auth_token_before_sign', $token, $user), $secret_key);
        return array(
            'token'=>$token,
            'token_expiration_date'=>$expire,
        );
    }


    
    public function addUserToJWT($data, $user){
        $data['user_id'] = $user->data->ID;
        return $data;
    }


    // Login

    /**
     * @return function callback
     */
    public function createLogin(){
        $this->createEndpoint('login','POST',array($this,'loginCallback'));
    }

    public function loginCallback(){
        $validation = $this->validateLogin($_POST);

        if(is_wp_error( $validation ) && ! empty( $validation->errors )){
            return $validation;
        }

        $user = $this->loginUser($_POST);
        if(is_wp_error( $user )){
            return $this->returnError('invalid_field','User or Password is wrong.');
        }

        $token = $this->createToken($user);
        $data = $this->getUserData($user);
        $data = array_merge($data,$token);
        return $data;
    }

    private function loginUser($post){
        $creds = array(
            'user_login'    => sanitize_text_field( $post['name'] ),
            'user_password' => sanitize_text_field( $post['password'] ),
            'remember'      => true
        );
     
        $user = wp_signon( $creds, false );
        return $user;
    }

    function validateLogin($post){
        if(empty($post)){
            $this->addError('empty_fields','Request is empty.');
        }

        // Name
        if(!isset($post['name']) || $post['name'] == ''){
            $this->addError('missing_fields','User Login is required.');
        }

        // Password
        if(!isset($post['password']) || $post['password'] == ''){
            $this->addError('missing_fields','User Password is required.');
        }

        return $this->getErrors();

    }

    private function saveUserData($user,$type){
        if($type == 'google'){
            update_user_meta($user->ID,'_google_user_id',$data->id);
            update_user_meta($user->ID,'_google_token',$data->authToken);
        }else{
            update_user_meta($user->ID,'_facebook_user_id',$data->id);
            update_user_meta($user->ID,'_facebook_token',$data->authToken);
        }
    }

    private function tryLoginWithSocial($data,$type){

        // User exist > login
        if(email_exists( $data->email )){
            $user = get_user_by('email',$data->email);
            $user_data = $this->getUserData($user);
            $this->saveUserData($user,$type);
            $token = $this->createToken($user);
            $user_data = array_merge($user_data,$token);
            return $user_data;
        }

        // User not exist > register
        $user_data = $this->registerUser($data,$type);
        $user = get_user_by('id',$user_data['id']);
        $this->saveUserData($user,$type);
        $token = $this->createToken($user);
        $user_data = array_merge($user_data,$token);
        return $user_data;
    }


    // Google
    public function loginWithGoogle(){
        $this->createEndpoint('google_login','POST',array($this,'loginWithGoogleCallback'));
    }

    public function loginWithGoogleCallback(){
        $data = json_decode(file_get_contents('php://input'));

        if($data->id == '' || $data->id == null){
            return $this->returnError('empty_fields','token is not found.');
        }

        return $this->tryLoginWithSocial($data,'google');

    }


    // Facebook
    public function loginWithFacebook(){
        $this->createEndpoint('facebook_login','POST',array($this,'loginWithFacebookCallback'));
    }

    public function loginWithFacebookCallback(){
        $data = json_decode(file_get_contents('php://input'));
        if($data->id == '' || $data->id == null){
            return $this->returnError('empty_fields','token is not found.');
        }

        return $this->tryLoginWithSocial($data,'facebook');
    }


    private function registerUser($data,$type){
        $password = wp_generate_password();
        $args = array(
            'user_login'=>$data->email,
            'fist_name'=>$data->firstName,
            'last_name'=>$data->lastName,
            'user_email'=>$data->email,
            'display_name'=>$data->name,
            'user_pass'=>$password,
        );
        $user_id = wp_insert_user($args);
        if(is_wp_error($user_id)){
            return $user_id;
        }
        $attach_id = $this->uploadUserImage($data->photoUrl);
        update_user_meta($user_id,'profile_image',$attach_id);
        update_user_meta($user_id,$type.'_token',$data);
        $user = get_user_by('id',$user_id);
        $user_data = $this->getUserData($user);

        // Send email notification with password and reset
        $this->sendResetPassword($user_data,$password);

        return $user_data;
    }

    public function sendResetPassword($user,$password){
        $blog_name = get_bloginfo('name');
        $subject = ''.__('Welcome to','outdoorfamily').' '.$blog_name;
        $body = '<h4>'.__('Welcome to','outdoorfamily').' '.$blog_name.'</h4>';
        $body .= '<p>
            '.__('Your new password is','outdoorfamily').' <b>'.$password.'</b> <br>
            '.__('To reset the password','outdoorfamily').' <a href="'.get_field('frontend_website_url','option').'profile?third_party_reset">'.__('Click here','outdoorfamily').'</a>
        </p>';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail( $user['email'], $subject, $body, $headers );
    }


    // Helpers
    private function uploadUserImage($url){
        if ( !function_exists('media_handle_upload') ) {
            require_once(ABSPATH . "wp-admin" . '/includes/image.php');
            require_once(ABSPATH . "wp-admin" . '/includes/file.php');
            require_once(ABSPATH . "wp-admin" . '/includes/media.php');
        }

        $tmp = download_url( $url );
        if( is_wp_error( $tmp ) ){
            
        }
        $file_array = array();
        preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $url, $matches);
        $file_array['name'] = basename($matches[0]);
        $file_array['tmp_name'] = $tmp;

        $id = media_handle_sideload( $file_array,0);
        return $id;
    }



}

new OutdoorfAuth();