<?php
/**
 * GitHub Export Manager.
 *
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Export
 */
class Writing_On_GitHub_Export {

    /**
     * Application container.
     *
     * @var Writing_On_GitHub
     */
    protected $app;

    /**
     * Initializes a new export manager.
     *
     * @param Writing_On_GitHub $app Application container.
     */
    public function __construct( Writing_On_GitHub $app ) {
        $this->app = $app;
    }

    /**
     * Updates all of the current posts in the database on master.
     *
     * @param  bool    $force
     *
     * @return string|WP_Error
     */
    public function full( $force = false ) {
        $posts = $this->app->database()->fetch_all_supported( $force );

        if ( is_wp_error( $posts ) ) {
            /*　@var WP_Error $posts */
            return $posts;
        }

        $error = '';

        foreach ( $posts as $post ) {
            $result = $this->update( $post->id() );
            if ( is_wp_error( $result ) ) {
                /* @var WP_Error $result */
                $error = wogh_append_error( $error, $result );
            }
        }

        if ( is_wp_error( $error ) ) {
            /* @var WP_Error $error */
            return $error;
        }

        return __( 'Export to GitHub completed successfully.', 'writing-on-github' );
    }


    /**
     * Check if it exists in github
     * @param  int  $post_id
     * @return boolean
     */
    protected function github_path( $post_id ) {
        $github_path = get_post_meta( $post_id, '_wogh_github_path', true );

        if ( $github_path && $this->app->api()->fetch()->exists( $github_path ) ) {
            return $github_path;
        }

        return false;
    }

    /**
     * Updates the provided post ID in master.
     *
     * @param int $post_id Post ID to update.
     *
     * @return string|WP_Error
     */
    public function update( $post_id ) {
        $post = $this->app->database()->fetch_by_id( $post_id );

        if ( is_wp_error( $post ) ) {
            /*　@var WP_Error $post */
            return $post;
        }

        if ( 'trash' === $post->status() ) {
            return $this->delete( $post_id );
        }

        if ( $old_github_path = $this->github_path( $post->id() ) ) {
            error_log("old_github_path: $old_github_path");
            $post->set_old_github_path($old_github_path);
        }

        $result = $this->export_post( $post );

        if ( is_wp_error( $result ) ) {
            /* @var WP_Error $result */
            return $result;
        }

        return __( 'Export to GitHub completed successfully.', 'writing-on-github' );
    }

    /**
     * Post to blob
     * @param  Writing_On_GitHub_Post $post
     * @return WP_Error|Writing_On_GitHub_Blob
     */
    protected function post_to_blob( Writing_On_GitHub_Post $post ) {
        if ( ! $post->get_blob()
            && $post->old_github_path()
            && wogh_is_dont_export_content() ) {


            $blob = $this->app->api()->fetch()->blob_by_path( $post->old_github_path() );

            if ( is_wp_error( $blob ) ) {
                /** @var WP_Error $blob */
                return $blob;
            }

            $post->set_blob( $blob );
        }

        return $post->to_blob();
    }

    /**
     * Export post to github
     * @param  Writing_On_GitHub_Post $post
     * @return WP_Error|true
     */
    public function export_post( Writing_On_GitHub_Post $post ) {
        // check blob
        $blob = $this->post_to_blob( $post );
        if ( is_wp_error( $blob ) ) {
            /** @var WP_Error $blob */
            return $blob;
        }

        $result = false;

        $persist = $this->app->api()->persist();
        $github_path = $post->github_path();
        $old_github_path = $post->old_github_path();

        if ( $old_github_path && $old_github_path != $github_path ) {
            // rename
            $message = apply_filters(
                'wogh_commit_msg_move_post',
                sprintf(
                    'Move %s to %s via WordPress at %s (%s)',
                    $old_github_path, $github_path,
                    site_url(),
                    get_bloginfo( 'name' )
                )
            ) . $this->get_commit_msg_tag();

            $result = $persist->delete_file( $post->old_github_path(), $blob->sha(), $message );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $result = $persist->create_file( $blob, $message );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        } elseif ( ! $old_github_path ) {
            // create new
            $message = apply_filters(
                'wogh_commit_msg_new_post',
                sprintf(
                    'Create new post %s from WordPress at %s (%s)',
                    $github_path,
                    site_url(),
                    get_bloginfo( 'name' )
                )
            ) . $this->get_commit_msg_tag();
            $result = $persist->create_file( $blob, $message );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        } elseif ( $old_github_path && $old_github_path == $github_path ) {
            // update
            $sha = wogh_git_sha( $blob->content() );
            if ( $sha === $blob->sha() ) {
                // don't export when has not changed
                return true;
            }
            $message = apply_filters(
                'wogh_commit_msg_update_post',
                sprintf(
                    'Update post %s from WordPress at %s (%s)',
                    $github_path,
                    site_url(),
                    get_bloginfo( 'name' )
                )
            ) . $this->get_commit_msg_tag();
            $result = $persist->update_file( $blob, $message );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        $sha = $result->content->sha;
        $post->set_sha( $sha );
        $post->set_old_github_path( $github_path );

        return true;
    }

    /**
     * Deletes a provided post ID from master.
     *
     * @param int $post_id Post ID to delete.
     *
     * @return string|WP_Error
     */
    public function delete( $post_id ) {
        $post = $this->app->database()->fetch_by_id( $post_id );

        if ( is_wp_error( $post ) ) {
            /*　@var WP_Error $post */
            return $post;
        }

        $github_path = get_post_meta( $post_id, '_wogh_github_path', true );

        $message = apply_filters(
            'wogh_commit_msg_delete',
            sprintf(
                'Deleting %s via WordPress at %s (%s)',
                $github_path,
                site_url(),
                get_bloginfo( 'name' )
            ),
            $post
        ) . $this->get_commit_msg_tag();

        $result = $this->app->api()->persist()->delete_file( $github_path, $post->sha(), $message );

        if ( is_wp_error( $result ) ) {
            /*　@var WP_Error $result */
            return $result;
        }

        return __( 'Export to GitHub completed successfully.', 'writing-on-github' );
    }


    /**
     * Saves the export user to the database.
     *
     * @param int $user_id User ID to export with.
     *
     * @return bool
     */
    public function set_user( $user_id ) {
        return update_option( self::EXPORT_USER_OPTION, (int) $user_id );
    }

    /**
     * Gets the commit message tag.
     *
     * @return string
     */
    protected function get_commit_msg_tag() {
        $tag = apply_filters( 'wogh_commit_msg_tag', 'wogh' );

        if ( ! $tag ) {
            throw new Exception( __( 'Commit message tag not set. Filter `wogh_commit_msg_tag` misconfigured.', 'writing-on-github' ) );
        }

        return ' - ' . $tag;
    }
}
