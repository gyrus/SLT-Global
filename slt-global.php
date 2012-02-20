<?php

/**
 * Plugin Name: SLT Global
 * Plugin URI: http://sltaylor.co.uk/
 * Description: Steve Taylor's global WordPress modifications and functions library
 * Version: 1.0
 * Author: Steve Taylor
 * Author URI: http://sltaylor.co.uk/
 * License: GPL2
 *
 * @package SLT_Global
 *
 */


// Disable theme and plugin editing through the admin
define( 'DISALLOW_FILE_EDIT', true );


/* Admin-only stuff
*****************************************************************************/
if ( is_admin() ) :

// Disable WP upgrade notification for non-admins
add_action( 'admin_init', 'slt_disable_upgrade_notification' );
function slt_disable_upgrade_notification() {
	if ( ! current_user_can( 'update_core' ) ) {
		remove_action( 'init', 'wp_version_check' );
		add_filter( 'pre_option_update_core', create_function( '$a', "return null;" ) );
	}
}

// Only check for updates for active plugins
// From: http://wordpress.org/extend/plugins/update-active-plugins-only/
add_filter( 'http_request_args', 'slt_update_active_plugins_only', 10, 2 );
function slt_update_active_plugins_only( $r, $url ) {
	if ( 0 === strpos( $url, 'http://api.wordpress.org/plugins/update-check/' ) ) {
		$plugins = unserialize( $r['body']['plugins'] );
		$plugins->plugins = array_intersect_key( $plugins->plugins, array_flip( $plugins->active ) );
		$r['body']['plugins'] = serialize( $plugins );
	}
	return $r;
}

// Nav menus hidden columns
add_filter( 'get_user_option_managenav-menuscolumnshidden', 'slt_nav_menus_columns_hidden' );
function slt_nav_menus_columns_hidden( $result ) {
	// Description always on
	if ( in_array( 'description', $result ) )
		unset( $result[ array_search( 'description', $result ) ] );
	return $result;
}

/**
 * Output an admin settings field
 *
 * @param string $name A name for the options field. If SLT_THEME_SHORTNAME is defined, this is used as a prefix. If the format 'option_group_name[option_name]' is used, the option 'option_name' will be stored as part of the single 'option_group_name' entry in the options table.
 * @param string $label A label for the field
 * @param string $type text | textarea | select | checkbox | file (requires Developer's Custom Fields plugin)
 * @param array $options For populating selects etc. If $options['auto_populate'] is set to 'number_range', $options['start'], $options['end'] and $options['increment'] will be used to populate select options.
 * @param string $note A note to output with the field
 * @param mixed $default A default value
 * @uses wp_kses()
 * @uses esc_attr()
 * @uses slt_cf_file_select_button()
 * @uses selected()
 * @uses checked()
 */
function slt_admin_setting_field( $name, $label, $type = 'text', $options = array(), $note = '', $default = '' ) {
	if ( defined( 'SLT_THEME_SHORTNAME' ) && substr( $name, 0, strlen( SLT_THEME_SHORTNAME ) ) != SLT_THEME_SHORTNAME )
		$name = SLT_THEME_SHORTNAME . '_' . $name;
	if ( preg_match( '/\[([^\]]+)\]/', $name, $matches ) ) {
		// Multiple options in one option array
		$name_root = str_replace( $matches[0], '', $name );
		$key = $matches[1];
		$value = get_option( $name_root, $default );
		if ( is_array( $value ) )
			$value = $value[ $key ];
	} else {
		// Ordinary value
		$value = get_option( $name, $default );
	}
	// Auto-population of options
	if ( is_array( $options ) && isset( $options['auto_populate'] ) ) {
		$new_options = array();
		switch( $options['auto_populate'] ) {
			case "number_range":
				$options['increment'] = isset( $options['increment'] ) ? intval( $options['increment'] ) : 1;
				for ( $i = $options['start']; $i <= $options['end']; $i = $i + $options['increment'] )
					$new_options[ $i ] = $i;
				break;
		}
		$options = $new_options;
	}
	?>
	<tr valign="top">
		<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo wp_kses( $label, array() ); ?></label></th>
		<td>
			<?php
			switch ( $type ) {
				case 'file': {
					if ( function_exists( 'slt_cf_file_select_button' ) )
						slt_cf_file_select_button( $name, $value, __( 'Select file' ) );
					break;
				}
				case 'select': {
					if ( ! empty( $options ) ) {
						echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">' . "\n";
						foreach ( $options as $option_value => $option_label )
							echo '<option value="' . esc_attr( $option_value ) . '"' . selected( $value, $option_value, false ) . '>' . wp_kses( $option_label, array() ) . '</option>' . "\n";
						echo '</select>' . "\n";
					} else {
						echo '<em>' . __( 'No options to select from.' ) . '</em>' . "\n";
						echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="" />' . "\n";
					}
					break;
				}
				case 'textarea': {
					echo '<textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" cols="40" rows="4">' . esc_textarea( $value ) . '</textarea>';
					break;
				}
				case 'checkbox': {
					echo '<input type="checkbox" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '" value="1"' . checked( $value, true, false ) . ' />';
					break;
				}
				default: {
					echo '<input type="text" value="' . esc_attr( $value ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name) . '" class="regular-text" />';
					break;
				}
			}
			if ( $note )
				echo '<p class="description">' . $note . '</p>';
			?>
		</td>
	</tr>
	<?php
}

