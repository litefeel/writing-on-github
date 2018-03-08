<?php
/**
 * GitHub Import Manager
 *
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Import
 */
class Writing_On_GitHub_Import {

    /**
     * Application container.
     *
     * @var Writing_On_GitHub
     */
    protected $app;

    /**
     * Initializes a new import manager.
     *
     * @param Writing_On_GitHub $app Application container.
     */
    public function __construct( Writing_On_GitHub $app ) {
        $this->app = $app;
    }

    /**
     * Imports a payload.
     * @param  Writing_On_GitHub_Payload $payload
     *
     * @return string|WP_Error
     */
    public function payload( Writing_On_GitHub_Payload $payload ) {

        $result = $this->app->api()->fetch()->compare( $payload->get_before_commit_id() );

        if ( is_wp_error( $result ) ) {
            /* @var WP_Error $result */
            return $result;
        }

        if ( is_array( $result ) ) {
            $result = $this->import_files( $result );
        }

        if ( is_wp_error( $result ) ) {
            return $files;
        }

        return __( 'Payload processed', 'writing-on-github' );
    }

    /**
     * import blob by files
     * @param  Writing_On_GitHub_File_Info[] $files
     * @param  boolean $force
     *
     * @return true|WP_Error
     */
    protected function import_files( $files, $force = false ) {

        $error = true;

        foreach ( $files as $file ) {
            if ( ! $this->importable_file( $file ) ) {
                continue;
            }

            $blob = $this->app->api()->fetch()->blob( $file );
            // network error ?
            if ( ! $blob instanceof Writing_On_GitHub_Blob ) {
                continue;
            }

            $is_remove = 'removed' == $file->status;

            $result = false;
            if ( $this->importable_raw_file( $blob ) ) {
                $result = $this->import_raw_file( $blob, $is_remove );
            } elseif ( $this->importable_post( $blob ) ) {
                if ( $is_remove ) {
                    $result = $this->delete_post( $blob );
                } else {
                    $result = $this->import_post( $blob, $force );
                }
            }

            if ( is_wp_error( $result ) ) {
                /* @var WP_Error $result */
                $error = wogh_append_error( $error, $result );
            }
        }

        return $error;
    }

    /**
     * Imports the latest commit on the master branch.
     *
     * @param  boolean $force
     * @return string|WP_Error
     */
    public function master( $force = false ) {
        $result = $this->app->api()->fetch()->tree_recursive();

        if ( is_wp_error( $result ) ) {
            /* @var WP_Error $result */
            return $result;
        }

        if ( is_array( $result ) ) {
            $result = $this->import_files( $result, $force );
        }

        if ( is_wp_error( $result ) ) {
            /* @var WP_Error $result */
            return $result;
        }

        return __( 'Payload processed', 'writing-on-github' );
    }

