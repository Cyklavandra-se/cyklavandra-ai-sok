<?php
/**
 * Uppdateringar från GitHub-releaser.
 *
 * Pluginhuvudet har "Update URI: https://github.com/…", så WordPress frågar inte
 * wordpress.org utan anropar filtret update_plugins_github.com (WP 5.8+). Vi hämtar
 * senaste releasen via GitHubs API och erbjuder den bifogade cyklavandra-ai-sok.zip.
 * Releaser utan zip (t.ex. medan GitHub Actions fortfarande bygger) hoppas över.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CVAI_GITHUB_REPO', 'Cyklavandra-se/cyklavandra-ai-sok' );
define( 'CVAI_UPDATE_ASSET', 'cyklavandra-ai-sok.zip' );
define( 'CVAI_UPDATE_SLUG', 'cyklavandra-ai-sok' );

/**
 * Senaste releasen som har en färdig zip, eller null.
 * Cachas 6 timmar (1 timme om inget hittades) för att inte slå i GitHubs gräns
 * på 60 anrop/timme utan inloggning.
 *
 * @return array|null { version, package, url, changelog, published }
 */
function cvai_updater_release( $force = false ) {
	$cached = get_site_transient( 'cvai_gh_release' );
	if ( ! $force && is_array( $cached ) ) {
		return empty( $cached['version'] ) ? null : $cached;
	}

	$release = array();
	$res     = wp_remote_get(
		'https://api.github.com/repos/' . CVAI_GITHUB_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'cyklavandra-ai-sok/' . CVAI_VERSION,
			),
		)
	);

	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_array( $data ) && empty( $data['draft'] ) && empty( $data['prerelease'] ) && ! empty( $data['tag_name'] ) ) {
			foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
				if ( isset( $asset['name'], $asset['browser_download_url'] ) && CVAI_UPDATE_ASSET === $asset['name'] ) {
					$release = array(
						'version'   => ltrim( (string) $data['tag_name'], 'vV' ),
						'package'   => (string) $asset['browser_download_url'],
						'url'       => (string) ( $data['html_url'] ?? 'https://github.com/' . CVAI_GITHUB_REPO ),
						'changelog' => (string) ( $data['body'] ?? '' ),
						'published' => (string) ( $data['published_at'] ?? '' ),
					);
					break;
				}
			}
		}
	}

	set_site_transient( 'cvai_gh_release', $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release ? $release : null;
}

/**
 * Svarar WordPress när den letar efter uppdateringar för tillägg med Update URI på github.com.
 */
add_filter(
	'update_plugins_github.com',
	function ( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( CVAI_FILE ) !== $plugin_file ) {
			return $update;
		}
		// "Sök igen" under Kontrollpanel → Uppdateringar hämtar färskt från GitHub.
		$force   = is_admin() && ! empty( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$release = cvai_updater_release( $force );
		if ( ! $release ) {
			return $update;
		}
		return array(
			'id'           => 'https://github.com/' . CVAI_GITHUB_REPO,
			'slug'         => CVAI_UPDATE_SLUG,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires_php' => '7.4',
		);
	},
	10,
	3
);

/**
 * "Visa detaljer" i tilläggslistan: visar releasens text från GitHub.
 */
add_filter(
	'plugins_api',
	function ( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || CVAI_UPDATE_SLUG !== $args->slug ) {
			return $result;
		}
		$release = cvai_updater_release();
		if ( ! $release ) {
			return $result;
		}
		$changelog = '' !== trim( $release['changelog'] )
			? wpautop( esc_html( $release['changelog'] ) )
			: '<p>Se <a href="' . esc_url( $release['url'] ) . '">releasen på GitHub</a>.</p>';

		return (object) array(
			'name'          => 'Cykla & Vandra – AI-sök',
			'slug'          => CVAI_UPDATE_SLUG,
			'version'       => $release['version'],
			'author'        => 'Cykla &amp; Vandra',
			'homepage'      => 'https://github.com/' . CVAI_GITHUB_REPO,
			'download_link' => $release['package'],
			'requires_php'  => '7.4',
			'last_updated'  => $release['published'],
			'sections'      => array(
				'changelog' => $changelog,
			),
		);
	},
	10,
	3
);

/**
 * Ser till att den uppackade mappen får samma namn som den installerade,
 * även om pluginen ligger i t.ex. "cyklavandra-ai-sok-main".
 */
add_filter(
	'upgrader_source_selection',
	function ( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( empty( $hook_extra['plugin'] ) || plugin_basename( CVAI_FILE ) !== $hook_extra['plugin'] || ! $wp_filesystem ) {
			return $source;
		}
		$wanted = trailingslashit( $remote_source ) . dirname( $hook_extra['plugin'] ) . '/';
		if ( '.' === dirname( $hook_extra['plugin'] ) || untrailingslashit( $source ) === untrailingslashit( $wanted ) ) {
			return $source;
		}
		if ( $wp_filesystem->move( $source, $wanted, true ) ) {
			return $wanted;
		}
		return new WP_Error( 'cvai_rename_failed', 'AI-sök: kunde inte byta namn på uppdateringsmappen.' );
	},
	10,
	4
);

// Töm cachen efter en uppdatering så att nästa koll blir färsk.
add_action(
	'upgrader_process_complete',
	function ( $upgrader, $extra ) {
		// Enskild uppdatering skickar 'plugin', massuppdatering 'plugins'.
		$plugins = array_merge( (array) ( $extra['plugins'] ?? array() ), (array) ( $extra['plugin'] ?? array() ) );
		if ( isset( $extra['type'] ) && 'plugin' === $extra['type'] && in_array( plugin_basename( CVAI_FILE ), $plugins, true ) ) {
			delete_site_transient( 'cvai_gh_release' );
		}
	},
	10,
	2
);

/**
 * Länken "Sök efter uppdatering" i tilläggslistan.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( CVAI_FILE ),
	function ( $links ) {
		if ( current_user_can( 'update_plugins' ) ) {
			$url     = wp_nonce_url( admin_url( 'admin-post.php?action=cvai_check_update' ), 'cvai_check_update' );
			$links[] = '<a href="' . esc_url( $url ) . '">Sök efter uppdatering</a>';
		}
		return $links;
	}
);

add_action(
	'admin_post_cvai_check_update',
	function () {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( 'Behörighet saknas.' );
		}
		check_admin_referer( 'cvai_check_update' );
		delete_site_transient( 'cvai_gh_release' );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}
);
