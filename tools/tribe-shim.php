<?php
// Minimal ersättning för The Events Calendar, bara för test av katalogen.
add_action('init', function(){ register_post_type('tribe_events', ['public'=>true]); register_taxonomy_for_object_type('post_tag','page'); });
if (!function_exists('tribe_get_events')) {
  function tribe_get_events($a){ return get_posts(['post_type'=>'tribe_events','post_status'=>'publish','numberposts'=>-1,'meta_key'=>'_EventStartDate','orderby'=>'meta_value','order'=>'ASC','meta_query'=>[['key'=>'_EventStartDate','value'=>$a['start_date'],'compare'=>'>=']]]); }
  function tribe_get_start_date($e,$x,$f){ return substr(get_post_meta(is_object($e)?$e->ID:$e,'_EventStartDate',true),0,10); }
  function tribe_get_end_date($e,$x,$f){ return substr(get_post_meta(is_object($e)?$e->ID:$e,'_EventEndDate',true),0,10); }
}
