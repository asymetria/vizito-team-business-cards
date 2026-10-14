<?php
/**
 * WordPress functions Vizito Team Business Cards calls that the Store Locator's stubs
 * never needed. Same rules as bootstrap.php: dumb, recording, reset per case.
 *
 * @package Vizito_Team_Business_Cards
 */

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		$email = trim( (string) $email );
		return is_email( $email ) ? $email : '';
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( (string) $title );
		$title = preg_replace( '/[^a-z0-9\s-]/', '', $title );
		$title = preg_replace( '/[\s-]+/', '-', $title );
		return trim( $title, '-' );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $key, $value = '' ) {
		$GLOBALS['tbcards_stub']['meta_deleted'][] = array(
			'id'  => (int) $post_id,
			'key' => $key,
		);
		unset( $GLOBALS['tbcards_stub']['post_meta'][ $post_id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $post = null ) {
		$id = is_object( $post ) ? ( $post->ID ?? 0 ) : (int) $post;
		return (int) ( $GLOBALS['tbcards_stub']['thumbnails'][ $id ] ?? 0 );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) {
		$id = is_object( $post ) ? ( $post->ID ?? 0 ) : (int) $post;
		return $GLOBALS['tbcards_stub']['permalinks'][ $id ] ?? home_url( '?p=' . $id );
	}
}

if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {
		$GLOBALS['tbcards_stub']['rewrite_rules'][] = compact( 'regex', 'query', 'after' );
	}
}

