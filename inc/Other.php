<?php
class OutdoorfOther extends OutdoorfMain{

    public function __construct(){
        $this->registerHooks();
        
    }

    public function registerHooks(){
        add_action('rest_api_init',array($this,'createRegister'));
        add_action('rest_api_init',array($this,'createPolicy'));
        add_action('rest_api_init',array($this,'createLegal'));
        add_action('rest_api_init',array($this,'createIntro'));
        add_action('rest_api_init',array($this,'createThankYou'));
        add_action('rest_api_init',array($this,'create404'));
        add_action('rest_api_init',array($this,'createAbout'));
        add_action('rest_api_init',array($this,'createResetPassword'));
        add_action('rest_api_init',array($this,'createResetPassword2'));
        add_action('rest_api_init',array($this,'createMapSearch'));
        add_action('rest_api_init',array($this,'createformSelections'));
    }

    public function createLegal(){
        $this->createEndpoint('legal','GET',array($this,'legalCallback'));
    }
    
    public function legalCallback(){
        $id = $this->get_page_id_by_template('templates/page-legal.php');

        if(intval($id) ===0 || get_post($id) === null){
            return 'Page is not created';
        }
        return array(
            'title'=>get_the_title($id),
            'content'=>get_post_field('post_content', $id),
        );
    }

    public function createThankYou(){
        $this->createEndpoint('thank_you','GET',array($this,'thankYouCallback'));
    }

    public function thankYouCallback(){
        return array(
            'title'=>get_field('ty_title','option'),
            'title2'=>get_field('ty_title2','option'),
            'button'=>get_field('ty_button','option'),
        );
    }


