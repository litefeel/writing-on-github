<?php
/**
 * Controller object manages tree retrieval, manipulation and publishing
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Controller
 */
class Writing_On_GitHub_Controller {

	/**
	 * Application container.
	 *
	 * @var Writing_On_GitHub
	 */
	public $app;

	/**
	 * Instantiates a new Controller object
	 *
	 * @param Writing_On_GitHub $app Applicatio container.
	 */
	public function __construct( Writing_On_GitHub $app ) {
		$this->app = $app;
	}

	/**
	 * Webhook callback as triggered from GitHub push.
	 *
	 * Reads the Webhook payload and syncs posts as necessary.
	 *
	 * @return boolean
	 */
	public function pull_posts() {
		$this->set_ajax();
		if ( ! $this->app->semaphore()->is_open() ) {
			return $this->app->response()->error( new WP_Error(
				'semaphore_locked',
				sprintf( __( '%s : Semaphore is locked, import/export already in progress.', 'writing-on-github' ), 'Controller::pull_posts()' )
			) );
		}

		if ( ! $this->app->request()->is_secret_valid() ) {
			return $this->app->response()->error( new WP_Error(
				'invalid_headers',
				__( 'Failed to validate secret.', 'writing-on-github' )
			) );
		}

		// ping
		if ( $this->app->request()->is_ping() ) {
			return $this->app->response()->success( __( 'Wordpress is ready.', 'writing-on-github' ) );
		}

		// push
		if ( ! $this->app->request()->is_push() ) {
			return $this->app->response()->error( new WP_Error(
				'invalid_headers',
				__( 'Failed to validate webhook event.', 'writing-on-github' )
			) );
		}
		$payload = $this->app->request()->payload();

		if ( ! $payload->should_import() ) {
			return $this->app->response()->error( new WP_Error(
				'invalid_payload',
				sprintf(
					__( "%s won't be imported.", 'writing-on-github' ),
					strtolower( $payload->get_commit_id() ) ? : '[Missing Commit ID]'
				)
			) );
		}

		$this->app->semaphore()->lock();
		remove_action( 'save_post', array( $this, 'export_post' ) );
		remove_action( 'delete_post', array( $this, 'delete_post' ) );

		$result = $this->app->import()->payload( $payload );

		$this->app->semaphore()->unlock();

		if ( is_wp_error( $result ) ) {
			return $this->app->response()->error( $result );
		}

		return $this->app->response()->success( $result );
	}

	/**
	 * Imports posts from the current master branch.
	 *
	 * @return boolean
	 */
	public function import_master( $user_id ) {
		if ( ! $this->app->semaphore()->is_open() ) {
			return $this->app->response()->error( new WP_Error(
				'semaphore_locked',
				sprintf( __( '%s : Semaphore is locked, import/export already in progress.', 'writing-on-github' ), 'Controller::import_master()' )
			) );
		}

		$this->app->semaphore()->lock();
		remove_action( 'save_post', array( $this, 'export_post' ) );
		remove_action( 'save_post', array( $this, 'delete_post' ) );

		if ( $user_id ) {
			wp_set_current_user( $user_id );
		}

		$result = $this->app->import()->master();

		$this->app->semaphore()->unlock();

		if ( is_wp_error( $result ) ) {
			update_option( '_wogh_import_error', $result->get_error_message() );

			return $this->app->response()->error( $result );
		}

		update_option( '_wogh_import_complete', 'yes' );

		return $this->app->response()->success( $result );
	}

	/**
	 * Export all the posts in the database to GitHub.
	 *
	 * @return boolean
	 */
	public function export_all( $user_id ) {
		if ( ! $this->app->semaphore()->is_open() ) {
			return $this->app->response()->error( new WP_Error(
				'semaphore_locked',
				sprintf( __( '%s : Semaphore is locked, import/export already in progress.', 'writing-on-github' ), 'Controller::export_all()' )
			) );
		}

		$this->app->semaphore()->lock();

		if ( $user_id ) {
			wp_set_current_user( $user_id );
		}

		$result = $this->app->export()->full();
		$this->app->semaphore()->unlock();

		// Maybe move option updating out of this class/upgrade message display?
		if ( is_wp_error( $result ) ) {
			update_option( '_wogh_export_error', $result->get_error_message() );

			return $this->app->response()->error( $result );
		} else {
			update_option( '_wogh_export_complete', 'yes' );
			update_option( '_wogh_fully_exported', 'yes' );

			return $this->app->response()->success( $result );
		}
	}

	/**
	 * Exports a single post to GitHub by ID.
	 *
	 * Called on the save_post hook.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return boolean
	 */
	public function export_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! $this->app->semaphore()->is_open() ) {
			return $this->app->response()->error( new WP_Error(
				'semaphore_locked',
				sprintf( __( '%s : Semaphore is locked, import/export already in progress.', 'writing-on-github' ), 'Controller::export_post()' )
			) );
		}

		$this->app->semaphore()->lock();
		$result = $this->app->export()->update( $post_id );
		$this->app->semaphore()->unlock();

		if ( is_wp_error( $result ) ) {
			return $this->app->response()->error( $result );
		}

		return $this->app->response()->success( $result );
	}

	/**
	 * Removes the post from the tree.
	 *
	 * Called the delete_post hook.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return boolean
	 */
	public function delete_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! $this->app->semaphore()->is_open() ) {
			return $this->app->response()->error( new WP_Error(
				'semaphore_locked',
				sprintf( __( '%s : Semaphore is locked, import/export already in progress.', 'writing-on-github' ), 'Controller::delete_post()' )
			) );
		}

		$this->app->semaphore()->lock();
		$result = $this->app->export()->delete( $post_id );
		$this->app->semaphore()->unlock();

		if ( is_wp_error( $result ) ) {
			return $this->app->response()->error( $result );
		}

		return $this->app->response()->success( $result );
	}

	/**
	 * Indicates we're running our own AJAX hook
	 * and thus should respond with JSON, rather
	 * than just returning data.
	 */
	protected function set_ajax() {
		if ( ! defined( 'WOGH_AJAX' ) ) {
			define( 'WOGH_AJAX', true );
		}
	}
}
