<?php
/**
 * Plugin Name: MOCache
 * Description: Store .mo translations with fast hash tables in external object cache if available or in temp files.
 * Plugin URI:  https://github.com/creame/mocache/
 * Version:     1.0.0
 * Author:      Creame
 * Author URI:  https://crea.me/
 * License:     GPL3
 *
 * Store expensive translation lookups as PHP fast hashtables in WP Transients or temp files.
 *
 * This plugin is heavily inspired and is a mix with the best of:
 *  - "WordPress Translation Cache" by Pressjitsu, Inc. (https://github.com/pressjitsu/pomodoro)
 *  - "A faster load_textdomain" by Per Søderlind (https://gist.github.com/soderlind/610a9b24dbf95a678c3e)
 *
 * 2021 Creame https://crea.me
 */

namespace Creame\MOCache;

function override( $plugin_override, $domain, $mofile ) {

	$mofile = apply_filters( 'load_textdomain_mofile', $mofile, $domain );

	if ( ! is_readable( $mofile ) ) {
		return false;
	}

	global $l10n;

	$class = __NAMESPACE__ . ( wp_using_ext_object_cache() ? '\\MOTransient' : '\\MOFile' );

	$mo = new $class( $mofile, $domain, empty( $l10n[ $domain ] ) ? null : $l10n[ $domain ] );

	$l10n[ $domain ] = &$mo;

	return true;
}
add_filter( 'override_load_textdomain', __NAMESPACE__ . '\\override', 999, 3 );


abstract class MO {

	/**
	 * Protedted data (childs need access).
	 */
	protected $cache  = array();
	protected $dirty  = false;
	protected $mofile = null;
	protected $domain = null;
	protected $mtime  = null;

	/**
	 * Private data (only for MO).
	 */
	private $override = null;
	private $upstream = null;

	/**
	 * Construct the main translation cache instance for a domain.
	 *
	 * @param string       $mofile The path to the mo file.
	 * @param string       $domain The textdomain.
	 * @param Translations $merge The class in the same domain, we have overriden it.
	 */
	public function __construct( $mofile, $domain, $override ) {
		$this->mofile   = $mofile;
		$this->domain   = $domain;
		$this->override = $override;
		$this->mtime    = filemtime( $mofile );

		$this->load_cache();

		register_shutdown_function( array( $this, 'save_cache' ) );
	}

	/**
	 * Abstract functions.
	 */
	abstract protected function load_cache();
	abstract public function save_cache();

	/**
	 * The translate() function implementation that WordPress calls.
	 */
	public function translate( $text, $context = null ) {
		return $this->get_translation( $this->hash( func_get_args() ), $text, func_get_args() );
	}

	/**
	 * The translate_plural() function implementation that WordPress calls.
	 */
	public function translate_plural( $singular, $plural, $count, $context = null ) {
		$text = abs( $count ) == 1 ? $singular : $plural;
		return $this->get_translation( $this->hash( array( $text, $count, $context ) ), $text, func_get_args() );
	}

	/**
	 * Text hash calculator.
	 */
	private function hash( $args ) {
		return md5( json_encode( array( $args, $this->domain ) ) );
	}

	/**
	 * Get translation from cache, override or .mo file.
	 */
	private function get_translation( $cache_key, $text, $args ) {
		// Check cache first.
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		// Mark dirty.
		$this->dirty = true;

		$translate_function = count( $args ) > 2 ? 'translate_plural' : 'translate';

		// Merge overrides.
		if ( $this->override ) {
			return $this->cache[ $cache_key ] = call_user_func_array( array( $this->override, $translate_function ), $args );
		}

		// Default WordPress MO upstream.
		if ( ! $this->upstream ) {
			$this->upstream = new \MO();
			$this->upstream->import_from_file( $this->mofile );
		}

		return $this->cache[ $cache_key ] = call_user_func_array( array( $this->upstream, $translate_function ), $args );
	}
}


class MOFile extends MO {

	private $cache_file = null;

	/**
	 * Load cache from file.
	 */
	protected function load_cache() {
		$filename = md5( implode( ' ', array( get_home_url(), $this->domain, $this->mofile ) ) );
		$temp_dir = get_temp_dir();

		$this->cache_file = sprintf( '%s/%s.mocache', untrailingslashit( $temp_dir ), $filename );

		if ( file_exists( $this->cache_file ) ) {
			include $this->cache_file; // Load cache (OPcache will grab the values from memory)

			if ( ! isset( $_mtime ) || ( isset( $_mtime ) && $_mtime < $this->mtime ) ) {
				$this->cache = array();
			} else {
				$this->cache = &$_cache;
			}
		}
	}

	/**
	 * Save cache in file.
	 */
	public function save_cache() {
		if ( $this->dirty ) {
			file_put_contents(
				$this->cache_file,
				sprintf( '<?php $_mtime = %d; $_cache = %s;', $this->mtime, var_export( $this->cache, true ) ),
				LOCK_EX
			);
		}
	}
}


class MOTransient extends MO {

	private $cache_key = null;

	/**
	 * Load cache from transient.
	 */
	protected function load_cache() {
		$this->cache_key = 'mo__' . md5( $this->mofile );

		$data = get_transient( $this->cache_key );

		if ( ! $data || ! isset( $data['mtime'] ) || $this->mtime > $data['mtime'] ) {
			$this->cache = array();
		} else {
			$this->cache = &$data['cache'];
		}
	}

	/**
	 * Save cache in transient.
	 */
	public function save_cache() {
		if ( $this->dirty ) {
			$cache_data = array(
				'mtime' => $this->mtime,
				'cache' => $this->cache,
			);
			set_transient( $this->cache_key, $cache_data );
		}
	}
}
