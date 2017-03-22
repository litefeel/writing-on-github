<?php
/**
 * WP_CLI Commands
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_CLI
 */
class Writing_On_GitHub_CLI extends WP_CLI_Command {

	/**
	 * Application container.
	 *
	 * @var Writing_On_GitHub
	 */
	protected $app;

	/**
	 * Grab the Application container on instantiation.
	 */
	public function __construct() {
		$this->app = Writing_On_GitHub::$instance;
	}

	/**
	 * Exports an individual post
	 * all your posts to GitHub
	 *
	 * ## OPTIONS
	 *
	 * <post_id|all>
	 * : The post ID to export or 'all' for full site
	 *
	 * <user_id>
	 * : The user ID you'd like to save the commit as
	 *
	 * ## EXAMPLES
	 *
	 *     wp wogh export all 1
	 *     wp wogh export 1 1
	 *
	 * @synopsis <post_id|all> <user_id>
	 *
	 * @param array $args Command arguments.
	 */
	public function export( $args ) {
		list( $post_id, $user_id ) = $args;

		if ( ! is_numeric( $user_id ) ) {
			WP_CLI::error( __( 'Invalid user ID', 'writing-on-github' ) );
		}

		$this->app->export()->set_user( $user_id );

		if ( 'all' === $post_id ) {
			WP_CLI::line( __( 'Starting full export to GitHub.', 'writing-on-github' ) );
			$this->app->controller()->export_all();
		} elseif ( is_numeric( $post_id ) ) {
			WP_CLI::line(
				sprintf(
					__( 'Exporting post ID to GitHub: %d', 'writing-on-github' ),
					$post_id
				)
			);
			$this->app->controller()->export_post( (int) $post_id );
		} else {
			WP_CLI::error( __( 'Invalid post ID', 'writing-on-github' ) );
		}
	}

	/**
	 * Imports the post in your GitHub repo
	 * into your WordPress blog
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user ID you'd like to save the commit as
	 *
	 * ## EXAMPLES
	 *
	 *     wp wogh import 1
	 *
	 * @synopsis <user_id>
	 *
	 * @param array $args Command arguments.
	 */
	public function import( $args ) {
		list( $user_id ) = $args;

		if ( ! is_numeric( $user_id ) ) {
			WP_CLI::error( __( 'Invalid user ID', 'writing-on-github' ) );
		}

		update_option( '_wogh_export_user_id', (int) $user_id );

		WP_CLI::line( __( 'Starting import from GitHub.', 'writing-on-github' ) );

		$this->app->controller()->import_master();
	}

	/**
	 * Fetches the provided sha or the repository's
	 * master branch and caches it.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user ID you'd like to save the commit as
	 *
	 * ## EXAMPLES
	 *
	 *     wp wogh prime --branch=master
	 *     wp wogh prime --sha=<commit_sha>
	 *
	 * @synopsis [--sha=<commit_sha>] [--branch]
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command associated arguments.
	 */
	public function prime( $args, $assoc_args ) {
		if ( isset( $assoc_args['branch'] ) ) {
			WP_CLI::line( __( 'Starting branch import.', 'writing-on-github' ) );

			$commit = $this->app->api()->fetch()->master();

			if ( is_wp_error( $commit ) ) {
				WP_CLI::error(
					sprintf(
						__( 'Failed to import and cache branch with error: %s', 'writing-on-github' ),
						$commit->get_error_message()
					)
				);
			} else {
				WP_CLI::success(
					sprintf(
						__( 'Successfully imported and cached commit %s from branch.', 'writing-on-github' ),
						$commit->sha()
					)
				);
			}
		} else if ( isset( $assoc_args['sha'] ) ) {
			WP_CLI::line( 'Starting sha import.' );

			$commit = $this->app->api()->fetch()->commit( $assoc_args['sha'] );

			WP_CLI::success(
				sprintf(
					__( 'Successfully imported and cached commit %s.', 'writing-on-github' ),
					$commit->sha()
				)
			);
		} else {
			WP_CLI::error( 'Invalid fetch.' );
		}
	}
}
