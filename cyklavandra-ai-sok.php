<?php
/**
 * Plugin Name: Cykla & Vandra – AI-sök
 * Description: Fritextsökning med AI som matchar kundens önskemål mot taggade resor. Stöder WPML (en katalog per språk). Använd shortcoden [cv_ai_sok] på valfri sida.
 * Version:     1.4.12
 * Author:      Cykla & Vandra
 * Text Domain: cvai
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Update URI:  https://github.com/Cyklavandra-se/cyklavandra-ai-sok
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Skydd om pluginen råkar finnas installerad två gånger (t.ex. "Source code"-zip från GitHub).
if ( defined( 'CVAI_VERSION' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p><strong>AI-sök</strong> är installerad två gånger. Avaktivera och radera den ena kopian under Tillägg.</p></div>';
		}
	);
	return;
}

define( 'CVAI_VERSION', '1.4.12' );
define( 'CVAI_DB_VERSION', '2' );
define( 'CVAI_FILE', __FILE__ );
define( 'CVAI_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/updater.php';

/* -------------------------------------------------------------------------
 * Inställningar
 * ---------------------------------------------------------------------- */

function cvai_defaults() {
	return array(
		'api_key'        => '',
		'model'          => 'claude-sonnet-5-5',
		'trip_tags'      => 'guidad, självguidad',
		'include_events' => 1,
		'rate_per_hour'  => 20,
		'daily_cap'      => 500,
		'examples'       => "Vi är 6 personer som vill vandra i Italien i oktober. Vi är ganska duktiga vandrare och har gått i Österrike många somrar.\nTvå pensionärer som vill cykla lugnt i maj, gärna med vin och god mat.\nJag vill på en guidad vandringsresa i höst och slippa planera själv.\nFamilj med två tonåringar som vill vandra i bergen i juli.",
		'extra_rules'    => '',
		'log_enabled'    => 1,
		'examples_by_lang' => array(),
		'max_hits'       => 4,
		'max_alts'       => 2,
		'color_text'     => '#1f2a24',
		'color_muted'    => '#5d6a62',
		'color_button'   => '#2f5d46',
		'color_btn_text' => '#ffffff',
		'color_accent'   => '#c8692c',
		'color_card'     => '#ffffff',
		'color_border'   => '#e2ddd0',
		'radius'         => 14,
		'show_images'    => 1,
	);
}

function cvai_color_keys() {
	return array(
		'color_text'     => array( 'Textfärg', 'Texten kunden skriver och texten i resultaten.' ),
		'color_muted'    => array( 'Dämpad text', 'Platshållartext, tips och små etiketter.' ),
		'color_button'   => array( 'Knappfärg', 'Sökknappen, nivå-etiketter i tolkningen och "Därför passar den".' ),
		'color_btn_text' => array( 'Knappens textfärg', '' ),
		'color_accent'   => array( 'Länkfärg', '"Läs mer och boka".' ),
		'color_card'     => array( 'Bakgrund sökruta och kort', '' ),
		'color_border'   => array( 'Ramfärg', '' ),
	);
}

function cvai_opt( $key ) {
	$opts = wp_parse_args( get_option( 'cvai_options', array() ), cvai_defaults() );
	if ( 'api_key' === $key && defined( 'CVAI_API_KEY' ) && CVAI_API_KEY ) {
		return CVAI_API_KEY;
	}
	return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
}

/* -------------------------------------------------------------------------
 * WPML-hjälpare (fungerar också utan WPML)
 * ---------------------------------------------------------------------- */

function cvai_has_wpml() {
	return defined( 'ICL_SITEPRESS_VERSION' ) || has_filter( 'wpml_current_language' );
}

function cvai_current_lang() {
	$lang = apply_filters( 'wpml_current_language', null );
	if ( ! $lang ) {
		$lang = substr( get_locale(), 0, 2 );
	}
	return sanitize_key( $lang );
}

function cvai_default_lang() {
	$lang = apply_filters( 'wpml_default_language', null );
	return $lang ? sanitize_key( $lang ) : substr( get_locale(), 0, 2 );
}

/** Aktiva språk: [ 'sv' => 'Svenska', 'no' => 'Norsk bokmål', ... ] */
function cvai_active_langs() {
	$langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
	$out   = array();
	if ( is_array( $langs ) && $langs ) {
		foreach ( $langs as $code => $l ) {
			$out[ $code ] = $l['translated_name'] ?? ( $l['native_name'] ?? $code );
		}
	} else {
		$out[ cvai_current_lang() ] = cvai_lang_name( cvai_current_lang() );
	}
	return $out;
}

/** Kör $fn med WPML växlat till $lang, och växla sedan tillbaka. */
function cvai_in_lang( $lang, $fn ) {
	if ( ! cvai_has_wpml() || ! $lang ) {
		return $fn();
	}
	$prev = cvai_current_lang();
	do_action( 'wpml_switch_language', $lang );
	try {
		return $fn();
	} finally {
		do_action( 'wpml_switch_language', $prev );
	}
}

/* -------------------------------------------------------------------------
 * Aktivering / uppgradering av loggtabell
 * ---------------------------------------------------------------------- */

register_activation_hook( __FILE__, 'cvai_install' );
add_action( 'plugins_loaded', 'cvai_maybe_upgrade' );

/** Engångsbyte från Haiku till Sonnet 5.5 (1.4.11). Ett annat val i inställningarna lämnas orört. */
add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'cvai_model_switch_1411' ) ) {
			return;
		}
		$o = get_option( 'cvai_options', array() );
		if ( is_array( $o ) && ( $o['model'] ?? '' ) === 'claude-haiku-5-5' ) {
			$o['model'] = 'claude-sonnet-5-5';
			update_option( 'cvai_options', $o );
		}
		update_option( 'cvai_model_switch_1411', 1, false );
	}
);

function cvai_maybe_upgrade() {
	if ( get_option( 'cvai_db_version' ) !== CVAI_DB_VERSION ) {
		cvai_install();
	}
}

function cvai_install() {
	global $wpdb;
	$table   = $wpdb->prefix . 'cvai_log';
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		"CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created DATETIME NOT NULL,
			lang VARCHAR(10) NOT NULL DEFAULT '',
			query TEXT NOT NULL,
			hits TEXT NULL,
			interpretation TEXT NULL,
			ms INT UNSIGNED NOT NULL DEFAULT 0,
			tokens_in INT UNSIGNED NOT NULL DEFAULT 0,
			tokens_out INT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'ok',
			PRIMARY KEY  (id),
			KEY created (created)
		) $charset;"
	);
	if ( false === get_option( 'cvai_options' ) ) {
		add_option( 'cvai_options', cvai_defaults() );
	}
	update_option( 'cvai_db_version', CVAI_DB_VERSION );
}

/* -------------------------------------------------------------------------
 * Resekatalogen (en per språk)
 * ---------------------------------------------------------------------- */

