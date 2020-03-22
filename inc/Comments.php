<?php
class OutdoorfComments extends OutdoorfMain{

    public function __construct(){
        parent::__construct();
        $this->registerHooks();
    }

    public function registerHooks(){
         add_filter('rest_prepare_comment', array($this,'removeAdditionalData'), 10, 3 );
         add_action('rest_api_init',array($this,'addCommentContent'));
         add_action('rest_api_init',array($this,'addCommentRating'));
         add_action('rest_api_init',array($this,'addCommentLikes'));
         add_action('rest_api_init',array($this,'addTotalComments'));
         add_action('rest_api_init',array($this,'addPlaceDetails'));
         add_action('rest_api_init',array($this,'addUserDetails'));
         add_action('wp_insert_comment',array($this,'addRating'),10,2);
         add_action('edit_comment',array($this,'editRating'),10);
         add_action('rest_api_init',array($this,'likeComment'));
         add_action('rest_api_init',array($this,'editComment'));
         add_action('rest_api_init',array($this,'deleteComment'));
         add_filter('rest_comment_query', array($this,'restCommentAuthor'), 10, 2);
         add_action('rest_api_init',array($this,'addChildsNumber'));
    }


    public function addChildsNumber(){
        $this->add_rest_field('comment','child_number',
            function($data){
                if($data['parent'] === 0){
                    $comments = get_comments(
                        array(
                            'parent'=>$data['id'],
                            'fields'=>'ids',
                        )
                    );
                    return count($comments);
                }
            }
        );
    }

    public function addTotalComments(){
        $this->add_rest_field('comment','total_comments',
            function($data){
                if($data['post'] !== ''){
                    return intval(get_comments_number($data['post']));
                }
                return 0;
            }
        );
    }

    public function deleteComment(){
        $this->createEndpoint('delete_comment','POST',array($this,'deleteCommentCallback'));
    }


    public function deleteCommentCallback(){
        // Validation
        if(!is_user_logged_in()){
            return $this->returnError('rest_like_comment','Sorry, you must be logged in to edit a comment.',411);
        }

        $user_id = intval(get_current_user_id());
        if(!isset($_POST['comment_id']) || intval($_POST['comment_id']) === 0){
            return $this->returnError('rest_like_comment','Comment ID is required.');
        }
        $comment_id = intval($_POST['comment_id']);
        wp_delete_comment($comment_id,true);
        return true;
    }

    public function editComment(){
        $this->createEndpoint('edit_comment','POST',array($this,'editCommentCallback'));
    }

    public function editCommentCallback(){
        // Validation
        if(!is_user_logged_in()){
            return $this->returnError('rest_like_comment','Sorry, you must be logged in to edit a comment.',411);
        }

        $user_id = intval(get_current_user_id());
        if(!isset($_POST['comment_id']) || intval($_POST['comment_id']) === 0){
            return $this->returnError('rest_like_comment','Comment ID is required.');
        }
        $comment = get_comment($_POST['comment_id']);
        if($comment == null){
            return $this->returnError('rest_like_comment','Comment is invalid.');
        }
        $author_id = intval($comment->user_id);
        if($user_id !== $author_id){
            return $this->returnError('rest_like_comment','User can only edit his comments.');
        }

        $content = $comment->comment_content;
        if(isset($_POST['content']) && $_POST['content'] != ''){
            $content = sanitize_text_field( $_POST['content'] );
        }
        wp_update_comment( array(
            'comment_ID'=>$_POST['comment_id'],
            'comment_content'=>$content,
        ));

        $endpoint = rest_url("wp/v2/comments/".$_POST['comment_id']).'?token='.$_POST['token'].'&token_type='.$_POST['token_type'];
        return json_decode(wp_remote_get($endpoint)['body']);
    }


    public function addUserDetails(){
        $this->add_rest_field('comment','user_details',
            function($data){
                $user_id = intval($data['author']);
                if($user_id === 0){return;}
                return array(
                    'image'=>$this->getUserAvatar($user_id),
                );
            }
        );
    }


    public function restCommentAuthor($args, $request){
        if (empty($request['author_id']) || intval($request['author_id']) === 0) {
            return $args;
        }
        $args['author__in'] = array(intval($request['author_id']));
        return $args;
    }


