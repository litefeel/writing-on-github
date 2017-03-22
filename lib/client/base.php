<?php
/**
 * Base API client class.
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Base_Client
 */
class Writing_On_GitHub_Base_Client {

	const HOST_OPTION_KEY 	= 'wogh_host';
	const TOKEN_OPTION_KEY 	= 'wogh_oauth_token';
	const REPO_OPTION_KEY 	= 'wogh_repository';
	const BRANCH_OPTION_KEY = 'wogh_branch';

	/**
	 * Application container.
	 *
	 * @var Writing_On_GitHub
	 */
	protected $app;

	/**
	 * Instantiates a new Api object.
	 *
	 * @param Writing_On_GitHub $app Application container.
	 */
	public function __construct( Writing_On_GitHub $app ) {
		$this->app = $app;
	}

	/**
	 * Generic GitHub API interface and response handler
	 *
	 * @param string $method HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $body Request body.
	 *
	 * @return stdClass|WP_Error
	 */
	protected function call( $method, $endpoint, $body = array() ) {
		if ( is_wp_error( $error = $this->can_call() ) ) {
			return $error;
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'token ' . $this->oauth_token(),
			),
		);

		if ( 'GET' !== $method ) {
			$args['body'] = function_exists( 'wp_json_encode' ) ?
				wp_json_encode( $body ) :
				json_encode( $body );
		}

		$tmpbody = isset($args['body']) ? $args['body'] : '';
		error_log("writing-on-github-call $method $endpoint $tmpbody");

		$response = wp_remote_request( $endpoint, $args );
		$status   = wp_remote_retrieve_header( $response, 'status' );
		$body     = json_decode( wp_remote_retrieve_body( $response ) );

		if ( '2' !== substr( $status, 0, 1 ) && '3' !== substr( $status, 0, 1 ) ) {
			return new WP_Error(
				strtolower( str_replace( ' ', '_', $status ) ),
				sprintf(
					__( 'Method %s to endpoint %s failed with error: %s', 'writing-on-github' ),
					$method,
					$endpoint,
					$body && $body->message ? $body->message : 'Unknown error'
				)
			);
		}

		return $body;
	}

	/**
	 * Validates whether the Api object can make a call.
	 *
	 * @return true|WP_Error
	 */
	protected function can_call() {
		if ( ! $this->oauth_token() ) {
			return new WP_Error(
				'missing_token',
				__( 'Writing On GitHub needs an auth token. Please update your settings.', 'writing-on-github' )
			);
		}

		$repo = $this->repository();

		if ( ! $repo ) {
			return new WP_Error(
				'missing_repository',
				__( 'Writing On GitHub needs a repository. Please update your settings.', 'writing-on-github' )
			);
		}

		$parts = explode( '/', $repo );

		if ( 2 !== count( $parts ) ) {
			return new WP_Error(
				'malformed_repository',
				__( 'Writing On GitHub needs a properly formed repository. Please update your settings.', 'writing-on-github' )
			);
		}

		return true;
	}

	/**
	 * Returns the repository to sync with
	 *
	 * @return string
	 */
	public function repository() {
		return (string) get_option( self::REPO_OPTION_KEY );
	}

	/**
	 * Returns the user's oauth token
	 *
	 * @return string
	 */
	public function oauth_token() {
		return (string) get_option( self::TOKEN_OPTION_KEY );
	}

	/**
	 * Returns the GitHub host to sync with (for GitHub Enterprise support)
	 */
	public function api_base() {
		return get_option( self::HOST_OPTION_KEY );
	}

	public function branch() {
		$branch = get_option( self::BRANCH_OPTION_KEY );
		return $branch ? $branch : 'master';
	}

	/**
	 * API endpoint for the master branch reference
	 */
	public function reference_endpoint() {
		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/git/refs/heads/' . $this->branch();

		return $url;
	}

	/**
	 * Api to get and create commits
	 */
	public function commit_endpoint() {
		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/git/commits';

		return $url;
	}

	/**
	 * Api to compare commits
	 */
	public function compare_endpoint() {
		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/compare';

		return $url;
	}

	/**
	 * Api to get and create trees
	 */
	public function tree_endpoint() {
		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/git/trees';

		return $url;
	}

	/**
	 * Builds the proper blob API endpoint for a given post
	 *
	 * Returns String the relative API call path
	 */
	public function blob_endpoint() {
		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/git/blobs';

		return $url;
	}

	/**
	 * Builds the proper content API endpoint for a given post
	 *
	 * Returns String the relative API call path
	 */
	public function content_endpoint( $path = false ) {
		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/contents';

		if ( ! empty($path) ) {
			$url .= '/' . $path;
		}

		return $url;
	}
}