if ( ! function_exists( 'add_rewrite_tag' ) ) {
	function add_rewrite_tag( $tag, $regex, $query = '' ) {
		$GLOBALS['tbcards_stub']['rewrite_tags'][] = compact( 'tag', 'regex', 'query' );
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( $hard = true ) {
		$GLOBALS['tbcards_stub']['rewrite_flushes']++;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $post_types = '' ) {
		if ( '' === $post_types || array() === $post_types ) {
			return ! empty( $GLOBALS['tbcards_stub']['is_singular'] );
		}

		foreach ( (array) $post_types as $type ) {
			if ( in_array( $type, $GLOBALS['tbcards_stub']['is_singular'], true ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( $var, $default = '' ) {
		return $GLOBALS['tbcards_stub']['query_vars'][ $var ] ?? $default;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return (int) ( $GLOBALS['tbcards_stub']['query_vars']['__queried_id'] ?? 0 );
	}
}

if ( ! function_exists( 'locate_template' ) ) {
	function locate_template( $names, $load = false, $once = true ) {
		foreach ( (array) $names as $name ) {
			if ( isset( $GLOBALS['tbcards_stub']['located_templates'][ $name ] ) ) {
				return $GLOBALS['tbcards_stub']['located_templates'][ $name ];
			}
		}
		return '';
	}
}

if ( ! function_exists( 'switch_to_locale' ) ) {
	function switch_to_locale( $locale ) {
		$GLOBALS['tbcards_stub']['locale_switches'][] = $locale;

		// Core refuses a locale whose language pack is not installed.
		return ! in_array( $locale, $GLOBALS['tbcards_stub']['locales_missing'] ?? array(), true );
	}
}

if ( ! function_exists( 'restore_previous_locale' ) ) {
	function restore_previous_locale() {
		$GLOBALS['tbcards_stub']['locale_restores']++;
		return true;
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( $id, $size = 'thumbnail', $icon = false ) {
		return $GLOBALS['tbcards_stub']['attachment_urls'][ (int) $id ] ?? false;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return $GLOBALS['tbcards_stub']['bloginfo'][ $show ] ?? '';
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) {
		return addslashes( (string) $text );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = null ) {
		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr( $text ?? 'Save Changes' ) . '"></p>';
	}
}

if ( ! function_exists( 'wp_enqueue_media' ) ) {
	function wp_enqueue_media( $args = array() ) {
		$GLOBALS['tbcards_stub']['media_enqueued'] = true;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = null ) {
		return esc_attr( __( $text, $domain ) );
	}
}

if ( ! function_exists( '__return_false' ) ) {
	function __return_false() {
		return false;
	}
}

if ( ! function_exists( 'add_settings_error' ) ) {
	function add_settings_error( $setting, $code, $message, $type = 'error' ) {
		$GLOBALS['tbcards_stub']['settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
	}
}

if ( ! function_exists( 'get_attached_file' ) ) {
	function get_attached_file( $id, $unfiltered = false ) {
		return $GLOBALS['tbcards_stub']['attached_files'][ (int) $id ] ?? false;
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		$nonce = $_REQUEST[ $query_arg ] ?? ''; // phpcs:ignore WordPress.Security
		$ok    = wp_verify_nonce( $nonce, $action );

		if ( ! $ok ) {
			wp_die( 'The link you followed has expired.' );
		}

		return $ok;
	}
}

if ( ! function_exists( 'get_temp_dir' ) ) {
	function get_temp_dir() {
		return rtrim( sys_get_temp_dir(), '/' . chr( 92 ) ) . '/';
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
}

if ( ! function_exists( 'remove_accents' ) ) {
	/**
	 * Enough of core's remove_accents() for Polish and German names.
	 */
	function remove_accents( $text ) {
		return strtr(
			(string) $text,
			array(
				'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
				'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z',
				'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U',
			)
		);
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $queries = '', $execute = true ) {
		$GLOBALS['tbcards_stub']['db_delta'][] = $queries;
		return array();
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		return $GLOBALS['tbcards_stub']['cron'][ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		$GLOBALS['tbcards_stub']['cron'][ $hook ] = $timestamp;
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		$had = isset( $GLOBALS['tbcards_stub']['cron'][ $hook ] ) ? 1 : 0;
		unset( $GLOBALS['tbcards_stub']['cron'][ $hook ] );
		return $had;
	}
}

if ( ! class_exists( 'Tbcards_Fake_Db' ) ) {
	/**
	 * A database that records prepared SQL and answers from a queue. For the
	 * statistics table, whose SQL the uninstall-shaped Tbcards_Stub_Wpdb cannot read.
	 */
	class Tbcards_Fake_Db {
		public $prefix = 'wp_';
		public $queries = array();
		public $results = array();

		public function prepare( $query, ...$args ) {
			if ( 1 === count( $args ) && is_array( $args[0] ) ) {
				$args = $args[0];
			}
			$i = 0;
			return (string) preg_replace_callback(
				'/%[sd]/',
				static function ( $m ) use ( &$i, $args ) {
					$v = $args[ $i++ ];
					return '%d' === $m[0] ? (string) (int) $v : "'" . addslashes( (string) $v ) . "'";
				},
				$query
			);
		}

		public function query( $sql ) {
			$this->queries[] = $sql;
			return 1;
		}

		public function get_results( $sql, $output = 'OBJECT' ) {
			$this->queries[] = $sql;
			return array_shift( $this->results ) ?? array();
		}

		public function get_charset_collate() {
			return 'DEFAULT CHARSET=utf8mb4';
		}
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'test-salt-' . $scheme;
	}
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) {
		return is_object( $post ) ? (string) ( $post->post_title ?? '' ) : '';
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr, $wp_error = false ) {
		$postarr = wp_unslash( $postarr );
		$id      = (int) ( $GLOBALS['tbcards_stub']['next_post_id'] ?? 100 );

		$GLOBALS['tbcards_stub']['next_post_id']     = $id + 1;
		$GLOBALS['tbcards_stub']['inserted_posts'][] = $postarr;
		$GLOBALS['tbcards_stub']['posts_by_id'][ $id ] = (object) array_merge(
			array(
				'ID'        => $id,
				'post_name' => sanitize_title( $postarr['post_title'] ?? '' ),
			),
			$postarr
		);

		return $id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr = array(), $wp_error = false ) {
		$GLOBALS['tbcards_stub']['updated_posts'][] = wp_unslash( $postarr );
		return (int) ( $postarr['ID'] ?? 0 );
	}
}

if ( ! function_exists( 'set_post_thumbnail' ) ) {
	function set_post_thumbnail( $post, $thumbnail_id ) {
		$id = is_object( $post ) ? $post->ID : (int) $post;
		$GLOBALS['tbcards_stub']['thumbnails'][ $id ] = (int) $thumbnail_id;
		return true;
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $name ) {
		return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name );
	}
}

if ( ! function_exists( 'wp_basename' ) ) {
	function wp_basename( $path, $suffix = '' ) {
		return basename( str_replace( chr( 92 ), '/', (string) $path ), $suffix );
	}
}

if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( $handle, $data ) {
		if ( ! isset( $GLOBALS['tbcards_stub']['styles'][ $handle ] ) ) {
			return false;
		}
		$GLOBALS['tbcards_stub']['styles'][ $handle ]['inline'][] = $data;
		return true;
	}
}

if ( ! function_exists( 'register_block_type' ) ) {
	function register_block_type( $block_type, $args = array() ) {
		$GLOBALS['tbcards_stub']['blocks'][] = compact( 'block_type', 'args' );
		return true;
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $path = false ) {
		$GLOBALS['tbcards_stub']['textdomains'][] = compact( 'domain', 'path' );
		return true;
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( (string) $file ) ) . '/' . basename( (string) $file );
	}
}

if ( ! defined( 'TBCARDS_FILE' ) ) {
	define( 'TBCARDS_FILE', dirname( __DIR__ ) . '/vizito-team-business-cards.php' );
}

if ( ! function_exists( 'settings_errors' ) ) {
	function settings_errors( $setting = '', $sanitize = false, $hide_on_update = false ) {
		foreach ( $GLOBALS['tbcards_stub']['settings_errors'] ?? array() as $error ) {
			echo '<div class="notice notice-' . esc_attr( $error['type'] ) . '"><p>' . esc_html( $error['message'] ) . '</p></div>';
		}
	}
}

if ( ! function_exists( 'wp_print_scripts' ) ) {
	/**
	 * Prints registered scripts the way core does for these tests: inline
	 * "before" data, then the tag with ?ver=.
	 */
	function wp_print_scripts( $handles = false ) {
		foreach ( (array) $handles as $handle ) {
			$script = $GLOBALS['tbcards_stub']['scripts'][ $handle ] ?? null;

			if ( null === $script ) {
				continue;
			}

			foreach ( $script['extra']['before'] ?? array() as $inline ) {
				echo "<script>
" . $inline . "
</script>
";
			}

			echo '<script src="' . esc_url( $script['src'] . '?ver=' . $script['ver'] ) . '" defer></script>' . "
";
		}

		return array();
	}
}

if ( ! function_exists( 'load_textdomain' ) ) {
	function load_textdomain( $domain, $mofile, $locale = null ) {
		$GLOBALS['tbcards_stub']['textdomain_log'][] = 'load:' . $domain . ':' . basename( (string) $mofile );
		return is_readable( $mofile );
	}
}

if ( ! function_exists( 'unload_textdomain' ) ) {
	function unload_textdomain( $domain, $reloadable = false ) {
		$GLOBALS['tbcards_stub']['textdomain_log'][] = 'unload:' . $domain;
		return true;
	}
}
