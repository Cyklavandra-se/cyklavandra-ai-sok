<?php
$_SERVER['HTTP_HOST']='localhost'; define('WP_IMPORTING',true);
require rtrim($argv[2] ?? __DIR__.'/wordpress','/').'/wp-load.php';
global $wpdb; $D=$argv[1];
foreach ($wpdb->get_col("SELECT ID FROM $wpdb->posts") as $id) wp_delete_post($id,true);
foreach (get_terms(['taxonomy'=>'post_tag','hide_empty'=>false]) as $t) wp_delete_term($t->term_id,'post_tag');
$tags=json_decode(file_get_contents("$D/tags.json"),true); $map=[];
foreach ($tags as $t){ $r=wp_insert_term(html_entity_decode($t['name']),'post_tag',['slug'=>$t['slug']]); if(!is_wp_error($r)) $map[$t['id']]=$r['term_id']; }
$seen=[];
foreach (json_decode(file_get_contents("$D/trips.json"),true) as $p){ if(isset($seen[$p['id']])) continue; $seen[$p['id']]=1;
  $id=wp_insert_post(['import_id'=>$p['id'],'post_type'=>'page','post_status'=>'publish','post_title'=>html_entity_decode($p['title']['rendered'],ENT_QUOTES),'post_content'=>$p['content']['rendered'],'post_excerpt'=>wp_strip_all_tags($p['excerpt']['rendered']??'')]);
  wp_set_post_terms($id, array_values(array_filter(array_map(fn($x)=>$map[$x]??null,$p['tags']))), 'post_tag');
}
// destinationer: förälder-sidor + undersidor
$i=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Information','post_name'=>'information']);
$h=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Hitta resa','post_name'=>'hitta-resa','post_parent'=>$i]);
$dd=wp_insert_post(['import_id'=>8717,'post_type'=>'page','post_status'=>'publish','post_title'=>'Destinationer','post_name'=>'destinationer','post_parent'=>$h]);
$dests=json_decode(file_get_contents("$D/dests.json"),true); $did=[8717=>$dd];
usort($dests, fn($a,$b)=>0);
foreach ($dests as $d){ $par=$did[$d['parent']]??$dd; $id=wp_insert_post(['import_id'=>$d['id'],'post_type'=>'page','post_status'=>'publish','post_title'=>html_entity_decode($d['title']['rendered'],ENT_QUOTES),'post_content'=>$d['content']['rendered'],'post_excerpt'=>wp_strip_all_tags($d['excerpt']['rendered']??''),'post_parent'=>$par]); $did[$d['id']]=$id; }
foreach (json_decode(file_get_contents("$D/events.json"),true) as $e){ $id=wp_insert_post(['import_id'=>$e['id'],'post_type'=>'tribe_events','post_status'=>'publish','post_title'=>html_entity_decode($e['title'],ENT_QUOTES),'post_content'=>$e['description']]); update_post_meta($id,'_EventStartDate',$e['start_date']); update_post_meta($id,'_EventEndDate',$e['end_date']); }
echo "importerat: ", count($seen)," resor, ", count($dests)," destinationer\n";