    public function createformSelections(){
        $this->createEndpoint('form_selections','GET',array($this,'formSelectionsCallback'));
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


    public function formSelectionsCallback(){
        $all = [];
        
        $kind = get_field_object('field_5e8489733e50a');
        $key=-1;
        foreach($kind['choices'] as $val){ $key++;
            $all['type'][] = array(
                'key'=>$key,
                'name'=>$this->getTranslation($val),
                'checked'=>false,
            );
        }


        $key=-1;
        $size = get_field_object('field_5d3acd4b34748');
        foreach($size['choices'] as $val){ $key++;
            $all['category'][] = array(
                'key'=>$key,
                'name'=>$this->getTranslation($val),
                'checked'=>false,
            );
        }

        $key=-1;
        $size = get_field_object('field_5e74b55535b82');
        foreach($size['choices'] as $val){ $key++;
            $all['delivery'][] = array(
                'key'=>$key,
                'name'=>$this->getTranslation($val),
                'checked'=>false,
            );
        }

        $key=-1;
        $size = get_field_object('field_5e74b58435b83');
        foreach($size['choices'] as $val){ $key++;
            $all['payment_methods'][] = array(
                'key'=>$key,
                'name'=>$this->getTranslation($val),
                'checked'=>false,
            );
        }


        return $all;
    }


    public function createMapSearch(){
        $this->createEndpoint('map_search','GET',array($this,'mapSearchCallback'));
    }

    public function mapSearchCallback(){
        if(isset($_GET['lang']) && $_GET['lang'] === 'en'){
            global $sitepress;
            $lang='en';
            $sitepress->switch_lang($lang);
        }else{
            global $sitepress;
            $lang='de';
            $sitepress->switch_lang($lang);
        }
        $args = array(
            'post_type'=>'place',
            'posts_per_page'=>-1,
            'fields'=>'ids',
        );
        $query = new WP_Query($args);
        
        $final = [];

        if ( $query->have_posts() ) :
            while ( $query->have_posts() ) : $query->the_post();
                if(has_post_thumbnail()){
                    $image = get_the_post_thumbnail_url();
                }else{
                    $other_images = get_field('images');
                    if(intval($other_images[0]) !== 0){
                        $image = wp_get_attachment_url($other_images[0]);
                    }
                }
                $address = get_field('address');
                $type = get_field('type');
                if($this->searchIfExists($final,$address['lat'],$address['lng'])){
                    $offset = rand(0,1000)/10000000;
                    $offset2 = rand(0, 1000)/10000000;
                    $address['lat'] += $offset;
                    $address['lng'] += $offset2; 
                }
                $final[] = array(
                    'id'=>get_the_ID(),
                    'slug'=>get_post_field('post_name'),
                    'title'=>get_the_title(),
                    'address'=>$address['address'],
                    'lat'=>$address['lat'],
                    'lng'=>$address['lng'],
                    'type'=>$type,
                    'categories'=>get_field('category'),
                    'image'=>$image,
                );
            endwhile;
        endif;
        return $final;
    }

    public function searchIfExists($final,$lat,$lng){
        foreach($final as $fin){
            if($fin['lat'] === $lat && $fin['lng'] === $lng){
                return true;
            }
        }
    }

    public function createResetPassword2(){
        $this->createEndpoint('reset_password2','POST',array($this,'resetPasswordCallback2'));
    }

    public function resetPasswordCallback2(){

        if(!isset($_POST['email'])){
            return $this->returnError('rest_reset_password','Email address is required.');
        }

        if(!email_exists($_POST['email'])){
            return $this->returnError('rest_reset_password','Email address does not exist.');
        }

        if(!isset($_POST['reset_token']) || $_POST['reset_token'] == ''){
            return $this->returnError('rest_reset_password','Reset Token is required.');
        }

        $user = get_user_by('email',$_POST['email']);
        $rp_token = get_user_meta($user->ID,'rp_token',true);
        if($rp_token !== $_POST['reset_token']){
            return $this->returnError('rest_reset_password','Reset Token is invalid.');
        }

        if(!isset($_POST['new_password']) || $_POST['new_password'] == ''){
            return $this->returnError('rest_reset_password','New Password is required.');
        }

        if(strlen($_POST['new_password']) < 6){
            return $this->returnError('rest_reset_password','New Password must be 6 charachters or more.');
        }

        wp_update_user( array(
            'ID'=>$user->ID,
            'user_pass'=>$_POST['new_password'],
        ));
        return true;
    }


    public function createResetPassword(){
        $this->createEndpoint('reset_password','POST',array($this,'resetPasswordCallback'));
    }

    public function resetPasswordCallback(){
        if(!isset($_POST['email'])){
            return $this->returnError('rest_reset_password','Email address is required.');
        }

        if(!email_exists($_POST['email'])){
            return $this->returnError('rest_reset_password','Email address does not exist.');
        }

        if(!isset($_POST['reset_page']) || $_POST['reset_page'] == ''){
            return $this->returnError('rest_reset_password','Rest page url is required.');
        }


        if(filter_var($_POST['reset_page'], FILTER_VALIDATE_URL) == false){
            return $this->returnError('rest_reset_password','Rest page is invalid.');
        }

        $token = md5(uniqid($_POST['email'], true));
        $user = get_user_by('email',$_POST['email']);
        update_user_meta($user->ID,'rp_token',$token);
        $this->sendResetEmail($_POST['email'],$token,$_POST['reset_page']);
        return $token;
    }

    private function sendResetEmail($email,$token,$reset_page){
        $blog_name = get_bloginfo('name');
        $blog_email = get_bloginfo('admin_email');;
        $subject = ''.__('Reset Password for','outdoorfamily').' '.$blog_name;
        $headers[] = 'From: '.$blog_name.' <'.$blog_email.'>';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        
        $body = '<div style="background: #E6E9EE;    overflow: hidden;
    padding: 5px 20px;
    color: #000;">';
        $body .= '<h3 style="color: #000;
    FONT-WEIGHT: BOLD;
    font-size: 16px;">'.__('Reset Password for','outdoorfamily').' '.$blog_name.'</h3>';
        $body .= '<p style="    font-size: 15px;">'.__('To reset your password click on the link below','outdoorfamily').'<br>';
        $body .= '<a style="background: #E59069;
    padding: 5px 20px;
    float:left;
    margin-top: 10px;
    text-decoration: none;
    color: #fff;
    text-transform: uppercase;" href="'.$reset_page.'?email='.$email.'&token='.$token.'">'.__('Click here','outdoorfamily').'</a></p>';
        $body.= '</div>';
        wp_mail( $email, $subject, $body, $headers );
    }

    public function createAbout(){
        $this->createEndpoint('about','GET',array($this,'AboutCallback'));
    }

    public function AboutCallback(){
        $id = $this->get_page_id_by_template('templates/page-about.php');
        if(intval($id) ===0 || get_post($id) === null){
            return 'Page is not created';
        }
        return array(
            'title'=>get_the_title($id),
            'main_text'=>get_field('main_text',$id),
            'about_logos'=>get_field('left_logos',$id),
            'logos_text'=>get_field('right_text',$id),
            'partner_title'=>get_field('partner_title',$id),
            'partner_logos'=>get_field('right_logos',$id),
            'friends_title'=>get_field('friends_title',$id),
            'friends_logos'=>get_field('friends_logos',$id),
            'links'=>get_field('ab_links',$id),
        );
    }


    public function create404(){
        $this->createEndpoint('404','GET',array($this,'page404Callback'));
    }

    public function page404Callback(){
        $id = $this->get_page_id_by_template('templates/page-404.php');

        if(intval($id) ===0 || get_post($id) === null){
            return 'Page is not created';
        }
        return array(
            'title'=>get_field('title',$id),
            'image'=>get_field('image',$id),
        );
    }





    public function createIntro(){
        $this->createEndpoint('intro','GET',array($this,'createIntroCallback'));
    }

    public function createIntroCallback(){
        $id = $this->get_page_id_by_template('templates/page-intro.php');

        if(intval($id) ===0 || get_post($id) === null){
            return 'Page is not created';
        }
        return array(
            'title'=>get_the_title($id),
            'left_title'=>get_field('left_title',$id),
            'left_text'=>get_field('left_text',$id),
            'left_button'=>get_field('left_button',$id),
            'right_title'=>get_field('right_title',$id),
            'right_text'=>get_field('right_text',$id),
            'right_button'=>get_field('right_button',$id),
        );
    }





    public function createRegister(){
        $this->createEndpoint('register','GET',array($this,'registerCallback'));
    }
    
    public function registerCallback(){
        $id = $this->get_page_id_by_template('templates/page-register.php');

        if(intval($id) ===0 || get_post($id) === null){
            return 'Page is not created';
        }
        return array(
            'title'=>get_the_title($id),
            'content'=>get_post_field('post_content', $id),
        );
    }

    public function createPolicy(){
        $this->createEndpoint('policy','GET',array($this,'policyCallback'));
    }
    
    public function policyCallback(){
        $id = $this->get_page_id_by_template('templates/page-policy.php');

        if(intval($id) ===0 || get_post($id) === null){
            return 'Page is not created';
        }
        return array(
            'title'=>get_the_title($id),
            'content'=>get_post_field('post_content', $id),
        );
    }



}

new OutdoorfOther();