/** Känner igen svårighetstaggar även när WPML gett översatta slugs, t.ex. hike2-2 eller hike2-no. */
function cvai_level_from_slug( $slug ) {
	if ( preg_match( '/^(hike|bike)([1-4])(?:$|[-_])/', $slug, $m ) ) {
		return array( 'type' => $m[1], 'n' => (int) $m[2] );
	}
	return null;
}

/** Ord som betyder guidad / på egen hand / vandring / cykel / land på olika språk. */
function cvai_vocab() {
	return array(
		'guided'  => array( 'guidad', 'guidet', 'guidede', 'guided', 'geführt', 'gefuhrt', 'opastettu', 'begeleid', 'guidé', 'guidée' ),
		'self'    => array( 'självguidad', 'sjalvguidad', 'selvguidet', 'selvguidede', 'self-guided', 'selfguided', 'individuell', 'omatoiminen', 'zelfgeleid', 'en liberté', 'autoguidé' ),
		'hike'    => array( 'vandra', 'vandring', 'vandre', 'fottur', 'hike', 'hiking', 'walking', 'wandern', 'wandelen', 'vaellus', 'randonnée' ),
		'bike'    => array( 'cykla', 'cykel', 'sykle', 'sykkel', 'sykling', 'cykle', 'cycling', 'bike', 'radfahren', 'fietsen', 'pyöräily', 'vélo' ),
		'country' => array( 'italien', 'italia', 'italy', 'spanien', 'spania', 'spain', 'frankrike', 'france', 'frankreich', 'portugal', 'österrike', 'østerrike', 'østrig', 'austria', 'österreich', 'tyskland', 'germany', 'deutschland', 'grekland', 'hellas', 'grækenland', 'greece', 'slovenien', 'slovenia', 'kroatien', 'kroatia', 'croatia', 'schweiz', 'sveits', 'switzerland', 'holland', 'nederland', 'belgien', 'belgia', 'danmark', 'sverige', 'norge', 'skottland', 'scotland', 'uk', 'malta', 'bulgarien', 'bulgaria', 'kanarieöarna', 'kanariøyene', 'mallorca', 'costa brava', 'garda', 'kap verde', 'costa rica', 'kuba', 'cuba', 'peru', 'ungern', 'ungarn', 'hungary', 'unkari', 'hongarije', 'hongrie', 'ecuador' ),
	);
}

