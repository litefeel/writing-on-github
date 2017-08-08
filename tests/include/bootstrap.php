<?php
/**
 * PHPUnit bootstrap file
 */
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}
// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

define( 'WRITING_ON_GITHUB_TEST', true );

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    require dirname( __FILE__ ) . '/../../writing-on-github.php';
    remove_action( 'plugins_loaded', array( Writing_On_GitHub::$instance, 'boot' ) );
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

error_reporting( E_ALL ^ E_DEPRECATED );
