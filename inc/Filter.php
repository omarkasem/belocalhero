<?php
class OutdoorfFilter extends OutdoorfMain{

    public function __construct(){
        $this->registerHooks();
    }

    public function registerHooks(){
        add_filter('rest_place_query', array($this,'typeFilter'), 10, 2);
        add_filter('rest_place_query', array($this,'categoryFilter'), 10, 2);
        add_filter('rest_place_query', array($this,'DeliveryFilter'), 10, 2);
        add_filter('rest_place_query', array($this,'paymentMethodsFilter'), 10, 2);
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


    public function convertKeysToValues($name,$val){
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
            foreach($val as $key){
                $new[] = $array[intval($key)];
            }
            return $new;
        }else{
            return $array[intval($val)];
        }
    }
    
    public function paymentMethodsFilter($args, $request){
        if (empty($request['payment_methods']) || !is_array($request['payment_methods'])) {
            return $args;
        }
        $args['meta_query']['relation'] = 'AND';
        $payment_methods = $this->getOriginalTranslation($request['payment_methods']);
        foreach($payment_methods as $feat){
            $args['meta_query'][] = array(
                'key'     => 'payment_methods',
                'value'   => $feat,
                'compare' => 'LIKE',
            );
        }
        return $args;
    }


    public function deliveryFilter($args, $request){
        if (empty($request['delivery']) || !is_array($request['delivery'])) {
            return $args;
        }
        $args['meta_query']['relation'] = 'AND';
        $delivery = $this->getOriginalTranslation($request['delivery']);
        foreach($delivery as $feat){
            $args['meta_query'][] = array(
                'key'     => 'delivery',
                'value'   => $feat,
                'compare' => 'LIKE',
            );
        }
        return $args;
    }


    public function categoryFilter($args, $request){
        if (empty($request['category']) || !is_array($request['category'])) {
            return $args;
        }
        $args['meta_query']['relation'] = 'AND';
        $category = $this->getOriginalTranslation($request['category']);
        foreach($category as $feat){
            $args['meta_query'][] = array(
                'key'     => 'category',
                'value'   => $feat,
                'compare' => 'LIKE',
            );
        }
        return $args;
    }


    public function typeFilter($args, $request){
        if ($request['type'] == '') {
            return $args;
        }
        $args['meta_query']['relation'] = 'AND';
        $type = $this->getOriginalTranslation($request['type']);
        $args['meta_query'][] = array(
            'key'     => 'type',
            'value'   => $type,
            'compare' => 'LIKE',
        );
        return $args;
    }




}

new OutdoorfFilter();