/** Tag-id:n (i aktuellt språk) som markerar att en sida är en resa. */
function cvai_trip_tag_ids( $lang ) {
	$ids   = array();
	$names = array_filter( array_map( 'trim', explode( ',', (string) cvai_opt( 'trip_tags' ) ) ) );
	$names = array_merge( $names, cvai_vocab()['guided'], cvai_vocab()['self'] );

	foreach ( array_unique( $names ) as $t ) {
		foreach ( array( 'name', 'slug' ) as $field ) {
			$value = 'slug' === $field ? sanitize_title( $t ) : $t;
			$term  = get_term_by( $field, $value, 'post_tag' );
			if ( $term ) {
				$ids[] = (int) $term->term_id;
				// Översättningen av taggen i det språk vi bygger för.
				$tr = apply_filters( 'wpml_object_id', (int) $term->term_id, 'post_tag', false, $lang );
				if ( $tr ) {
					$ids[] = (int) $tr;
				}
			}
		}
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

function cvai_catalog_key( $lang ) {
	// Pluginversionen ingår så att katalogen byggs om efter en uppdatering.
	return 'cvai_catalog_' . $lang . '_' . (int) get_option( 'cvai_cat_ver', 1 ) . '_' . CVAI_VERSION;
}

/**
 * Bygger katalogen för ett språk: text till AI:n + kortdata till frontend.
 * Cachas i 24 timmar och töms när en sida eller tagg sparas.
 */
function cvai_get_catalog( $lang = null, $force = false ) {
	$lang = $lang ? sanitize_key( $lang ) : cvai_current_lang();
	$key  = cvai_catalog_key( $lang );

	if ( ! $force ) {
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$catalog = cvai_in_lang(
		$lang,
		function () use ( $lang ) {
			return cvai_build_catalog( $lang );
		}
	);
	set_transient( $key, $catalog, DAY_IN_SECONDS );
	return $catalog;
}

function cvai_build_catalog( $lang ) {
	$cards   = array();
	$lines   = array();
	$vocab   = cvai_vocab();
	$tag_ids = cvai_trip_tag_ids( $lang );

	if ( $tag_ids ) {
		$posts = get_posts(
			array(
				'post_type'        => array( 'page', 'post', 'avada_portfolio' ),
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'tag__in'          => $tag_ids,
				'suppress_filters' => false, // låter WPML filtrera fram rätt språk
			)
		);

		foreach ( $posts as $p ) {
			// Säkerhetsspärr om filtreringen inte slår igenom.
			$details = apply_filters( 'wpml_post_language_details', null, $p->ID );
			if ( is_array( $details ) && ! empty( $details['language_code'] ) && $details['language_code'] !== $lang ) {
				continue;
			}

			$tags   = wp_get_post_tags( $p->ID, array( 'fields' => 'all' ) );
			$names  = array();
			$levels = array();
			$land   = array();
			$form   = '';
			$akt    = array();

			foreach ( $tags as $t ) {
				$lvl = cvai_level_from_slug( $t->slug );
				if ( $lvl ) {
					$levels[] = cvai_level_label( $lvl['type'], $lvl['n'], $lang );
					continue;
				}
				$lname = mb_strtolower( $t->name );
				if ( in_array( $lname, $vocab['country'], true ) ) {
					$land[] = mb_convert_case( $t->name, MB_CASE_TITLE, 'UTF-8' );
				}
				if ( in_array( $lname, $vocab['guided'], true ) ) {
					$form = cvai_t( 'guided', $lang );
				} elseif ( in_array( $lname, $vocab['self'], true ) ) {
					$form = cvai_t( 'self_guided', $lang );
				}
				if ( in_array( $lname, $vocab['hike'], true ) ) {
					$akt['hike'] = cvai_t( 'hiking', $lang );
				} elseif ( in_array( $lname, $vocab['bike'], true ) ) {
					$akt['bike'] = cvai_t( 'cycling', $lang );
				}
				$names[] = $t->name;
			}

			$raw  = has_excerpt( $p ) ? $p->post_excerpt . ' ' . $p->post_content : $p->post_content;
			$text = cvai_clean_text( $raw, 700 );
			$key  = 'P' . $p->ID;

			$meta = array_slice( $land, 0, 2 );
			if ( $akt ) {
				$meta[] = implode( ' & ', $akt );
			}
			if ( $form ) {
				$meta[] = $form;
			}

			$cards[ $key ] = array(
				'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
				'url'   => get_permalink( $p ),
				'img'   => get_the_post_thumbnail_url( $p, 'medium_large' ) ?: '',
				'meta'  => array_values( array_filter( $meta ) ),
				'level' => implode( ', ', $levels ),
				'land'  => array_values( array_unique( array_map( 'mb_strtolower', $land ) ) ),
			);

			$lines[] = sprintf(
				'%s | %s | Taggar: %s | Nivå: %s | Text: %s',
				$key,
				$cards[ $key ]['title'],
				implode( ', ', $names ),
				$levels ? implode( ', ', $levels ) : '-',
				$text
			);
		}
	}

	// Kommande guidade avgångar från The Events Calendar.
	$event_lines = array();
	if ( cvai_opt( 'include_events' ) && function_exists( 'tribe_get_events' ) ) {
		$events = tribe_get_events(
			array(
				'start_date'       => current_time( 'Y-m-d' ),
				'posts_per_page'   => 300,
				'eventDisplay'     => 'list',
				'suppress_filters' => false,
			)
		);
		foreach ( (array) $events as $e ) {
			$details = apply_filters( 'wpml_post_language_details', null, $e->ID );
			if ( is_array( $details ) && ! empty( $details['language_code'] ) && $details['language_code'] !== $lang ) {
				continue;
			}
			$key   = 'E' . $e->ID;
			$start = function_exists( 'tribe_get_start_date' ) ? tribe_get_start_date( $e, false, 'Y-m-d' ) : '';
			$end   = function_exists( 'tribe_get_end_date' ) ? tribe_get_end_date( $e, false, 'Y-m-d' ) : '';

			$cards[ $key ] = array(
				'title' => html_entity_decode( get_the_title( $e ), ENT_QUOTES, 'UTF-8' ),
				'url'   => get_permalink( $e ),
				'img'   => get_the_post_thumbnail_url( $e, 'medium_large' ) ?: '',
				'meta'  => array( cvai_t( 'guided_departure', $lang ), trim( $start . ( $end && $end !== $start ? ' – ' . $end : '' ) ) ),
				'level' => '',
			);
			$event_lines[] = sprintf(
				'%s | %s | Datum: %s–%s | Text: %s',
				$key,
				$cards[ $key ]['title'],
				$start,
				$end,
				cvai_clean_text( $e->post_content, 300 )
			);
		}
	}

	$text = "RESOR (id | namn | taggar | nivå | beskrivning):\n" . implode( "\n", $lines );
	if ( $event_lines ) {
		$text .= "\n\nKOMMANDE GUIDADE AVGÅNGAR MED DATUM (id | namn | datum | beskrivning):\n" . implode( "\n", $event_lines );
	}

	return array(
		'lang'   => $lang,
		'text'   => $text,
		'cards'  => $cards,
		'count'  => count( $lines ),
		'events' => count( $event_lines ),
		'built'  => current_time( 'mysql' ),
	);
}

/** Rensar Avada/Fusion-shortcodes och HTML till ren text. */
function cvai_clean_text( $html, $max = 700 ) {
	$text = preg_replace( '/\[\/?[a-zA-Z_][^\]]*\]/', ' ', (string) $html );
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	if ( mb_strlen( $text ) > $max ) {
		$text = mb_substr( $text, 0, $max ) . '…';
	}
	return $text;
}

/** Töm alla språks kataloger genom att byta versionsnummer. */
function cvai_flush_catalog() {
	update_option( 'cvai_cat_ver', (int) get_option( 'cvai_cat_ver', 1 ) + 1, false );
}
add_action( 'save_post', 'cvai_flush_catalog' );
add_action( 'deleted_post', 'cvai_flush_catalog' );
add_action( 'edited_post_tag', 'cvai_flush_catalog' );
add_action( 'wpml_translation_update', 'cvai_flush_catalog' );

/* -------------------------------------------------------------------------
 * Prompt + strukturerat svar
 * ---------------------------------------------------------------------- */

function cvai_system_prompt( $catalog_text ) {
	$extra = trim( (string) cvai_opt( 'extra_rules' ) );
	return "Du är resesök-assistent för Cykla & Vandra, en researrangör av vandrings- och cykelresor: guidade gruppresor och resor på egen hand (självguidade).

UPPGIFT:
1. Tolka kundens önskemål: land/region, månad, aktivitet (vandring/cykel), nivå, antal personer och övrigt (vin, kust, berg, hund, tåg, budget m.m.).
2. Översätt erfarenhet till nivå. Exempel: \"många somrar i Österrike/Alperna\", \"vana vandrare\" = nivå 2–3; \"lugnt\", \"ovana\", \"pensionärer som tar det lugnt\" = nivå 1–2. Nivå 4 bara om kunden tydligt söker utmaning.
3. Välj " . cvai_hits_range() . " resor som passar bäst. Resan ska gå att göra den månad kunden önskar: läs månadstaggar, säsongstaggar och datum i texten (taggarna kan vara på olika språk). Om en önskad månad redan har passerat i år gäller nästa år. Guidade resor med datum som har passerat får inte rekommenderas.
4. Finns en kommande guidad avgång (id som börjar på E) som passar, föredra den framför resans allmänna sida.
5. " . ( (int) cvai_opt( 'max_alts' ) > 0 ? 'Ge även 0–' . (int) cvai_opt( 'max_alts' ) . ' alternativ som nästan passar (annat land, närliggande månad eller lite annan nivå) med en kort förklaring. Finns ingen bra träff, säg det ärligt i sammanfattningen och ge alternativ.' : 'Ge inga alternativ (lämna listan tom). Finns ingen bra träff, säg det ärligt i sammanfattningen.' ) . "
6. Använd ENDAST id:n ur katalogen. Ange för varje resa både id och namn från samma katalograd, och kontrollera att motiveringen handlar om just den resan. Hitta aldrig på resor, priser, datum eller avresedagar, och lova inte valfritt startdatum om det inte står i katalogen. Nämn bara pris om det står i katalogen.
7. SPRÅK: Skriv sammanfattning, motiveringar och tolkning på det svarsspråk som anges i kundens meddelande. Om kunden tydligt skriver på ett annat språk, svara på kundens språk. Tilltala kunden med \"ni\"/motsvarande om de är flera, annars \"du\".
8. Fyll i \"regler\" i verktyget INNAN du väljer resor: vilka extra regler som gäller för just den här frågan, och vilka länder de utesluter.

Svara ALLTID genom att anropa verktyget visa_resor, exakt en gång, och skriv ingen annan text.

KATALOG:
" . $catalog_text . ( $extra ? "\n\nEXTRA REGLER FRÅN CYKLA & VANDRA – OBLIGATORISKA, de gäller före allt annat ovan:\n$extra\n\nEn resa som bryter mot en extra regel får aldrig visas, varken som träff eller alternativ." : '' );
}

function cvai_hits_range() {
	$max = max( 1, (int) cvai_opt( 'max_hits' ) );
	return $max <= 2 ? ( 1 === $max ? '1' : '1–2' ) : '2–' . $max;
}

function cvai_tool_schema() {
	$hit = array(
		'type'       => 'object',
		'properties' => array(
			'id'         => array( 'type' => 'string', 'description' => 'Id ur katalogen, t.ex. P123 eller E456' ),
			'namn'       => array( 'type' => 'string', 'description' => 'Resans namn exakt som det står i katalogen på samma rad som id:t' ),
			'motivering' => array( 'type' => 'string', 'description' => '1–2 meningar till kunden om varför resan passar' ),
		),
		'required'   => array( 'id', 'namn', 'motivering' ),
	);
	return array(
		'name'         => 'visa_resor',
		'description'  => 'Visar de rekommenderade resorna för kunden.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'tolkning'       => array(
					'type'       => 'object',
					'properties' => array(
						'land'      => array( 'type' => 'string' ),
						'manad'     => array( 'type' => 'string' ),
						'aktivitet' => array( 'type' => 'string' ),
						'niva'      => array( 'type' => 'string' ),
						'antal'     => array( 'type' => 'string' ),
						'ovrigt'    => array( 'type' => 'string' ),
					),
				),
				'regler'         => array(
					'type'        => 'object',
					'description' => 'Fylls i före träffarna: vilka extra regler från Cykla & Vandra som gäller för frågan.',
					'properties'  => array(
						'tillampade'      => array( 'type' => 'string', 'description' => 'De extra regler som gäller för just den här frågan, kort. Tomt om ingen gäller.' ),
						'uteslutna_lander' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Länder som reglerna utesluter för frågan, stavade som i katalogens taggar (t.ex. sverige, norge, danmark). Tom lista om inga.',
						),
					),
				),
				'sammanfattning' => array( 'type' => 'string', 'description' => '1–2 varma, konkreta meningar till kunden' ),
				'traffar'        => array( 'type' => 'array', 'items' => $hit, 'description' => cvai_hits_range() . ' bästa resor' ),
				'alternativ'     => array( 'type' => 'array', 'items' => $hit, 'description' => '0–' . (int) cvai_opt( 'max_alts' ) . ' alternativ som nästan passar' ),
			),
			'required'   => array( 'sammanfattning', 'traffar' ),
		),
	);
}