endif; // is_admin()


/* Users
*****************************************************************************/

// Better default user display name
add_action( 'user_register', 'slt_default_user_display_name' );
function slt_default_user_display_name( $user_id ) {
	// Fetch current user meta information
	$first = get_user_meta( $user_id, 'first_name', true );
	$last = get_user_meta( $user_id, 'last_name', true );
	$display = $first . " " . $last;
	wp_update_user( array( "ID" => $user_id, "display_name" => $display ) );
}

/**
 * Get a user's role
 *
 * @param mixed $user Either a user's ID or a user object
 * @param bool $manual If true, a "manual" check is done that avoids using WP functions; use this if the code calling this function is hooked to something that may be called by WP_User, creating an infinite loop
 * @return string
 * @uses $wpdb
 * @uses maybe_unserialize()
 * @uses WP_User
 */
function slt_get_user_role( $user, $manual = false ) {
	global $wpdb;
	$role = null;
	if ( is_int( $user ) || ctype_digit( $user ) ) {
		if ( $manual ) {
			// Manual check
			global $wpdb;
			$caps = $wpdb->get_var( $wpdb->prepare("
				SELECT	meta_value
				FROM	$wpdb->usermeta
				WHERE	user_id		= %d
				AND		meta_key	= %s
			", intval( $user ), $wpdb->prefix . "capabilities" ) );
			if ( $caps ) {
				$user = new StdClass;
				$user->roles = array_keys( maybe_unserialize( $caps ) );
			}
		} else {
			// Standard WP User
			$user = new WP_User( $user );
		}
	}
	if ( is_object( $user ) ) {
		$caps_field = $wpdb->prefix . 'capabilities';
		if ( property_exists( $user, 'roles' ) && is_array( $user->roles ) && ! empty( $user->roles ) )
			$role = $user->roles[0];
		else if ( property_exists( $user, $caps_field ) && is_array( $user->$caps_field ) && ! empty( $user->$caps_field ) )
			$role = array_shift( array_keys( $user->$caps_field ) );
	}
	return $role;
}

/**
 * Get a user with metadata
 *
 * @param integer $id The user's ID
 * @return object
 * @uses get_userdata()
 * @uses slt_get_all_user_meta()
 * @uses maybe_unserialize()
 */
function slt_get_user_with_meta( $id ) {
	$user = get_userdata( $id );
	if ( $user ) {
		$user = $user->data;
		$user_meta = slt_get_all_user_meta( $id, false );
		foreach ( $user_meta as $user_metadatum )
			$user->{$user_metadatum->meta_key} = maybe_unserialize( $user_metadatum->meta_value );
	}
	return $user;
}

/**
 * Get all a user's metadata
 *
 * @param integer $id The user's ID
 * @param bool $array Return as an associative array, or leave as array of objects?
 * @param bool $skip_hook Skip the hook that lets other functions take over? Necessary for calling this from within a function hooked to 'slt_get_all_user_meta'!
 * @return array
 * @uses $wpdb
 * @uses apply_filters()
 * @uses maybe_unserialize()
 */
function slt_get_all_user_meta( $id, $array = true, $skip_hook = false ) {
	// Allow hooks to take over?
	if ( ! $skip_hook ) {
		$check = apply_filters( 'slt_get_all_user_meta', null, $id, $array );
		if ( $check !== null )
			return $check;
	}
	global $wpdb;
	$user_meta = array();
	$user_metadata = $wpdb->get_results( $wpdb->prepare( "
		SELECT	meta_key, meta_value
		FROM	$wpdb->usermeta
		WHERE	user_id	= %d
	", $id ) );
	if ( $array ) {
		foreach ( $user_metadata as $user_metadatum )
			$user_meta[ $user_metadatum->meta_key ] = maybe_unserialize( $user_metadatum->meta_value );
	} else {
		$user_meta = $user_metadata;
	}
	return $user_meta;
}


/* Front-end output
*****************************************************************************/

// Body classes
add_filter( 'body_class', 'slt_global_body_classes' );
function slt_global_body_classes( $classes ) {
	// Browser detection
	global $is_lynx, $is_gecko, $is_IE, $is_opera, $is_safari, $is_chrome, $is_iphone, $post;
	if ( $is_lynx ) $classes[] = 'lynx';
	else if ( $is_gecko ) $classes[] = 'gecko';
	else if ( $is_opera ) $classes[] = 'opera';
	else if ( $is_safari ) $classes[] = 'safari';
	else if ( $is_chrome ) $classes[] = 'chrome';
	else if ( $is_IE ) $classes[] = 'ie';
	if ( $is_iphone ) $classes[] = 'iphone';
	// Content attributes
	if ( is_single() && ! defined( 'SLT_CATEGORIES' ) || SLT_CATEGORIES ) {
		foreach ( get_the_category( $post->ID ) as $category )
			$classes[] = "cat-" . $category->cat_ID;
	}
	// Return
	return $classes;
}

/**
 * JS variables that need to be populated from WP settings or constants
 *
 * To use this, just add the names of options in the WP options table, or the names of constants, to the global $slt_js_settings array.
 *
 */
add_action( 'init', function() { global $slt_js_settings; $slt_js_settings = array(); } );
add_action( 'wp_head', 'slt_js_settings', 1 );
function slt_js_settings() {
	global $slt_js_settings;
	if ( ! empty( $slt_js_settings ) ) {
		echo '<script type="text/javascript">' . "\n";
		foreach ( $slt_js_settings as $setting ) {
			if ( defined( $setting ) )
				echo 'var ' . $setting . ' = "' . constant( $setting ) . '";' . "\n";
			else if ( ( $option_value = get_option( $setting ) ) !== false )
				echo 'var ' . $setting . ' = "' . esc_js( $option_value ) . '";' . "\n";
		}
		echo '</script>' . "\n";
	}
}

/**
 * 'Run-early' JavaScript
 *
 * To use this, just add lines of JS that need to run directly in the foot to the global $slt_run_early_js array.
 *
 */
add_action( 'init', function() { global $slt_run_early_js; $slt_run_early_js = array(); } );
add_action( 'wp_footer', 'slt_run_early_js', 0 );
function slt_run_early_js() {
	global $slt_run_early_js;
	if ( ! empty( $slt_run_early_js ) ) {
		echo '<script type="text/javascript">' . "\n";
		echo implode( ";\n", $slt_run_early_js ) . ";\n";
		echo '</script>' . "\n";
	}
}


/* Security
*****************************************************************************/

// Remove generator meta tags
remove_action( 'wp_head', 'wp_generator' );

// Block attempted comments without a referrer
add_action( 'check_comment_flood', 'slt_check_referrer' );
function slt_check_referrer() {
	if ( !isset($_SERVER['HTTP_REFERER']) || $_SERVER['HTTP_REFERER'] == "" )
		wp_die( __( 'Please enable referrers in your browser.', 'slt-functions' ) );
}

// Block malicious requests
// See: http://perishablepress.com/press/2009/12/22/protect-wordpress-against-malicious-url-requests/
add_action( 'init', 'slt_block_malicious_requests' );
function slt_block_malicious_requests() {
	if (	( strlen( $_SERVER['REQUEST_URI'] ) > 255 && ! is_user_logged_in() ) ||
			strpos( $_SERVER['REQUEST_URI'], "eval(" ) ||
			strpos( $_SERVER['REQUEST_URI'], "base64" ) ) {
		@header( "HTTP/1.1 414 Request-URI Too Long" );
		@header( "Status: 414 Request-URI Too Long" );
		@header( "Connection: Close" );
		@exit;
	}
}


/* Some useful helper functions
*****************************************************************************/

/**
 * Return an array of values from an array of objects
 *
 * @param string $needle_key The property to search for inside the array's objects
 * @param array $haystack The array of objects
 * @return array
 */
function slt_objects_array_values( $needle_key, $haystack ) {
	$values = array();
	if ( is_array( $haystack ) ) {
		// Iterate through our haystack
		for ( $i = 0; $i < count( $haystack ); $i++ ) {
			// Ensure this array element is an object and has a key that matches our needle's key
			if ( is_object( $haystack[$i] ) && property_exists( $haystack[$i], $needle_key ) )
				$values[] = $haystack[$i]->$needle_key;
		}
	}
	return $values;
}

/**
 * Search array of objects for property value
 *
 * @param string $needle_key The key being searched for
 * @param string $needle_val The value being searched for
 * @param array $haystack An array of objects
 * @return mixed False if no match found, otherwise the index of the object in the array that has the key / value combination
 */
function slt_search_object_array( $needle_key, $needle_val, $haystack ) {
	// Iterate through our haystack
	for ( $i = 0; $i < count( $haystack ); $i++ ) {
		// Ensure this array element is an object and has a key that matches our needle's key
		if ( is_object( $haystack[$i]) and property_exists( $haystack[$i], $needle_key ) ) {
			// Do case-insensitive comparison
			if ( strtolower( $needle_val ) == strtolower( $haystack[$i]->$needle_key ) )
				return $i;
		}
	}
	// no match found
	return false;
}

/**
 * Search arrays in an array for a value, and return the key of the first matching array
 *
 * @param string $needle The value being searched for
 * @param array $haystack An array of arrays
 * @return mixed False if no match found, otherwise the index of the object in the array that has the key / value combination
 */
function slt_search_arrays_in_array( $needle, $haystack ) {
	if ( is_array( $haystack ) ) {
		foreach ( $haystack as $key => $value ) {
			if ( ( is_array( $value ) && ( array_search( $needle, $value ) !== false ) || $value == $needle ) )
				return $key;
		}
	}
	// no match found
	return false;
}

/**
 * Trim every value in an array
 *
 * @param array $array
 * @param string $charlist Optional, defaults to null (trims whitespace)
 * @return array
 */
function slt_trim_array( $array, $charlist = null ) {
	if ( is_array( $array ) ) {
		foreach ( $array as &$value ) {
			if ( is_string( $value ) ) {
				if ( $charlist )
					$value = trim( $value, $charlist );
				else
					$value = trim( $value );
			}
		}
	}
	return $array;
}

/**
 * A quick way to explode lists stored in constants into the global scope
 *
 * @param array $constants
 * @param string $sep
 */
function slt_explode_constants( $constants = array(), $sep = ',' ) {
	if ( is_array( $constants ) && count( $constants ) ) {
		foreach ( $constants as $constant ) {
			if ( defined( $constant ) ) {
				$var_name = strtolower( $constant );
				global $$var_name;
				$$var_name = explode( $sep, constant( $constant ) );
			}
		}
	}
}

/**
 * Remove magic quotes slashes
 *
 * @param string $string
 * @return string
 */
function slt_undo_magic_quotes( $string ) {
	$string = str_replace( array( "\'", '\"' ), array( "'", '"' ), $string );
	return $string;
}

/**
 * Check that array key or object property exists and there's a value
 *
 * @param string $needle The key or property name
 * @param mixed $haystack The array or object
 * @return boolean
 */
function slt_value_exists( $needle, $haystack ) {
	if ( is_array( $haystack ) )
		return array_key_exists( $needle, $haystack ) && ! empty( $haystack[ $needle ] );
	else if ( is_object( $haystack ) )
		return property_exists( $haystack, $needle ) && ! empty( $haystack->$needle );
	else
		return false;
}

/**
 * Return the formatted size of a file.
 *
 * @param mixed $input Either the path to a valid file, or a number in bytes
 * @return string The size, formatted
 */
function slt_filesize( $input ) {
	$size = null;
	$output = '??';
	// Set up some common file size measurements
	$kb = 1024;         // Kilobyte
	$mb = 1024 * $kb;   // Megabyte
	$gb = 1024 * $mb;   // Gigabyte
	$tb = 1024 * $gb;   // Terabyte
	if ( is_file( $input ) ) {
		// Get the file size in bytes
		$size = filesize( $input );
	} else if ( is_numeric( $input ) ) {
		$size = (int) $input;
	}
	if ( $size ) {
		// If it's less than a kb we just return the size,
		// otherwise we keep going until the size is in the appropriate measurement range.
		if ( $size < $kb ) {
			$output = $size . " bytes";
		} else if ( $size < $mb ) {
			$output = round( $size / $kb ) . " KB";
		} else if ( $size < $gb ) {
			$output = round( $size / $mb, 2 ) . " MB";
		} else if ( $size < $tb ) {
			$output = round( $size / $gb, 2 ) . " GB";
		} else {
			$output = round( $size / $tb, 2 ) . " TB";
		}
	}
	return $output;
}

/**
 * Return a PHP setting value (e.g. "2M" ) in bytes
 *
 * @param string $val
 * @return integer
 */
function slt_return_bytes( $val ) {
	$val = trim( $val );
	$last = strtolower( $val[ strlen( $val ) - 1 ] );
	switch ( $last ) {
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}
	return $val;
}
