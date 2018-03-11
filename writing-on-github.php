<?php
/**
 * Plugin Name: Writing on GitHub
 * Plugin URI: https://github.com/litefeel/writing-on-github
 * Description: A WordPress plugin to allow you writing on GitHub (or Jekyll site).
 * Version: 1.9
 * Author:  litefeel
 * Author URI: https://www.litefeel.com
 * License: GPLv2
 * Text Domain: writing-on-github
 */

// If the functions have already been autoloaded, don't reload.
// This fixes function duplication during unit testing.
$path = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $path ) ) {
    require_once( $path );
}

add_action( 'plugins_loaded', array( new Writing_On_GitHub, 'boot' ) );

class Writing_On_GitHub {

    /**
     * Object instance
     * @var self
     */
    public static $instance;

    /**
     * Language text domain
     * @var string
     */
    public static $text_domain = 'writing-on-github';

    /**
     * Controller object
     * @var Writing_On_GitHub_Controller
     */
    public $controller;

    /**
     * Controller object
     * @var Writing_On_GitHub_Admin
     */
    public $admin;

    /**
     * CLI object.
     *
     * @var Writing_On_GitHub_CLI
     */
    protected $cli;

    /**
     * Request object.
     *
     * @var Writing_On_GitHub_Request
     */
    protected $request;

    /**
     * Response object.
     *
     * @var Writing_On_GitHub_Response
     */
    protected $response;

    /**
     * Api object.
     *
     * @var Writing_On_GitHub_Api
     */
    protected $api;

    /**
     * Import object.
     *
     * @var Writing_On_GitHub_Import
     */
    protected $import;

    /**
     * Export object.
     *
     * @var Writing_On_GitHub_Export
     */
    protected $export;

    /**
     * Semaphore object.
     *
     * @var Writing_On_GitHub_Semaphore
     */
    protected $semaphore;

    /**
     * Database object.
     *
     * @var Writing_On_GitHub_Database
     */
    protected $database;