/* -------------------------------------------------------------------------
 * REST-endpoint
 * ---------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'cvai/v1',
			'/search',
			array(
				'methods'             => 'POST',
				'callback'            => 'cvai_rest_search',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'    => array(
						'type'     => 'string',
						'required' => true,
					),
					'lang' => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);
	}
);

function cvai_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
}

function cvai_same_origin() {
	$home   = wp_parse_url( home_url(), PHP_URL_HOST );
	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ), PHP_URL_HOST ) : '';
	$ref    = isset( $_SERVER['HTTP_REFERER'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_HOST ) : '';
	$strip  = function ( $h ) {
		return preg_replace( '/^www\./', '', (string) $h );
	};
	return ( $origin && $strip( $origin ) === $strip( $home ) ) || ( ! $origin && $ref && $strip( $ref ) === $strip( $home ) );
}

function cvai_error( $msg_key, $lang, $code ) {
	return new WP_REST_Response( array( 'error' => cvai_t( $msg_key, $lang ) ), $code );
}

function cvai_rest_search( WP_REST_Request $req ) {
	$lang = sanitize_key( (string) $req->get_param( 'lang' ) );
	if ( ! $lang || ! array_key_exists( $lang, cvai_active_langs() ) ) {
		$lang = cvai_has_wpml() ? cvai_default_lang() : cvai_current_lang();
	}

	$q = trim( wp_strip_all_tags( (string) $req->get_param( 'q' ) ) );
	if ( mb_strlen( $q ) < 5 ) {
		return cvai_error( 'err_short', $lang, 400 );
	}
	if ( mb_strlen( $q ) > 600 ) {
		$q = mb_substr( $q, 0, 600 );
	}
	if ( ! cvai_same_origin() ) {
		return cvai_error( 'err_forbidden', $lang, 403 );
	}

	// Begränsning per besökare och per dag.
	$rl_key = 'cvai_rl_' . md5( cvai_client_ip() );
	$count  = (int) get_transient( $rl_key );
	if ( $count >= (int) cvai_opt( 'rate_per_hour' ) ) {
		return cvai_error( 'err_rate', $lang, 429 );
	}
	$day_key = 'cvai_day_' . current_time( 'Ymd' );
	$day     = (int) get_transient( $day_key );
	if ( $day >= (int) cvai_opt( 'daily_cap' ) ) {
		return cvai_error( 'err_cap', $lang, 503 );
	}
	set_transient( $rl_key, $count + 1, HOUR_IN_SECONDS );
	set_transient( $day_key, $day + 1, 2 * DAY_IN_SECONDS );

	$api_key = cvai_opt( 'api_key' );
	if ( ! $api_key ) {
		return cvai_error( 'err_config', $lang, 500 );
	}

	$catalog = cvai_get_catalog( $lang );
	$started = microtime( true );

	$body = array(
		'model'       => cvai_opt( 'model' ),
		'max_tokens'  => 8000, // plats för tänkande + svar
		'tools'       => array( cvai_tool_schema() ),
		// Sonnet/Opus 5.5 tillåter inte tvingat verktyg; prompten kräver visa_resor.
		'tool_choice' => array( 'type' => 'auto' ),
		'system'      => array(
			array(
				'type'          => 'text',
				'text'          => cvai_system_prompt( $catalog['text'] ),
				'cache_control' => array( 'type' => 'ephemeral' ),
			),
		),
		'messages'    => array(
			array(
				'role'    => 'user',
				'content' => 'Dagens datum: ' . current_time( 'Y-m-d' ) . "\nSvarsspråk: " . cvai_lang_name( $lang ) . ( trim( (string) cvai_opt( 'extra_rules' ) ) ? "\nKontrollera de obligatoriska EXTRA REGLERNA innan du väljer resor." : '' ) . "\n\nKUNDENS BESKRIVNING:\n\"\"\"" . $q . '"""',
			),
		),
	);

	// Låg ansträngning = snabbare svar. Stöds av generation 5-modellerna.
	if ( preg_match( '/^claude-(opus|sonnet|haiku|fable|mythos)-5/', (string) $body['model'] ) ) {
		$body['output_config'] = array( 'effort' => 'low' );
	}

	$resp = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 45,
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		)
	);

	$ms = (int) round( ( microtime( true ) - $started ) * 1000 );

	if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
		$detail = is_wp_error( $resp ) ? $resp->get_error_message() : wp_remote_retrieve_body( $resp );
		error_log( '[cvai] API-fel: ' . mb_substr( (string) $detail, 0, 500 ) );
		cvai_log( $lang, $q, array(), array( 'felsökning' => mb_substr( (string) $detail, 0, 500 ) ), $ms, 0, 0, 'api_error' );
		return cvai_error( 'err_api', $lang, 502 );
	}

	$json = json_decode( wp_remote_retrieve_body( $resp ), true );
	$text = '';
	$data = null;
	foreach ( (array) ( $json['content'] ?? array() ) as $block ) {
		$type = $block['type'] ?? '';
		if ( 'tool_use' === $type && is_array( $block['input'] ?? null ) ) {
			$data = $block['input'];
		} elseif ( 'text' === $type ) {
			$text .= $block['text'];
		}
	}
	$usage   = $json['usage'] ?? array();
	$tok_in  = (int) ( $usage['input_tokens'] ?? 0 ) + (int) ( $usage['cache_read_input_tokens'] ?? 0 ) + (int) ( $usage['cache_creation_input_tokens'] ?? 0 );
	$tok_out = (int) ( $usage['output_tokens'] ?? 0 );

	// Reserv: försök tolka JSON i fritext om verktyget inte användes.
	if ( ! is_array( $data ) && preg_match( '/\{[\s\S]*\}/', $text, $m ) ) {
		$data = json_decode( preg_replace( '/[\x00-\x1F]+/', ' ', $m[0] ), true );
	}
	if ( ! is_array( $data ) ) {
		$raw = mb_substr( wp_remote_retrieve_body( $resp ), 0, 1500 );
		error_log( '[cvai] Kunde inte tolka svaret: ' . $raw );
		cvai_log( $lang, $q, array(), array( 'felsökning' => 'stop_reason: ' . ( $json['stop_reason'] ?? '?' ) . ' | ' . $raw ), $ms, $tok_in, $tok_out, 'parse_error' );
		return cvai_error( 'err_parse', $lang, 502 );
	}

	// Länder som AI:n själv angett att extraregler utesluter: ta bort sådana resor säkert.
	$excluded = array();
	foreach ( (array) ( $data['regler']['uteslutna_lander'] ?? array() ) as $c ) {
		$c = cvai_norm_title( (string) $c );
		if ( '' !== $c ) {
			$excluded = array_merge( $excluded, cvai_country_variants( $c ) );
		}
	}
	$excluded = array_unique( $excluded );

	$fixes = array();
	$map   = function ( $list ) use ( $catalog, &$fixes, $excluded ) {
		$out  = array();
		$seen = array();
		foreach ( (array) $list as $h ) {
			$id = cvai_resolve_hit( $catalog['cards'], (string) ( $h['id'] ?? '' ), (string) ( $h['namn'] ?? '' ) );
			if ( $id !== (string) ( $h['id'] ?? '' ) ) {
				$fixes[] = ( $h['id'] ?? '?' ) . '→' . ( $id ? $id : 'bort' ) . ' (' . mb_substr( (string) ( $h['namn'] ?? '' ), 0, 60 ) . ')';
			}
			if ( $id && $excluded && array_intersect( (array) ( $catalog['cards'][ $id ]['land'] ?? array() ), $excluded ) ) {
				$fixes[] = $id . '→bort (regel: ' . implode( ', ', array_intersect( $catalog['cards'][ $id ]['land'], $excluded ) ) . ')';
				continue;
			}
			if ( $id && ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = true;
				$out[]       = array_merge( $catalog['cards'][ $id ], array( 'id' => $id, 'why' => sanitize_text_field( $h['motivering'] ?? '' ) ) );
			}
		}
		return $out;
	};

	$hits = array_slice( $map( $data['traffar'] ?? array() ), 0, max( 1, (int) cvai_opt( 'max_hits' ) ) );
	$alts = array_slice( $map( $data['alternativ'] ?? array() ), 0, max( 0, (int) cvai_opt( 'max_alts' ) ) );
	if ( ! cvai_opt( 'show_images' ) ) {
		foreach ( array( 'hits', 'alts' ) as $v ) {
			foreach ( $$v as $i => $h ) {
				$$v[ $i ]['img'] = '';
			}
		}
	}

	$interp = array();
	foreach ( (array) ( $data['tolkning'] ?? array() ) as $k => $v ) {
		if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
			$interp[ sanitize_key( $k ) ] = sanitize_text_field( (string) $v );
		}
	}

	foreach ( array( 'hits', 'alts' ) as $v ) {
		foreach ( $$v as $i => $h ) {
			unset( $$v[ $i ]['land'] );
		}
	}

	$log_interp = $interp;
	if ( ! empty( $data['regler']['tillampade'] ) ) {
		$log_interp['regler'] = sanitize_text_field( (string) $data['regler']['tillampade'] );
	}
	if ( $fixes ) {
		$log_interp['korrigerade_id'] = implode( '; ', $fixes );
	}
	cvai_log( $lang, $q, array_column( $hits, 'id' ), $log_interp, $ms, $tok_in, $tok_out, $hits ? 'ok' : 'no_hits' );

	return new WP_REST_Response(
		array(
			'summary'        => sanitize_text_field( $data['sammanfattning'] ?? '' ),
			'interpretation' => $interp,
			'hits'           => $hits,
			'alternatives'   => $alts,
		),
		200
	);
}

/** Normaliserar ett resenamn för jämförelse. */
function cvai_norm_title( $s ) {
	$s = mb_strtolower( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) );
	return trim( preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s ) );
}

