<?php

$wordpress_path = getenv( 'WPMVC_WORDPRESS_PATH' );
$wordpress_path = $wordpress_path ? rtrim( $wordpress_path, '/' ) . '/' : __DIR__ . '/../../../../';

// Allowing the CLI works with composer structure
if (!file_exists($wordpress_path . 'wp-load.php')) {
	$wordpress_path .= 'wp/';
}
/** @psalm-suppress UnresolvableInclude The CLI supports a configurable WordPress root via WPMVC_WORDPRESS_PATH. */
require_once $wordpress_path . 'wp-load.php';
/** @psalm-suppress UnresolvableInclude Same validated WordPress root as wp-load.php above. */
require_once $wordpress_path . 'wp-admin/includes/plugin.php';

// Make sure the plugin has been activated.
if ( ! is_plugin_active( 'wp-mvc/wp_mvc.php' ) ) {
	echo "The WP MVC plugin is not active.\n";
	exit( 1 );
}

$shell = new MvcShellDispatcher( $argv );

echo "\n";
