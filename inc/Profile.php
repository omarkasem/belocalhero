<?php
class OutdoorfProfile extends OutdoorfMain{

    public function __construct(){
        parent::__construct();
        $this->registerHooks();
        $this->activateErrors();
    }

    private function registerHooks(){
        add_action('rest_api_init',array($this,'createProfile'));
        add_action('rest_api_init',array($this,'editProfile'));
        add_action('rest_api_init',array($this,'changePassword'));
        add_action('rest_api_init',array($this,'deleteAccount'));
        add_action('rest_api_init',array($this,'changeImage'));
    }


    public function changeImage(){
        $this->createEndpoint('change_image','POST',array($this,'changeImageCallback'));
    }

    public function changeImageCallback(){
        if(!is_user_logged_in()){
            return $this->returnError('rest_view_profile','Sorry, you must be logged in to view profile.',411);
        }
        $user_id = intval(get_current_user_id());

        if($_POST['profile_image'] == '' || $_POST['profile_image_name'] == ''){
            return $this->returnError('rest_view_profile','Profile image is required.');
        }
        $attach_id = $this->uploadImage($_POST['profile_image'],$_POST['profile_image_name']);
        update_field('field_5d1aa4c852b59',$attach_id,'user_'.$user_id);
        return true;
    }


    public function createProfile(){
        $this->createEndpoint('profile','POST',array($this,'profileCallback'));
    }


    public function profileCallback(){
        if(!is_user_logged_in()){
            return $this->returnError('rest_view_profile','Sorry, you must be logged in to view profile.',411);
        }
        $user_id = intval(get_current_user_id());
        $user = get_user_by('id',$user_id);
        return $this->getUserData($user);
    }


    // Edit
    public function editProfile(){
        $this->createEndpoint('edit_profile','POST',array($this,'editProfileCallback'));
    }

    private function editProfileValidation(){
        $user_data = array();

        if(isset($_POST['name']) && $_POST['name'] != ''){
            if(username_exists( $_POST['name'] )){
                $this->addError('invalid_field','Username exists, please choose another one.');
            }
            $user_data['user_login'] = sanitize_text_field($_POST['name']);
            $user_data['user_nicename'] = sanitize_text_field($_POST['name']);
            $user_data['display_name'] = sanitize_text_field($_POST['name']);
            $user_data['nickname'] = sanitize_text_field($_POST['name']);
            
             
        }


        if(isset($_POST['email']) && $_POST['email'] != ''){
            if(email_exists( $_POST['email'] )){
                $this->addError('invalid_field','Email exists, please choose another one.');
            }

            if(!is_email( $_POST['email'] )){
                $this->addError('invalid_field','Email is invalid.');
            }
            $user_data['user_email'] = sanitize_email($_POST['email']);
        }

        if(isset($_POST['address']) && $_POST['address'] != ''){
            $user_data['address'] = sanitize_text_field($_POST['address']);
        }

        if(isset($_POST['country']) && $_POST['country'] != ''){
            $user_data['country'] = sanitize_text_field($_POST['country']);
        }

        if(isset($_POST['city']) && $_POST['city'] != ''){
            $user_data['city'] = sanitize_text_field($_POST['city']);
        }

        return array('errors'=> $this->getErrors(),'data'=>$user_data);

    }

    public function editProfileCallback(){
        if(!is_user_logged_in()){
            return $this->returnError('rest_edit_profile','Sorry, you must be logged in to edit profile.',411);
        }
        $user_id = intval(get_current_user_id());

        $validation = $this->editProfileValidation();
        $validation_errors = $validation['errors'];

        if(is_wp_error( $validation_errors ) && ! empty( $validation_errors->errors )){
            return $validation_errors;
        }

        $user_data = $validation['data'];
        $user_data['ID'] = $user_id;
        $this->updateFields($user_data,$user_id);
        wp_update_user($user_data);
        // Change user_login
        if(array_key_exists('user_login',$user_data)){
            global $wpdb;
            $wpdb->update($wpdb->users, array('user_login' => $user_data['user_login']), array('ID' => $user_id));
            $user = get_user_by('id',$user_id);
            return $this->getUserData($user,$user_data['user_login']);
        }
        
        
        $user = get_user_by('id',$user_id);
        return $this->getUserData($user);
    }

    private function updateFields($data,$user_id){
        $fields = array('address','country','city');
        foreach($fields as $field){
            if(array_key_exists($field, $data)){
                update_user_meta($user_id,$field,$data[$field]);
            }
        }
    }



    // Password
    public function changePassword(){
        $this->createEndpoint('change_password','POST',array($this,'changePasswordCallback'));
    }

    public function changePasswordCallback(){
        if(!is_user_logged_in()){
            return $this->returnError('rest_change_password','Sorry, you must be logged in to change password.',411);
        }
        $user_id = intval(get_current_user_id());
        $user = get_user_by('id',$user_id);

        if(isset($_POST['old_password']) == ''){
            return $this->returnError('empty_fields','Old password is required.');
        }
        if(isset($_POST['new_password']) == ''){
            return $this->returnError('empty_fields','New password is required.');
        }

        if(strlen($_POST['new_password']) < 6){
            return $this->returnError('invalid_field','Password minimum length is 6 charachters.');
        }

        $try_login = wp_signon(
            array(
                'user_login'=> $user->user_login,
                'user_password'=>$_POST['old_password'],
            )
        );

        if(is_wp_error( $try_login )){
            return $this->returnError('invalid_field','Password is wrong.');
        }
        
        $new = wp_set_password($_POST['new_password'],$user_id);
        return true;
    }


    // Delete Account
    public function deleteAccount(){
        $this->createEndpoint('delete_account','POST',array($this,'deleteAccountCallback'));
    }

    public function deleteAccountCallback(){
        if(!is_user_logged_in()){
            return $this->returnError('rest_delete_account','Sorry, you must be logged in to delete account.',411);
        }
        $user_id = intval(get_current_user_id());
        require_once(ABSPATH.'wp-admin/includes/user.php');
        return wp_delete_user( $user_id, false);
    }


}

new OutdoorfProfile();