/** Andel gemensamma ord (minst 3 tecken) mellan två namn, 0–1. */
function cvai_title_score( $a, $b ) {
	$a = cvai_norm_title( $a );
	$b = cvai_norm_title( $b );
	if ( '' === $a || '' === $b ) {
		return 0;
	}
	if ( $a === $b ) {
		return 1;
	}
	$words = function ( $s ) {
		return array_unique( array_filter( explode( ' ', $s ), function ( $w ) {
			return mb_strlen( $w ) >= 3;
		} ) );
	};
	$wa = $words( $a );
	$wb = $words( $b );
	if ( ! $wa || ! $wb ) {
		return ( false !== mb_strpos( $a, $b ) || false !== mb_strpos( $b, $a ) ) ? 0.9 : 0;
	}
	return count( array_intersect( $wa, $wb ) ) / min( count( $wa ), count( $wb ) );
}

/**
 * Kontrollerar att id och namn från AI:n hör ihop. Stämmer de inte litar vi på
 * namnet (motiveringen är skriven för den resan) och letar upp rätt id.
 * Returnerar giltigt id eller '' om träffen ska tas bort.
 */
function cvai_resolve_hit( $cards, $id, $name ) {
	if ( '' === trim( $name ) ) {
		return isset( $cards[ $id ] ) ? $id : '';
	}
	$own = isset( $cards[ $id ] ) ? cvai_title_score( $cards[ $id ]['title'], $name ) : 0;
	if ( $own >= 0.5 ) {
		return $id;
	}
	$best = '';
	$top  = 0;
	foreach ( $cards as $cid => $c ) {
		$sc = cvai_title_score( $c['title'], $name );
		// Vid lika poäng: föredra samma typ (P = resa, E = avgång) som AI:n angav.
		if ( $sc > $top || ( $sc === $top && $sc > 0 && $cid[0] === substr( $id, 0, 1 ) && $best[0] !== $cid[0] ) ) {
			$top  = $sc;
			$best = $cid;
		}
	}
	return $top >= 0.6 ? $best : '';
}