    /**
     * Checks whether the provided blob should be imported.
     *
     * @param Writing_On_GitHub_File_Info $file
     *
     * @return bool
     */
    protected function importable_file( Writing_On_GitHub_File_Info $file ) {

        $path = $file->path;

        // only _pages, _posts and images
        $prefixs = array( '_pages/', '_posts/', 'images/');
        foreach ($prefixs as $prefix) {
            if ( ! strncasecmp($path, $prefix, strlen( $prefix ) ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks whether the provided blob should be imported.
     *
     * @param Writing_On_GitHub_Blob $blob Blob to validate.
     *
     * @return bool
     */
    protected function importable_post( Writing_On_GitHub_Blob $blob ) {
        // global $wpdb;

        // // Skip the repo's readme.
        // if ( 'readme' === strtolower( substr( $blob->path(), 0, 6 ) ) ) {
        //  return false;
        // }

        // // If the blob sha already matches a post, then move on.
        // if ( ! is_wp_error( $this->app->database()->fetch_by_sha( $blob->sha() ) ) ) {
        //  return false;
        // }

        if ( ! $blob->has_frontmatter() ) {
            return false;
        }

        return true;
    }

    /**
     * Delete post
     * @param  Writing_On_GitHub_Blob $blob
     * @return WP_Error|bool
     */
    protected function delete_post( Writing_On_GitHub_Blob $blob ) {
        $id = $blob->id();
        if ( empty( $id ) ) {
            return false;
        }
        $result = $this->app->database()->delete_post( $id );
        if ( is_wp_error( $result ) ) {
            /* @var WP_Error $result */
            return $result;
        }
        return true;
    }

    /**
     * Imports a post into wordpress
     * @param  Writing_On_GitHub_Blob $blob
     * @param  boolean                $force
     * @return WP_Error|bool
     */
    protected function import_post( Writing_On_GitHub_Blob $blob, $force = false ) {
        $post = $this->blob_to_post( $blob, $force );

        if ( ! $post instanceof Writing_On_GitHub_Post ) {
            return false;
        }

        $result = $this->app->database()->save_post( $post );
        if ( is_wp_error( $result ) ) {
            /** @var WP_Error $result */
            return $result;
        }

        if ( $post->is_new() ||
                ! wogh_equal_front_matter( $post, $blob ) ) {

            $result = $this->app->export()->export_post( $post );

            if ( is_wp_error( $result ) ) {
                /** @var WP_Error $result */
                return $result;
            }
        }

        clean_post_cache( $post->id() );

        return true;
    }

    /**
     * import raw file
     * @param  Writing_On_GitHub_Blob $blob
     * @return bool
     */
    protected function importable_raw_file( Writing_On_GitHub_Blob $blob ) {
        if ( $blob->has_frontmatter() ) {
            return false;
        }

        // only images
        if ( strncasecmp($blob->path(), 'images/', strlen('images/') ) != 0) {
            return false;
        }

        return true;
    }

    /**
     * Imports a raw file content into file system.
     * @param  Writing_On_GitHub_Blob $blob
     * @param  bool                   $is_remove
     */
    protected function import_raw_file( Writing_On_GitHub_Blob $blob, $is_remove ) {
        $arr = wp_upload_dir();
        $path = $arr['basedir'] . '/writing-on-github/' . $blob->path();
        if ( $is_remove ) {
            if ( file_exists($path) ) {
                unlink($path);
            }
        } else {
            $dirname = dirname($path);
            if ( ! file_exists($dirname) ) {
                wp_mkdir_p($dirname);
            }

            file_put_contents($path, $blob->content());
        }
        return true;
    }

    /**
     * Imports a single blob content into matching post.
     *
     * @param Writing_On_GitHub_Blob $blob Blob to transform into a Post.
     * @param boolean                $force
     *
     * @return Writing_On_GitHub_Post|false
     */
    protected function blob_to_post( Writing_On_GitHub_Blob $blob, $force = false ) {
        $args = array( 'post_content' => $blob->content_import() );
        $meta = $blob->meta();

        $id = false;

        if ( ! empty( $meta ) ) {
            if ( array_key_exists( 'layout', $meta ) ) {
                $args['post_type'] = $meta['layout'];
                unset( $meta['layout'] );
            }

            if ( array_key_exists( 'published', $meta ) ) {
                $args['post_status'] = true === $meta['published'] ? 'publish' : 'draft';
                unset( $meta['published'] );
            }

            if ( array_key_exists( 'post_title', $meta ) ) {
                $args['post_title'] = $meta['post_title'];
                unset( $meta['post_title'] );
            }

            if ( array_key_exists( 'post_name', $meta ) ) {
                $args['post_name'] = $meta['post_name'];
                unset( $meta['post_name'] );
            }

            if ( array_key_exists( 'ID', $meta ) ) {
                $id = $args['ID'] = $meta['ID'];
                $blob->set_id( $id );
                unset( $meta['ID'] );
            }

            if ( array_key_exists( 'post_date', $meta ) ) {
                if ( empty( $meta['post_date'] ) ) {
                    $meta['post_date'] = current_time( 'mysql' );
                }

                $args['post_date'] = $meta['post_date'];
                unset( $meta['post_date'] );
            }
        }

        $meta['_wogh_sha'] = $blob->sha();

        if ( ! $force && $id ) {
            $old_sha = get_post_meta( $id, '_wogh_sha', true );
            $old_github_path = get_post_meta( $id, '_wogh_github_path', true );

            // dont save post when has same sha
            if ( $old_sha  && $old_sha == $meta['_wogh_sha'] &&
                 $old_github_path && $old_github_path == $blob->path() ) {
                return false;
            }
        }

        $post = new Writing_On_GitHub_Post( $args, $this->app->api() );
        $post->set_old_github_path( $blob->path() );
        $post->set_meta( $meta );
        $post->set_blob( $blob );
        $blob->set_id( $post->id() );

        return $post;
    }
}