    public function likeComment(){
        $this->createEndpoint('like_comment','POST',array($this,'likeCommentCallback'));
    }

    public function likeCommentCallback(){
        // Validation
        if(!is_user_logged_in()){
            return $this->returnError('rest_like_comment','Sorry, you must be logged in to like a comment.',411);
        }

        $user_id = intval(get_current_user_id());
        
        if(!isset($_POST['comment_id']) || intval($_POST['comment_id']) === 0){
            return $this->returnError('rest_like_comment','Comment ID is required.');
        }
        $comment_id = intval($_POST['comment_id']);

        // Like & Dislike Comments
        $liked_comments = get_user_meta($user_id,'liked_comments',true);
        $comment_likes = intval(get_comment_meta($comment_id,'comment_likes',true));
        if(empty($liked_comments)){
            $liked_comments = [];
        }

        // Like
        if(!in_array($comment_id,$liked_comments)){
            $liked_comments[] = $comment_id;
            $comment_likes++;
        }
        // Dislike
        else{
            if(($key = array_search($comment_id, $liked_comments)) !== false) {
                unset($liked_comments[$key]);
            }
            $comment_likes--;
        }
        update_user_meta($user_id,'liked_comments',$liked_comments);
        update_comment_meta($comment_id,'comment_likes',$comment_likes);


        $endpoint = rest_url("wp/v2/comments/".$comment_id).'?token='.$_POST['token'].'&token_type='.$_POST['token_type'];
        return json_decode(wp_remote_get($endpoint)['body']);
    }



    public function addRating($comment_id, $comment_object) {
        if(intval($comment_object->comment_parent) === 0){
            if(isset($_POST['rating']) && intval($_POST['rating']) !== 0 && intval($_POST['rating']) <= 5){
                update_comment_meta($comment_id,'rating',intval($_POST['rating']));
                $this->getAvgRating($comment_object->comment_post_ID);
            }
        }
    }


    public function editRating($comment_id) {
        if(isset($_POST['rating']) && intval($_POST['rating']) !== 0 && intval($_POST['rating']) <= 5){
            update_comment_meta($comment_id,'rating',intval($_POST['rating']));
            $this->getAvgRating($comment_object->comment_post_ID);
        }
    }


    public function addPlaceDetails(){
        $this->add_rest_field('comment','place_details',
            function($data){
                $place_id = intval($data['post']);
                if($place_id === 0){return;}
                $slug = get_post_field( 'post_name', $place_id );
                return array(
                    'id'=>$place_id,
                    'slug'=>$slug,
                    'title'=>get_the_title($place_id),
                    'image'=>get_the_post_thumbnail_url($place_id),
                    'price_from'=>get_field('price_range_from',$place_id),
                    'price_to'=>get_field('price_range_to',$place_id),
                );
            }
        );

    }


    public function addCommentLikes(){
        $this->add_rest_field('comment','liked_by_user',
            function($data){
                $comment_id = $data['id'];
                if(is_user_logged_in()){
                    $user_id = intval(get_current_user_id());
                    $liked_comments = get_user_meta($user_id,'liked_comments',true);
                    if(empty($liked_comments)){
                        $liked_comments = [];
                    }
                    if(in_array($comment_id,$liked_comments)){
                        return true;
                    }
                }
                return false;
            }
        );


        $this->add_rest_field('comment','likes_number',
            function($data){
                $comment_id = $data['id'];
                return intval(get_comment_meta($comment_id,'comment_likes',true));
            }
        );

    }

    public function addCommentContent(){
        $this->add_rest_field('comment','comment_content',
            function($data){
                return $data['content']['raw'];
            }
        );
    }

    public function addCommentRating(){
        $this->add_rest_field('comment','rating',
            function($data){
                $comment_id = $data['id'];
                $rating = intval(get_comment_meta($comment_id,'rating',true));
                if($rating === 0){
                    return null;
                }else{
                    return $rating;
                }
            }
        );
    }


    public function removeAdditionalData( $data, $post, $request ) {
        $_data = $data->data;
        $params = $request->get_params();
        if (isset($params['core'])){
            $unset_keys = array(
                'link',
                'author_avatar_urls',
                'meta',
                'author_url',
                '_links',
                'content',
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

    


}

new OutdoorfComments();