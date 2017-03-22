<?php
/**
 * Database interface.
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Database
 */
class Writing_On_GitHub_Database {

	/**
	 * Application container.
	 *
	 * @var Writing_On_GitHub
	 */
	protected $app;

	/**
	 * Currently whitelisted post types.
	 *
	 * @var array
	 */
	protected $whitelisted_post_types = array( 'post', 'page' );

	/**
	 * Currently whitelisted post statuses.
	 *
	 * @var array
	 */
	protected $whitelisted_post_statuses = array( 'publish' );

	/**
	 * Instantiates a new Database object.
	 *
	 * @param Writing_On_GitHub $app Application container.
	 */
	public function __construct( Writing_On_GitHub $app ) {
		$this->app = $app;
	}

	/**
	 * Queries the database for all of the supported posts.
	 *
	 * @return Writing_On_GitHub_Post[]|WP_Error
	 */
	public function fetch_all_supported() {
		$args  = array(
			'post_type'   => $this->get_whitelisted_post_types(),
			'post_status' => $this->get_whitelisted_post_statuses(),
			'nopaging'    => true,
			'fields'      => 'ids',
		);

		$query = new WP_Query( apply_filters( 'wogh_pre_fetch_all_supported', $args ) );

		$post_ids = $query->get_posts();

		if ( ! $post_ids ) {
			return new WP_Error(
				'no_results',
				__( 'Querying for supported posts returned no results.', 'writing-on-github' )
			);
		}

		$results = array();
		foreach ( $post_ids as $post_id ) {
			// Do not export posts that have already been exported
			if ( ! get_post_meta( $post_id, '_wogh_sha', true ) ||
				 ! get_post_meta( $post_id, '_wogh_github_path', true) ) {

				$results[] = new Writing_On_GitHub_Post( $post_id, $this->app->api() );
			}
		}

		return $results;
	}

	/**
	 * Queries a post and returns it if it's supported.
	 *
	 * @param int $post_id Post ID to fetch.
	 *
	 * @return WP_Error|Writing_On_GitHub_Post
	 */
	public function fetch_by_id( $post_id ) {
		$post = new Writing_On_GitHub_Post( $post_id, $this->app->api() );

		if ( ! $this->is_post_supported( $post ) ) {
			return new WP_Error(
				'unsupported_post',
				sprintf(
					__(
						'Post ID %s is not supported by WOGH. See wiki to find out how to add support.',
						'writing-on-github'
					),
					$post_id
				)
			);
		}

		return $post;
	}

