<?php
/**
 * Response management object.
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Response
 */
class Writing_On_GitHub_Response {

	/**
	 * Application container.
	 *
	 * @var Writing_On_GitHub
	 */
	protected $app;

	/**
	 * Writing_On_GitHub_Response constructor.
	 *
	 * @param Writing_On_GitHub $app Application container.
	 */
	public function __construct( Writing_On_GitHub $app ) {
		$this->app = $app;
	}

	/**
	 * Writes to the log and returns the error response.
	 *
	 * @param WP_Error $error Error to respond with.
	 *
	 * @return false
	 */
	public function error( WP_Error $error ) {
		global $wp_version;

		$this->log( $error );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && defined( 'WOGH_AJAX' ) && WOGH_AJAX ) {
			/**
			 * WordPress 4.1.0 introduced allowing WP_Error objects to be
			 * passed directly into `wp_send_json_error`. This shims in
			 * compatibility for older versions. We're currently supporting 3.9+.
			 */
			if ( version_compare( $wp_version, '4.1.0', '<' ) ) {
				$result = array();

				foreach ( $error->errors as $code => $messages ) {
					foreach ( $messages as $message ) {
						$result[] = array( 'code' => $code, 'message' => $message );
					}
				}

				$error = $result;
			}

			wp_send_json_error( $error );
		}

		return false;
	}

	/**
	 * Writes to the log and returns the success response.
	 *
	 * @param string $success Success message to respond with.
	 *
	 * @return true
	 */
	public function success( $success ) {
		$this->log( $success );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && defined( 'WOGH_AJAX' ) && WOGH_AJAX ) {
			wp_send_json_success( $success );
		}

		return true;
	}

	/**
	 * Writes a log message.
	 *
	 * Can extract a message from WP_Error object.
	 *
	 * @param string|WP_Error $msg Message to log.
	 */
	protected function log( $msg ) {
		if ( is_wp_error( $msg ) ) {
			$msg = $msg->get_error_message();
		}

		Writing_On_GitHub::write_log( $msg );
	}
}
