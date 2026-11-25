<?php
/**
 * Kör frågor mot Claude med olika upplägg och jämför förslag, tokens och svarstid.
 * Körs i en LOKAL WordPress med pluginen och en importerad katalog – aldrig på sajten.
 *
 *   ANTHROPIC_API_KEY=… php tools/jamfor-upplagg.php <wp-mapp> [antal-varv] [upplägg,…]
 *
 * Upplägg: A = hela katalogen + Sonnet (nuvarande), B = bantad + Sonnet,
 *          C = bantad + förfilter + Sonnet, D = bantad + förfilter + Haiku, E = bantad + Haiku.
 * Cachning är avstängd i alla upplägg så att siffrorna går att jämföra.
 */
$cv_wp  = rtrim( $argv[1] ?? '', '/' );
$rounds = max( 1, (int) ( $argv[2] ?? 1 ) );
$only   = isset( $argv[3] ) ? explode( ',', strtoupper( $argv[3] ) ) : null;
$key    = getenv( 'ANTHROPIC_API_KEY' );
if ( ! $cv_wp || ! $key ) {
	fwrite( STDERR, "Användning: ANTHROPIC_API_KEY=… php tools/jamfor-upplagg.php <wp-mapp> [varv] [A,B,…]\n" );
	exit( 1 );
}
define( 'CVAI_API_KEY', $key );
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
require $cv_wp . '/wp-load.php';

$dir     = __DIR__;
$queries = json_decode( file_get_contents( "$dir/fragor.json" ), true );
$setups  = array(
	'A' => array( 'full', 0, 'claude-sonnet-5-5' ),
	'B' => array( 'slim', 0, 'claude-sonnet-5-5' ),
	'C' => array( 'slim', 1, 'claude-sonnet-5-5' ),
	'D' => array( 'slim', 1, 'claude-haiku-5-5' ),
	'E' => array( 'slim', 0, 'claude-haiku-5-5' ),
);
$o                  = get_option( 'cvai_options', array() );
$o['extra_rules']   = $o['extra_rules'] ?? '' ? $o['extra_rules'] : file_get_contents( "$dir/extraregler-rekonstruerade.txt" );
$o['rate_per_hour'] = 1000;
$o['daily_cap']     = 10000;
$o['cache_ttl']     = 'off';
$o['log_enabled']   = 1;
global $wpdb;
$out = array();
for ( $r = 1; $r <= $rounds; $r++ ) {
	foreach ( $setups as $name => list( $mode, $pf, $model ) ) {
		if ( $only && ! in_array( $name, $only, true ) ) {
			continue;
		}
		$o['catalog_mode'] = $mode;
		$o['prefilter']    = $pf;
		$o['model']        = $model;
		update_option( 'cvai_options', $o );
		foreach ( $queries as $i => $q ) {
			$_SERVER['REMOTE_ADDR'] = '10.0.' . $r . '.' . $i;
			$req = new WP_REST_Request( 'POST', '/cvai/v1/search' );
			$req->set_header( 'content-type', 'application/json' );
			$req->set_body( wp_json_encode( array( 'q' => $q, 'lang' => 'sv' ) ) );
			$t0  = microtime( true );
			$res = rest_do_request( $req );
			$ms  = (int) round( ( microtime( true ) - $t0 ) * 1000 );
			$d   = $res->get_data();
			$row = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}cvai_log ORDER BY id DESC LIMIT 1" );
			$out[] = array(
				'upplagg'  => $name,
				'varv'     => $r,
				'fraga'    => $i + 1,
				'status'   => $res->get_status() . ' ' . ( $row->status ?? '' ),
				'bast'     => array_column( (array) ( $d['hits'] ?? array() ), 'title' ),
				'alt'      => array_column( (array) ( $d['alternatives'] ?? array() ), 'title' ),
				'dest'     => array_column( (array) ( $d['destinations'] ?? array() ), 'title' ),
				'tokens_in'  => (int) ( $row->tokens_in ?? 0 ),
				'tokens_ut'  => (int) ( $row->tokens_out ?? 0 ),
				'ms'       => $ms,
				'tolkning' => json_decode( (string) ( $row->interpretation ?? '' ), true ),
				'fel'      => $d['error'] ?? '',
			);
			$e = end( $out );
			printf( "%s v%d F%d %s in=%d ut=%d %dms | %s\n", $name, $r, $i + 1, $e['status'], $e['tokens_in'], $e['tokens_ut'], $ms, implode( ' ; ', $e['bast'] ) ?: $e['fel'] );
		}
	}
}
file_put_contents( "$dir/../jamforelse-" . gmdate( 'Ymd-His' ) . '.json', wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