/** Ett land på olika språk, så att \"Norge\" också fångar taggen \"norway\" osv. */
function cvai_country_variants( $c ) {
	$groups = array(
		array( 'sverige', 'sweden', 'schweden', 'ruotsi', 'zweden', 'suède', 'suede' ),
		array( 'norge', 'norway', 'norwegen', 'norja', 'noorwegen', 'norvège', 'norvege' ),
		array( 'danmark', 'denmark', 'dänemark', 'tanska', 'denemarken', 'danemark' ),
		array( 'finland', 'suomi', 'finnland', 'finlande' ),
		array( 'island', 'iceland', 'islanti', 'ijsland', 'islande' ),
		array( 'italien', 'italia', 'italy', 'italie', 'italië' ),
		array( 'spanien', 'spania', 'spain', 'espanja', 'spanje', 'espagne' ),
		array( 'frankrike', 'france', 'frankreich', 'ranska', 'frankrijk' ),
		array( 'österrike', 'østerrike', 'østrig', 'austria', 'österreich', 'itävalta', 'oostenrijk', 'autriche' ),
		array( 'tyskland', 'germany', 'deutschland', 'saksa', 'duitsland', 'allemagne' ),
		array( 'ungern', 'ungarn', 'hungary', 'unkari', 'hongarije', 'hongrie' ),
	);
	foreach ( $groups as $g ) {
		if ( in_array( $c, $g, true ) ) {
			return $g;
		}
	}
	return array( $c );
}

function cvai_log( $lang, $q, $ids, $interp, $ms, $tin, $tout, $status ) {
	if ( ! cvai_opt( 'log_enabled' ) ) {
		return;
	}
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'cvai_log',
		array(
			'created'        => current_time( 'mysql' ),
			'lang'           => $lang,
			'query'          => $q,
			'hits'           => implode( ',', (array) $ids ),
			'interpretation' => wp_json_encode( $interp, JSON_UNESCAPED_UNICODE ),
			'ms'             => $ms,
			'tokens_in'      => $tin,
			'tokens_out'     => $tout,
			'status'         => $status,
		)
	);
}

/* -------------------------------------------------------------------------
 * Shortcode
 * ---------------------------------------------------------------------- */

add_shortcode( 'cv_ai_sok', 'cvai_shortcode' );
function cvai_shortcode( $atts ) {
	$lang = cvai_current_lang();
	$atts = shortcode_atts(
		array(
			'platshallare'  => '',
			'knapp'         => '',
			'exempel'       => 'ja',
			'exempelfragor' => '', // egna exempel för just den här sidan, separerade med |
		),
		$atts,
		'cv_ai_sok'
	);

	wp_enqueue_style( 'cvai', CVAI_URL . 'assets/cvai.css', array(), CVAI_VERSION );
	wp_enqueue_script( 'cvai', CVAI_URL . 'assets/cvai.js', array(), CVAI_VERSION, true );

	$examples = array();
	if ( 'nej' !== $atts['exempel'] && 'no' !== $atts['exempel'] ) {
		if ( $atts['exempelfragor'] ) {
			$examples = array_map( 'trim', explode( '|', $atts['exempelfragor'] ) );
		} else {
			$examples = array_map( 'trim', explode( "\n", cvai_examples_for( $lang ) ) );
		}
		$examples = array_values( array_filter( $examples ) );
	}

	wp_localize_script(
		'cvai',
		'cvaiData',
		array(
			'endpoint' => esc_url_raw( rest_url( 'cvai/v1/search' ) ),
			'lang'     => $lang,
			'examples' => $examples,
			'i18n'     => cvai_js_strings( $lang ),
		)
	);

	$placeholder = $atts['platshallare'] ? $atts['platshallare'] : cvai_t( 'placeholder', $lang );
	$button      = $atts['knapp'] ? $atts['knapp'] : cvai_t( 'button', $lang );

	ob_start();
	?>
	<div class="cvai" data-cvai lang="<?php echo esc_attr( $lang ); ?>" style="<?php echo esc_attr( cvai_style_vars() ); ?>">
		<form class="cvai-form" novalidate>
			<label class="screen-reader-text" for="cvai-q"><?php echo esc_html( cvai_t( 'label', $lang ) ); ?></label>
			<textarea id="cvai-q" class="cvai-q" rows="3" maxlength="600" placeholder="<?php echo esc_attr( $placeholder ); ?>"></textarea>
			<div class="cvai-row">
				<span class="cvai-hint"><?php echo esc_html( cvai_t( 'hint', $lang ) ); ?></span>
				<button type="submit" class="cvai-go"><?php echo esc_html( $button ); ?></button>
			</div>
		</form>
		<div class="cvai-chips"></div>
		<div class="cvai-status" aria-live="polite"></div>
		<div class="cvai-out"></div>
	</div>
	<?php
	return ob_get_clean();
}

/** Exempelfrågor för ett språk: egna från inställningarna, annars inbyggda. */
function cvai_examples_for( $lang ) {
	$is_default = ! cvai_has_wpml() || $lang === cvai_default_lang();
	if ( $is_default ) {
		$own = trim( (string) cvai_opt( 'examples' ) );
	} else {
		$by  = (array) cvai_opt( 'examples_by_lang' );
		$own = trim( (string) ( $by[ $lang ] ?? '' ) );
	}
	return '' !== $own ? $own : cvai_t( 'examples', $lang );
}

function cvai_style_vars() {
	$map = array(
		'color_text'     => '--cvai-text',
		'color_muted'    => '--cvai-muted',
		'color_button'   => '--cvai-green',
		'color_btn_text' => '--cvai-btn-text',
		'color_accent'   => '--cvai-accent',
		'color_card'     => '--cvai-card',
		'color_border'   => '--cvai-line',
	);
	$css = '';
	foreach ( $map as $opt => $var ) {
		$c = sanitize_hex_color( (string) cvai_opt( $opt ) );
		if ( $c ) {
			$css .= $var . ':' . $c . ';';
		}
	}
	$css .= '--cvai-radius:' . max( 0, min( 40, (int) cvai_opt( 'radius' ) ) ) . 'px;';
	return $css;
}

