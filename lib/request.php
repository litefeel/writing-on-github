<?php
/**
 * Request management object.
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Request
 */
class Writing_On_GitHub_Request {

    /**
     * Application container.
     *
     * @var Writing_On_GitHub
     */
    protected $app;

    /**
     * Raw request data.
     *
     * @var string
     */
    protected $raw_data;

    /**
     * Headers
     * @var array
     */
    protected $headers;

    /**
     * Writing_On_GitHub_Request constructor.
     *
     * @param Writing_On_GitHub $app Application container.
     */
    public function __construct( Writing_On_GitHub $app ) {
        $this->app = $app;
    }

    /**
     * Validates the header's secret.
     *
     * @return true|WP_Error
     */
    public function is_secret_valid() {
        $headers = $this->headers();

        $this->raw_data = $this->read_raw_data();

        // Validate request secret.
        $hash = hash_hmac( 'sha1', $this->raw_data, $this->secret() );
        if ( 'sha1=' . $hash !== $headers['X-Hub-Signature'] ) {
            return false;
        }

        //      [X-Hub-Signature] => sha1=3cf3da70de401f7dfff053392f60cc534efed3b4
        //     [Content-Type] => application/json
        //     [X-Github-Delivery] => b2102500-0acf-11e7-8acb-fd86a3497c2f
        //     [X-Github-Event] => ping

        return true;
    }

    /**
     * Validates the ping event.
     * @return boolean
     */
    public function is_ping() {
        return 'ping' == $this->webhook_event();
    }

    /**
     * Validates the push event.
     * @return boolean
     */
    public function is_push() {
        return 'push' == $this->webhook_event();
    }

    /**
     * Return X-Github-Event in headers.
     * @return string
     */
    public function webhook_event() {
        $headers = $this->headers();
        return $headers['X-Github-Event'];
    }

    /**
     * Returns a payload object for the given request.
     *
     * @return Writing_On_GitHub_Payload
     */
    public function payload() {
        return new Writing_On_GitHub_Payload( $this->app, $this->raw_data );
    }

    /**
     * Cross-server header support.
     *
     * Returns an array of the request's headers.
     *
     * @return array
     */
    protected function headers() {
        if ( ! empty( $this->headers ) ) {
            return $this->headers;
        }

        $this->headers = array();
        if ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
            // github webhook
            // content-type: application/json
            // Expect:
            // User-Agent: GitHub-Hookshot/7a71d82
            // X-GitHub-Delivery: a331b200-2537-11e8-9d7e-ce0853020b44
            // X-GitHub-Event: push
            // X-Hub-Signature: sha1=98185ffa2c4684c9a1324c57086709acca9dddc7
            foreach ( $headers as $name => $value ) {
                $this->headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '-', ' ', $name ) ) ) ) ] = $value;
            }
        } else {
            /**
             * Nginx and pre 5.4 workaround.
             * @see http://www.php.net/manual/en/function.getallheaders.php
             */
            foreach ( $_SERVER as $name => $value ) {
                if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
                    $this->headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
                }
            }
        }

        return $this->headers;
    }

    /**
     * Reads the raw data from STDIN.
     *
     * @return string
     */
    protected function read_raw_data() {
        return file_get_contents( 'php://input' );
    }

    /**
     * Returns the Webhook secret
     *
     * @return string
     */
    protected function secret() {
        return get_option( 'wogh_secret' );
    }
}
