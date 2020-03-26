<?php
class OutdoorfOther extends OutdoorfMain{

    public function __construct(){
        $this->registerHooks();
        
    }

    public function registerHooks(){
        add_action('rest_api_init',array($this,'createHeaderMenu'));
        add_action('rest_api_init',array($this,'createRegister'));
        add_action('rest_api_init',array($this,'createPolicy'));
        
        add_action('rest_api_init',array($this,'createSlider'));
        add_action('rest_api_init',array($this,'createCookies'));
        add_action('rest_api_init',array($this,'createIntro'));
        add_action('rest_api_init',array($this,'create404'));
        add_action('rest_api_init',array($this,'createAbout'));
        add_action('rest_api_init',array($this,'createResetPassword'));
        add_action('rest_api_init',array($this,'createResetPassword2'));
        add_action('rest_api_init',array($this,'createMapSearch'));
        add_action('rest_api_init',array($this,'createformSelections'));
        
        add_action('rest_api_init',array($this,'subscribeNewsletter'));

    }


    public function subscribeNewsletter(){
        $this->createEndpoint('subscribe_newsletter','POST',array($this,'subscribeNewsletterCallback'));
    }

    public function subscribeNewsletterCallback(){
        if(!isset($_POST['email'])){
            return $this->returnError('newsletter_rest','Email address is required.',411);
        }
        if(!is_email($_POST['email'])){
            return $this->returnError('newsletter_rest','Email address is invalid.');
        }

        $lang = 'de';
        if(isset($_POST['lang'])){
            $lang = $_POST['lang'];
        }

        $body = array(
                'email_address'=>$_POST['email'],
                'status'=>'pending',
                'language'=>$lang,
        );
        $args = array(
            'method' => 'POST',
            'headers'=>array(
                'Authorization'=>'Bearer '.NEWSLETTER_API_KEY,
                'Content-type' => 'application/json',
            ),
            'body'=>json_encode($body),
        );
        $response = wp_remote_post( NEWSLETTER_URL, $args );
        $response = json_decode($response['body']);
        return $response;
    }


    public function createformSelections(){
        $this->createEndpoint('form_selections','GET',array($this,'formSelectionsCallback'));
    }

    public function formSelectionsCallback(){
        $all = [];
        
        
        $kind = get_field('m_type','option');;
        foreach($kind as $val){
            $all['type'][] = array(
                'name'=>$val['name'],
                'checked'=>false,
            );
        }


        
        $size = get_field('m_category','option');;
        foreach($size as $val){
            $all['category'][] = array(
                'name'=>$val['name'],
                'checked'=>false,
            );
        }

        
        $size = get_field('m_delivery','option');;
        foreach($size as $val){
            $all['delivery'][] = array(
                'name'=>$val['name'],
                'checked'=>false,
            );
        }

        
        $size = get_field('m_payment_methods','option');;
        foreach($size as $val){
            $all['payment_methods'][] = array(
                'name'=>$val['name'],
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
            'left_text'=>get_field('left_text',$id),
            'left_logos'=>get_field('left_logos',$id),
            'right_text'=>get_field('right_text',$id),
            'right_logos'=>get_field('right_logos',$id),
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


    public function createCookies(){
        $this->createEndpoint('cookies','GET',array($this,'CookiesCallback'));
    }

    public function CookiesCallback(){
        return get_field('cookies_message','option');
    }

    public function createSlider(){
        $this->createEndpoint('slider','GET',array($this,'sliderCllback'));
    }


    public function sliderCllback(){
        $slider = get_field('slider_items','option');
        if(empty($slider)){return;}
        return $slider;
    }

    public function createHeaderMenu(){
        $this->createEndpoint('menus/header','GET',array($this,'headerMenu'));
    }

    public function headerMenu(){
        return $this->get_menu_items('header_menu');
    }

    private function get_menu_items($menu_slug) {
      $menu_items = array();

      if ( ($locations = get_nav_menu_locations()) && isset($locations[$menu_slug]) && $locations[$menu_slug] != 0 ) {
        $menu = get_term( $locations[ $menu_slug ] );
        $menu_items = wp_get_nav_menu_items($menu->term_id);
      }

      $final_items = array();
      if(!empty($menu_items)){
        foreach($menu_items as $item){
            $final_items[] = array(
                'title'=>$item->title,
                'url'=>$item->url,
                'slug'=>basename( $item->url ),
                'target'=>$item->target,
                'attr_title'=>$item->attr_title,
                'classes'=>$item->classes,
            );
        }
      }

      return $final_items;
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