    /**
     * Called at load time, hooks into WP core
     */
    public function __construct() {
        self::$instance = $this;

        if ( is_admin() ) {
            $this->admin = new Writing_On_GitHub_Admin( plugin_basename( __FILE__ ) );
        }

        $this->controller = new Writing_On_GitHub_Controller( $this );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::add_command( 'wogh', $this->cli() );
        }
    }

    /**
     * Attaches the plugin's hooks into WordPress.
     */
    public function boot() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        add_action( 'admin_notices', array( $this, 'activation_notice' ) );

        add_action( 'init', array( $this, 'l10n' ) );

        // Controller actions.
        add_action( 'save_post', array( $this->controller, 'export_post' ) );
        add_action( 'delete_post', array( $this->controller, 'delete_post' ) );
        add_action( 'wp_ajax_nopriv_wogh_push_request', array( $this->controller, 'pull_posts' ) );
        add_action( 'wogh_export', array( $this->controller, 'export_all' ), 10, 2 );
        add_action( 'wogh_import', array( $this->controller, 'import_master' ), 10, 2 );
        add_filter( 'get_edit_post_link', array( $this, 'edit_post_link' ), 10, 3 );

        // add_filter( 'wogh_post_meta', array( $this, 'ignore_post_meta' ), 10, 1 );
        // add_filter( 'wogh_pre_import_meta', array( $this, 'ignore_post_meta' ), 10, 1 );
        add_filter( 'the_content', array( $this, 'the_content' ) );

        do_action( 'wogh_boot', $this );
    }

    public function edit_post_link($link, $postID, $context) {
        if ( ! wp_is_post_revision( $postID ) ) {
            $post = new Writing_On_GitHub_Post( $postID, Writing_On_GitHub::$instance->api() );
            if ( $post->is_on_github() ) {
                return $post->github_edit_url();
            }
        }

        return $link;
    }

    public function ignore_post_meta($meta) {
        $ignore_meta_keys = get_option('wogh_ignore_metas');
        if (empty($ignore_meta_keys)) {
            return $meta;
        }

        $keys = preg_split("/\\r\\n|\\r|\\n/", $ignore_meta_keys);
        if (empty($keys)) {
            return $meta;
        }
        foreach ($keys as $key => $value) {
            $keys[$key] = trim($value);
        }

        foreach ($meta as $key => $value) {
            if (in_array($key, $keys)) {
                unset($meta[$key]);
            }
        }

        return $meta;
    }

    public function the_content($content) {
        $arr = wp_upload_dir();
        $baseurl = $arr['baseurl'] . '/writing-on-github';

        $content = preg_replace_callback(
            '/(<img [^>]*?src=[\'"])\s*(\/images\/[^\s#]\S+)\s*([\'"][^>]*?>)/',
            function($matchs) use ($baseurl) {
                $url = $baseurl . $matchs[2];
                return "${matchs[1]}$url${matchs[3]}";
            },
            $content
        );

        $content = preg_replace_callback(
            '/(<a [^>]*?href=[\'"])\s*(\/images\/[^\s#]\S+)\s*([\'"][^>]*?>)/',
            function($matchs) use ($baseurl) {
                $url = $baseurl . $matchs[2];
                return "${matchs[1]}$url${matchs[3]}";
            },
            $content
        );
        return $content;
    }

    /**
     * Init i18n files
     */
    public function l10n() {
        load_plugin_textdomain( self::$text_domain );
    }

    /**
     * Sets and kicks off the export cronjob
     */
    public function start_export( $force = false ) {
        $this->start_cron( 'export', $force );
    }

    /**
     * Sets and kicks off the import cronjob
     */
    public function start_import( $force = false ) {
        $this->start_cron( 'import', $force );
    }

    /**
     * Enables the admin notice on initial activation
     */
    public function activate() {
        if ( 'yes' !== get_option( '_wogh_fully_exported' ) ) {
            set_transient( '_wogh_activated', 'yes' );
        }
    }

    /**
     * Displays the activation admin notice
     */
    public function activation_notice() {
        if ( ! get_transient( '_wogh_activated' ) ) {
            return;
        }

        delete_transient( '_wogh_activated' );

        ?><div class="updated">
            <p>
                <?php
                    printf(
                        __( 'To set up your site to sync with GitHub, update your <a href="%s">settings</a> and click "Export to GitHub."', 'writing-on-github' ),
                        admin_url( 'options-general.php?page=' . static::$text_domain)
                    );
                ?>
            </p>
        </div><?php
    }

    /**
     * Get the Controller object.
     *
     * @return Writing_On_GitHub_Controller
     */
    public function controller() {
        return $this->controller;
    }

    /**
     * Lazy-load the CLI object.
     *
     * @return Writing_On_GitHub_CLI
     */
    public function cli() {
        if ( ! $this->cli ) {
            $this->cli = new Writing_On_GitHub_CLI;
        }

        return $this->cli;
    }

    /**
     * Lazy-load the Request object.
     *
     * @return Writing_On_GitHub_Request
     */
    public function request() {
        if ( ! $this->request ) {
            $this->request = new Writing_On_GitHub_Request( $this );
        }

        return $this->request;
    }

    /**
     * Lazy-load the Response object.
     *
     * @return Writing_On_GitHub_Response
     */
    public function response() {
        if ( ! $this->response ) {
            $this->response = new Writing_On_GitHub_Response( $this );
        }

        return $this->response;
    }

    /**
     * Lazy-load the Api object.
     *
     * @return Writing_On_GitHub_Api
     */
    public function api() {
        if ( ! $this->api ) {
            $this->api = new Writing_On_GitHub_Api( $this );
        }

        return $this->api;
    }

    /**
     * Lazy-load the Import object.
     *
     * @return Writing_On_GitHub_Import
     */
    public function import() {
        if ( ! $this->import ) {
            $this->import = new Writing_On_GitHub_Import( $this );
        }

        return $this->import;
    }

    /**
     * Lazy-load the Export object.
     *
     * @return Writing_On_GitHub_Export
     */
    public function export() {
        if ( ! $this->export ) {
            $this->export = new Writing_On_GitHub_Export( $this );
        }

        return $this->export;
    }

    /**
     * Lazy-load the Semaphore object.
     *
     * @return Writing_On_GitHub_Semaphore
     */
    public function semaphore() {
        if ( ! $this->semaphore ) {
            $this->semaphore = new Writing_On_GitHub_Semaphore;
        }

        return $this->semaphore;
    }

    /**
     * Lazy-load the Database object.
     *
     * @return Writing_On_GitHub_Database
     */
    public function database() {
        if ( ! $this->database ) {
            $this->database = new Writing_On_GitHub_Database( $this );
        }

        return $this->database;
    }

    /**
     * Print to WP_CLI if in CLI environment or
     * write to debug.log if WP_DEBUG is enabled
     * @source http://www.stumiller.me/sending-output-to-the-wordpress-debug-log/
     *
     * @param mixed $msg
     * @param string $write
     */
    public static function write_log( $msg, $write = 'line' ) {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            if ( is_array( $msg ) || is_object( $msg ) ) {
                WP_CLI::print_value( $msg );
            } else {
                WP_CLI::$write( $msg );
            }
        } elseif ( true === WP_DEBUG ) {
            if ( is_array( $msg ) || is_object( $msg ) ) {
                error_log( print_r( $msg, true ) );
            } else {
                error_log( $msg );
            }
        }
    }

    /**
     * Kicks of an import or export cronjob.
     *
     * @param bool   $force
     * @param string $type
     */
    protected function start_cron( $type, $force = false ) {
        update_option( '_wogh_' . $type . '_started', 'yes' );
        $user_id = get_current_user_id();
        wp_schedule_single_event( time(), 'wogh_' . $type . '', array( $user_id, $force ) );
        spawn_cron();
    }
}
