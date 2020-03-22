<?php
class OutdoorfBlog extends OutdoorfMain{

    public function __construct(){
        $this->registerHooks();
    }

    public function registerHooks(){
    	add_filter('rest_prepare_post', array($this,'removeAdditionalData'), 10, 3 );
    	add_action('rest_api_init',array($this,'addBlogCat'));
    	add_action('rest_api_init',array($this,'addFeatruedImage'));
    	add_action('rest_api_init',array($this,'addTranslatedId'));
    }

	public function addTranslatedId(){
		$this->add_rest_field('post','translated_id',
			function($data){
				if(ICL_LANGUAGE_CODE == 'en'){
					$lang = 'de';
				}else{
					$lang = 'en';
				}
				return apply_filters( 'wpml_object_id', $data['id'], 'post', false, $lang );
			}
		);
	}

	public function addFeatruedImage(){
		$this->add_rest_field('post','featured_image',
			function($data){
				return get_the_post_thumbnail_url( $data['id']);
			}
		);
	}

	public function addBlogCat(){
		$this->add_rest_field('post','blog_categories',
			function($data){
				$terms = get_the_terms($data['id'],'category');
				if(empty($terms)){return;}
				$arr = [];
				foreach($terms as $term){
					$arr[] = array(
						'id'=>$term->term_id,
						'name'=>$term->name,
						'slug'=>$term->slug,
					);
				}
				return $arr;
			}
		);
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
				'title',
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


	


}

new OutdoorfBlog();