/* -------------------------------------------------------------------------
 * Admin: Inställningar → AI-sök
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_options_page( 'AI-sök', 'AI-sök', 'manage_options', 'cvai', 'cvai_admin_page' );
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'settings_page_cvai' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".cvai-color").wpColorPicker();});' );
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'cvai',
			'cvai_options',
			array(
				'sanitize_callback' => 'cvai_sanitize_options',
			)
		);
	}
);

function cvai_sanitize_options( $in ) {
	$old = wp_parse_args( get_option( 'cvai_options', array() ), cvai_defaults() );
	$out = $old;
	if ( isset( $in['api_key'] ) && '' !== trim( $in['api_key'] ) && false === strpos( $in['api_key'], '•' ) ) {
		$out['api_key'] = sanitize_text_field( $in['api_key'] );
	}
	$out['model']          = sanitize_text_field( $in['model'] ?? $old['model'] );
	$out['trip_tags']      = sanitize_text_field( $in['trip_tags'] ?? $old['trip_tags'] );
	$out['include_events'] = empty( $in['include_events'] ) ? 0 : 1;
	$out['log_enabled']    = empty( $in['log_enabled'] ) ? 0 : 1;
	$out['rate_per_hour']  = max( 1, (int) ( $in['rate_per_hour'] ?? 20 ) );
	$out['daily_cap']      = max( 1, (int) ( $in['daily_cap'] ?? 500 ) );
	$out['examples']       = sanitize_textarea_field( $in['examples'] ?? '' );
	$out['examples_by_lang'] = array();
	foreach ( (array) ( $in['examples_by_lang'] ?? array() ) as $l => $txt ) {
		$out['examples_by_lang'][ sanitize_key( $l ) ] = sanitize_textarea_field( (string) $txt );
	}
	$out['extra_rules']    = sanitize_textarea_field( $in['extra_rules'] ?? '' );
	$out['max_hits']       = max( 1, min( 8, (int) ( $in['max_hits'] ?? 4 ) ) );
	$out['max_alts']       = max( 0, min( 6, (int) ( $in['max_alts'] ?? 2 ) ) );
	$out['radius']         = max( 0, min( 40, (int) ( $in['radius'] ?? 14 ) ) );
	$out['show_images']    = empty( $in['show_images'] ) ? 0 : 1;
	foreach ( array_keys( cvai_color_keys() ) as $ck ) {
		$c          = sanitize_hex_color( (string) ( $in[ $ck ] ?? '' ) );
		$out[ $ck ] = $c ? $c : cvai_defaults()[ $ck ];
	}
	cvai_flush_catalog();
	return $out;
}

function cvai_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;

	$langs = cvai_active_langs();

	if ( isset( $_POST['cvai_rebuild'] ) && check_admin_referer( 'cvai_rebuild' ) ) {
		cvai_flush_catalog();
		foreach ( array_keys( $langs ) as $l ) {
			cvai_get_catalog( $l, true );
		}
		echo '<div class="notice notice-success"><p>Resekatalogen är ombyggd för alla språk.</p></div>';
	}

	$preview_lang = isset( $_GET['cvai_lang'] ) ? sanitize_key( wp_unslash( $_GET['cvai_lang'] ) ) : cvai_default_lang();
	if ( ! isset( $langs[ $preview_lang ] ) ) {
		$preview_lang = key( $langs );
	}

	$o        = wp_parse_args( get_option( 'cvai_options', array() ), cvai_defaults() );
	$has_key  = (bool) cvai_opt( 'api_key' );
	$from_cfg = defined( 'CVAI_API_KEY' ) && CVAI_API_KEY;
	$table    = $wpdb->prefix . 'cvai_log';
	$rows     = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC LIMIT 200" ); // phpcs:ignore
	$today    = (int) get_transient( 'cvai_day_' . current_time( 'Ymd' ) );
	$catalogs = array();
	foreach ( array_keys( $langs ) as $l ) {
		$catalogs[ $l ] = cvai_get_catalog( $l );
	}
	?>
	<div class="wrap">
		<h1>AI-sök</h1>
		<p>Lägg shortcoden <code>[cv_ai_sok]</code> på startsidan i varje språk (t.ex. i ett Avada Text Block). Rubrik och ingress skriver du själv ovanför. Sökrutan, knappar och resultat visas automatiskt på sidans språk.</p>
		<p>Valfria attribut: <code>platshallare="…"</code>, <code>knapp="…"</code>, <code>exempel="nej"</code>, <code>exempelfragor="Fråga 1|Fråga 2|Fråga 3"</code> (egna exempel för just den sidan – praktiskt per språk).</p>

		<h2>Status</h2>
		<ul>
			<li><strong>API-nyckel:</strong> <?php echo $has_key ? ( $from_cfg ? 'satt i wp-config.php ✔' : 'sparad ✔' ) : '<span style="color:#b32d2e">saknas</span>'; ?></li>
			<li><strong>WPML:</strong> <?php echo cvai_has_wpml() ? 'aktivt ✔ – en katalog per språk' : 'inte aktivt – en katalog'; ?></li>
			<li><strong>Sökningar i dag:</strong> <?php echo (int) $today; ?> av max <?php echo (int) $o['daily_cap']; ?></li>
		</ul>
		<table class="widefat" style="max-width:700px">
			<thead><tr><th>Språk</th><th>Resor</th><th>Guidade avgångar</th><th>Storlek</th><th>Byggd</th></tr></thead>
			<tbody>
			<?php foreach ( $catalogs as $l => $c ) : ?>
				<tr>
					<td><?php echo esc_html( $langs[ $l ] . ' (' . $l . ')' ); ?></td>
					<td><?php echo (int) $c['count']; ?></td>
					<td><?php echo (int) $c['events']; ?></td>
					<td>ca <?php echo esc_html( number_format_i18n( mb_strlen( $c['text'] ) / 4 ) ); ?> tokens</td>
					<td><?php echo esc_html( $c['built'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" style="margin-top:10px"><?php wp_nonce_field( 'cvai_rebuild' ); ?><button class="button" name="cvai_rebuild" value="1">Bygg om resekatalogen nu</button></form>

		<h2>Inställningar</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'cvai' ); ?>
			<table class="form-table">
				<tr><th>Anthropic API-nyckel</th><td>
					<?php if ( $from_cfg ) : ?>
						<em>Sätts i wp-config.php via <code>CVAI_API_KEY</code>.</em>
					<?php else : ?>
						<input type="password" class="regular-text" name="cvai_options[api_key]" value="<?php echo $o['api_key'] ? '••••••••' : ''; ?>" autocomplete="off">
						<p class="description">Säkrast är att i stället lägga <code>define( 'CVAI_API_KEY', 'sk-ant-…' );</code> i wp-config.php.</p>
					<?php endif; ?>
				</td></tr>
				<tr><th>Modell</th><td><input class="regular-text" name="cvai_options[model]" value="<?php echo esc_attr( $o['model'] ); ?>"><p class="description">Standard: <code>claude-sonnet-5-5</code> (följer reglerna bra, ca 0,20–1,20 kr per sökning). <code>claude-haiku-5-5</code> är billigast men följer extraregler sämre. <code>claude-opus-5-5</code> är dyrast och långsammast.</p></td></tr>
				<tr><th>Taggar som markerar en resa</th><td><input class="regular-text" name="cvai_options[trip_tags]" value="<?php echo esc_attr( $o['trip_tags'] ); ?>"><p class="description">Kommaseparerat, på huvudspråket. Översatta taggar i WPML hittas automatiskt. Vanliga varianter (guidet, selvguidet, guided, self-guided m.fl.) känns också igen. Lägg till fler namn här om ett språk använder andra ord.</p></td></tr>
				<tr><th>Guidade avgångar</th><td><label><input type="checkbox" name="cvai_options[include_events]" value="1" <?php checked( $o['include_events'] ); ?>> Ta med kommande evenemang från The Events Calendar</label></td></tr>
				<tr><th>Max sökningar per besökare/timme</th><td><input type="number" min="1" name="cvai_options[rate_per_hour]" value="<?php echo (int) $o['rate_per_hour']; ?>"></td></tr>
				<tr><th>Max sökningar per dygn (totalt)</th><td><input type="number" min="1" name="cvai_options[daily_cap]" value="<?php echo (int) $o['daily_cap']; ?>"><p class="description">Kostnadsspärr för alla språk tillsammans.</p></td></tr>
				<tr><th>Exempelfrågor</th><td>
					<p class="description" style="margin-bottom:8px">En fråga per rad. Lämna ett språk tomt för att använda de inbyggda exemplen, som visas i grått.</p>
					<?php foreach ( $langs as $l => $lname ) : ?>
						<?php $is_def = ! cvai_has_wpml() || $l === cvai_default_lang(); ?>
						<p style="margin:10px 0 4px"><strong><?php echo esc_html( $lname ); ?></strong></p>
						<textarea class="large-text" rows="4" placeholder="<?php echo esc_attr( cvai_t( 'examples', $l ) ); ?>"
							name="<?php echo $is_def ? 'cvai_options[examples]' : 'cvai_options[examples_by_lang][' . esc_attr( $l ) . ']'; ?>"><?php
							echo esc_textarea( $is_def ? $o['examples'] : ( $o['examples_by_lang'][ $l ] ?? '' ) );
						?></textarea>
					<?php endforeach; ?>
				</td></tr>
				<tr><th>Extra regler till AI:n</th><td><textarea class="large-text" rows="4" name="cvai_options[extra_rules]"><?php echo esc_textarea( $o['extra_rules'] ); ?></textarea><p class="description">Gäller alla språk. T.ex. "Lyft gärna fram tågresor för kunder som vill undvika flyg."</p></td></tr>
				<tr><th>Sökhistorik</th><td><label><input type="checkbox" name="cvai_options[log_enabled]" value="1" <?php checked( $o['log_enabled'] ); ?>> Spara sökningar (utan IP-adress)</label></td></tr>
			</table>

			<h2>Antal svar</h2>
			<table class="form-table">
				<tr><th>Max antal träffar</th><td><input type="number" min="1" max="8" name="cvai_options[max_hits]" value="<?php echo (int) $o['max_hits']; ?>"><p class="description">Rubriken "Bäst för er". 1–8.</p></td></tr>
				<tr><th>Max antal alternativ</th><td><input type="number" min="0" max="6" name="cvai_options[max_alts]" value="<?php echo (int) $o['max_alts']; ?>"><p class="description">Rubriken "Kan också passa". 0 = visa inga alternativ.</p></td></tr>
			</table>

			<h2>Utseende</h2>
			<table class="form-table">
				<?php foreach ( cvai_color_keys() as $ck => $info ) : ?>
					<tr><th><?php echo esc_html( $info[0] ); ?></th><td>
						<input type="text" class="cvai-color" name="cvai_options[<?php echo esc_attr( $ck ); ?>]" value="<?php echo esc_attr( $o[ $ck ] ); ?>" data-default-color="<?php echo esc_attr( cvai_defaults()[ $ck ] ); ?>">
						<?php if ( $info[1] ) : ?><p class="description"><?php echo esc_html( $info[1] ); ?></p><?php endif; ?>
					</td></tr>
				<?php endforeach; ?>
				<tr><th>Hörnradie</th><td><input type="number" min="0" max="40" name="cvai_options[radius]" value="<?php echo (int) $o['radius']; ?>"> px <p class="description">0 = raka hörn.</p></td></tr>
				<tr><th>Bilder i resultaten</th><td><label><input type="checkbox" name="cvai_options[show_images]" value="1" <?php checked( $o['show_images'] ); ?>> Visa resans utvalda bild</label></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2>Senaste sökningarna</h2>
		<p>Här ser du vad kunderna faktiskt efterfrågar – och vad de inte hittar (status <em>no_hits</em>).</p>
		<table class="widefat striped">
			<thead><tr><th style="width:130px">Tid</th><th style="width:50px">Språk</th><th>Sökning</th><th>Tolkning</th><th>Träffar</th><th style="width:70px">ms</th><th style="width:80px">Status</th></tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="7">Inga sökningar ännu.</td></tr>
			<?php endif; ?>
			<?php foreach ( (array) $rows as $r ) : ?>
				<?php
				$rl     = $r->lang ?? '';
				$cards  = isset( $catalogs[ $rl ] ) ? $catalogs[ $rl ]['cards'] : array();
				$titles = array();
				foreach ( array_filter( explode( ',', (string) $r->hits ) ) as $id ) {
					$titles[] = $cards[ $id ]['title'] ?? $id;
				}
				$interp = json_decode( (string) $r->interpretation, true );
				?>
				<tr>
					<td><?php echo esc_html( $r->created ); ?></td>
					<td><?php echo esc_html( $rl ); ?></td>
					<td><?php echo esc_html( $r->query ); ?></td>
					<td><?php echo esc_html( is_array( $interp ) ? implode( ' · ', $interp ) : '' ); ?></td>
					<td><?php echo esc_html( implode( ', ', $titles ) ); ?></td>
					<td><?php echo (int) $r->ms; ?></td>
					<td><?php echo esc_html( $r->status ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2>Förhandsgranska katalogen</h2>
		<p>Så här ser resedatan ut som AI:n får läsa. Saknas nivå eller månad här, saknas den också för sökningen.</p>
		<p>
		<?php foreach ( $langs as $l => $name ) : ?>
			<a class="button <?php echo $l === $preview_lang ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'cvai_lang', $l ) ); ?>"><?php echo esc_html( $name ); ?></a>
		<?php endforeach; ?>
		</p>
		<textarea class="large-text code" rows="14" readonly><?php echo esc_textarea( mb_substr( $catalogs[ $preview_lang ]['text'], 0, 30000 ) ); ?></textarea>
	</div>
	<?php
}

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=cvai' ) ) . '">Inställningar</a>' );
		return $links;
	}
);
