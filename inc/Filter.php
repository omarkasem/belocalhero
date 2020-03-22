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


    
    public function paymentMethodsFilter($args, $request){
        if (empty($request['payment_methods']) || !is_array($request['payment_methods'])) {
            return $args;
        }
        $args['meta_query']['relation'] = 'AND';
        $payment_methods = $request['payment_methods'];
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
        $delivery = $request['delivery'];
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
        $category = $request['category'];
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
        if (empty($request['type'])) {
            return $args;
        }
        $args['meta_query']['relation'] = 'AND';
        $type = $request['type'];
        $args['meta_query'][] = array(
            'key'     => 'type',
            'value'   => $type,
            'compare' => 'LIKE',
        );
        return $args;
    }




}

new OutdoorfFilter();