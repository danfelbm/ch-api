<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'lideresapp_wp' );

/** Database username */
define( 'DB_USER', 'danielb' );

/** Database password */
define( 'DB_PASSWORD', '159753456' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '8)+hn`rG=`4C^Yp^Kd){{0:VEqJ5nK-KSF{ua#.Znth]av@}9YD-M5`{Qvof?@qP' );
define( 'SECURE_AUTH_KEY',  'D&Z &4@qclshJ5:Z{jB6D(t4x*oaKN~G]v-&V@K<oC%pcuLz8;0!5(8O-XWd-F(8' );
define( 'LOGGED_IN_KEY',    'D~+~Sb47Mee:f} -~)33G#4owpwS1~;9Rs(C&@8iii{KvarkqoPz1x$s?AKmwkVM' );
define( 'NONCE_KEY',        'Yw9UY_C@PY S)4$8M!3}6;r^^-nJv*-FvLQ&FJqu:-^toQc6!eH579~mtjsxz>+g' );
define( 'AUTH_SALT',        'yY7-SKbO6hUq7hzO8Mi(WN>T=s8Gm}Y+oeB:F7z2^pJp8 YY$P|FB;FgDs?_`=q0' );
define( 'SECURE_AUTH_SALT', 'QDBb_$HeUfqE~6T2{hCvp>!t4K!%UaJebh>0H|<^h+H<3N^@dY7`7<7|.n&sY?0?' );
define( 'LOGGED_IN_SALT',   'mm-?qJ9tp@dN:AOVQHw|#<W/Wy`(T24 yS]aZ}GXRSdKmE-^FE8^jgqfIS=sZc?,' );
define( 'NONCE_SALT',       '|IELV32cGHjBQ;2P0!TeV.u7XZScb|@E4bM/bck9uv:Y:>5s^a}^K>oZw/0d8~{V' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