	/**
	 * Saves an array of Post objects to the database
	 * and associates their author as well as their latest
	 *
	 * @param Writing_On_GitHub_Post[] $posts Array of Posts to save.
	 * @param string                       $email Author email.
	 *
	 * @return string|WP_Error
	 */
	public function save_posts( array $posts ) {

		/**
		 * Whether an error has occurred.
		 *
		 * @var WP_Error|false $error
		 */
		$error = false;

		foreach ( $posts as $post ) {
			$args = apply_filters( 'wogh_pre_import_args', $this->post_args( $post ), $post );

			remove_filter('content_save_pre', 'wp_filter_post_kses');
			$post_id = $post->is_new() ?
				wp_insert_post( $args, true ) :
				wp_update_post( $args, true );
			add_filter('content_save_pre', 'wp_filter_post_kses');

			if ( is_wp_error( $post_id ) ) {
				if ( ! $error ) {
					$error = $post_id;
				} else {
					$error->add( $post_id->get_error_code(), $post_id->get_error_message() );
				}

				// Abort saving if updating the post fails.
				continue;
			}

			// $this->set_revision_author( $post_id, $user_id );

			if ( $post->is_new() ) {
				$author = false;
				$meta = $post->get_meta();
				if ( ! empty( $meta ) && ! empty( $meta['author'] ) ) {
					$author = $meta['author'];
				}
				$user    = $this->fetch_commit_user( $author );
				$user_id = ! is_wp_error( $user ) ? $user->ID : 0;
				$this->set_post_author( $post_id, $user_id );
			}

			$post->set_post( get_post( $post_id ) );

			$meta = apply_filters( 'wogh_pre_import_meta', $post->get_meta(), $post );

			unset( $meta['tags'] );
			unset( $meta['categories'] );
			unset( $meta['author'] );
			unset( $meta['post_date'] );
			unset( $meta['post_excerpt'] );
			unset( $meta['permalink'] );

			foreach ( $meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		if ( $error ) {
			return $error;
		}

		return __( 'Successfully saved posts.', 'writing-on-github' );
	}

	protected function post_args( $post ) {
		$args = $post->get_args();
		$meta = $post->get_meta();

		// update tags
		if ( isset( $meta['tags'] ) && $meta['tags'] ) {
		    $args['tags_input'] = $meta['tags'];
		}

		// update categories
		if ( isset( $meta['categories'] ) && $meta['categories'] ) {
		    $categories = $meta['categories'];
		    if (!is_array($categories)) {
		        $categories = array($categories);
		    }
		    $terms = get_terms(array(
		        'taxonomy' => 'category',
		        'fields' => 'id=>name',
		        'hide_empty' => 0,
		        'name' => $categories
		        )
		    );
		    $map = array();
		    foreach ($categories as $name) {
		        $map[$name] = 1;
		    }

		    $ids = array();
		    if (!empty($terms)) {
		        foreach ($terms as $id => $name) {
		            $ids[] = $id;
		            unset($map[$name]);
		        }
		    }

		    // create new terms
		    if (!empty($map)) {
		        foreach ($map as $name => $value) {
		            $term = wp_insert_term($name, 'category', array('parent' => 0));
		            // array('term_id' => $term_id, 'term_taxonomy_id' => $tt_id);
		            $ids[] = $term['term_id'];
		        }
		    }

		    $args['post_category'] = $ids;
		}

		return $args;
	}

	/**
	 * Deletes a post from the database based on its GitHub path.
	 *
	 * @param string $path Path of Post to delete.
	 *
	 * @return string|WP_Error
	 */
	public function delete_post_by_path( $path ) {
		$query = new WP_Query( array(
			'meta_key'       => '_wogh_github_path',
			'meta_value'     => $path,
			'meta_compare'   => '=',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );

		$post_id = $query->get_posts();
		$post_id = array_pop( $post_id );

		if ( ! $post_id ) {
			$parts     = explode( '/', $path );
			$filename  = array_pop( $parts );
			$directory = $parts ? array_shift( $parts ) : '';

			if ( false !== strpos( $directory, 'post' ) ) {
				preg_match( '/([0-9]{4})-([0-9]{2})-([0-9]{2})-(.*)\.md/', $filename, $matches );
				$title = $matches[4];

				$query = new WP_Query( array(
					'name'     => $title,
					'posts_per_page' => 1,
					'post_type' => $this->get_whitelisted_post_types(),
					'fields'         => 'ids',
				) );

				$post_id = $query->get_posts();
				$post_id = array_pop( $post_id );
			}

			if ( ! $post_id ) {
				preg_match( '/(.*)\.md/', $filename, $matches );
				$title = $matches[1];

				$query = new WP_Query( array(
					'name'     => $title,
					'posts_per_page' => 1,
					'post_type' => $this->get_whitelisted_post_types(),
					'fields'         => 'ids',
				) );

				$post_id = $query->get_posts();
				$post_id = array_pop( $post_id );
			}
		}

		if ( ! $post_id ) {
			return new WP_Error(
				'path_not_found',
				sprintf(
					__( 'Post not found for path %s.', 'writing-on-github' ),
					$path
				)
			);
		}

		$result = wp_delete_post( $post_id );

		// If deleting fails...
		if ( false === $result ) {
			$post = get_post( $post_id );

			// ...and the post both exists and isn't in the trash...
			if ( $post && 'trash' !== $post->post_status ) {
				// ... then something went wrong.
				return new WP_Error(
					'db_error',
					sprintf(
						__( 'Failed to delete post ID %d.', 'writing-on-github' ),
						$post_id
					)
				);
			}
		}

		return sprintf(
			__( 'Successfully deleted post ID %d.', 'writing-on-github' ),
			$post_id
		);
	}

	public function delete_post( $post_id ) {
		$result = wp_delete_post( $post_id );

		// If deleting fails...
		if ( false === $result ) {
			$post = get_post( $post_id );

			// ...and the post both exists and isn't in the trash...
			if ( $post && 'trash' !== $post->post_status ) {
				// ... then something went wrong.
				return new WP_Error(
					'db_error',
					sprintf(
						__( 'Failed to delete post ID %d.', 'writing-on-github' ),
						$post_id
					)
				);
			}
		}

		return sprintf(
			__( 'Successfully deleted post ID %d.', 'writing-on-github' ),
			$post_id
		);
	}

	/**
	 * Returns the list of post type permitted.
	 *
	 * @return array
	 */
	protected function get_whitelisted_post_types() {
		return apply_filters( 'wogh_whitelisted_post_types', $this->whitelisted_post_types );
	}

	/**
	 * Returns the list of post status permitted.
	 *
	 * @return array
	 */
	protected function get_whitelisted_post_statuses() {
		return apply_filters( 'wogh_whitelisted_post_statuses', $this->whitelisted_post_statuses );
	}

	/**
	 * Formats a whitelist array for a query.
	 *
	 * @param array $whitelist Whitelisted posts to format into query.
	 *
	 * @return string Whitelist formatted for query
	 */
	protected function format_for_query( $whitelist ) {
		foreach ( $whitelist as $key => $value ) {
			$whitelist[ $key ] = "'$value'";
		}

		return implode( ', ', $whitelist );
	}

	/**
	 * Verifies that both the post's status & type
	 * are currently whitelisted
	 *
	 * @param  Writing_On_GitHub_Post $post Post to verify.
	 *
	 * @return boolean                          True if supported, false if not.
	 */
	protected function is_post_supported( Writing_On_GitHub_Post $post ) {
		if ( wp_is_post_revision( $post->id ) ) {
			return false;
		}

		// We need to allow trashed posts to be queried, but they are not whitelisted for export.
		if ( ! in_array( $post->status(), $this->get_whitelisted_post_statuses() ) && 'trash' !== $post->status() ) {
			return false;
		}

		if ( ! in_array( $post->type(), $this->get_whitelisted_post_types() ) ) {
			return false;
		}

		if ( $post->has_password() ) {
			return false;
		}

		return apply_filters( 'wogh_is_post_supported', true, $post );
	}

	/**
	 * Retrieves the commit user for a provided display name
	 *
	 * Searches for a user with provided display name or returns
	 * the default user saved in the database.
	 *
	 * @param string $display_name User display name to search for.
	 *
	 * @return WP_Error|WP_User
	 */
	protected function fetch_commit_user( $display_name ) {
		// If we can't find a user and a default hasn't been set,
		// we're just going to set the revision author to 0.
		$user = false;

		if ( ! empty( $display_name ) ) {
			$search_string = esc_attr( $display_name );
			$query = new WP_User_Query( array(
			    'search'         => "{$search_string}",
			    'search_columns' => array(
			        'display_name',
			        'user_nicename',
			        'user_login',
			    )
			) );
			$users = $query->get_results();
			$user = empty($users) ? false : $users[0];
		}

		if ( ! $user ) {
			// Use the default user.
			$user = get_user_by( 'id', (int) get_option( 'wogh_default_user' ) );
		}

		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				sprintf(
					__( 'Commit user not found for email %s', 'writing-on-github' ),
					$email
				)
			);
		}

		return $user;
	}

	/**
	 * Sets the author latest revision
	 * of the provided post ID to the provided user.
	 *
	 * @param int $post_id Post ID to update revision author.
	 * @param int $user_id User ID for revision author.
	 *
	 * @return string|WP_Error
	 */
	protected function set_revision_author( $post_id, $user_id ) {
		$revision = wp_get_post_revisions( $post_id );

		if ( ! $revision ) {
			$new_revision = wp_save_post_revision( $post_id );

			if ( ! $new_revision || is_wp_error( $new_revision ) ) {
				return new WP_Error( 'db_error', 'There was a problem saving a new revision.' );
			}

			// `wp_save_post_revision` returns the ID, whereas `get_post_revision` returns the whole object
			// in order to be consistent, let's make sure we have the whole object before continuing.
			$revision = get_post( $new_revision );

			if ( ! $revision ) {
				return new WP_Error( 'db_error', 'There was a problem retrieving the newly recreated revision.' );
			}
		} else {
			$revision = array_shift( $revision );
		}

		return $this->set_post_author( $revision->ID, $user_id );
	}

	/**
	 * Updates the user ID for the provided post ID.
	 *
	 * Bypassing triggering any hooks, including creating new revisions.
	 *
	 * @param int $post_id Post ID to update.
	 * @param int $user_id User ID to update to.
	 *
	 * @return string|WP_Error
	 */
	protected function set_post_author( $post_id, $user_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->posts,
			array(
				'post_author' => (int) $user_id,
			),
			array(
				'ID' => (int) $post_id,
			),
			array( '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', $wpdb->last_error );
		}

		if ( 0 === $result ) {
			return sprintf(
				__( 'No change for post ID %d.', 'writing-on-github' ),
				$post_id
			);
		}

		clean_post_cache( $post_id );

		return sprintf(
			__( 'Successfully updated post ID %d.', 'writing-on-github' ),
			$post_id
		);
	}

	/**
	 * Update the provided post's blob sha.
	 *
	 * @param Writing_On_GitHub_Post $post Post to update.
	 * @param string                     $sha Sha to update to.
	 *
	 * @return bool|int
	 */
	public function set_post_sha( $post, $sha ) {
		return update_post_meta( $post->id, '_wogh_sha', $sha );
	}
}
