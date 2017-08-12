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
     *
     * @return string|WP_Error
     */
    protected function import_files( $files ) {

        $error      = false;
        $delete_ids = false;

        $result = $this->compare( $files, $delete_ids );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( $delete_ids ) {
            foreach ($delete_ids as $id) {
                $result = $this->app->database()->delete_post( $id );
                if ( is_wp_error( $result ) ) {
                    /* @var WP_Error $result */
                    $error = wogh_append_error( $error, $result );
                }
            }
        }

        return $error;
    }

    /**
     * Imports the latest commit on the master branch.
     *
     * @return string|WP_Error
     */
    public function master() {
        $result = $this->app->api()->fetch()->tree_recursive();

        if ( is_wp_error( $result ) ) {
            /* @var WP_Error $result */
            return $result;
        }

        if ( is_array( $result ) ) {
            $result = $this->import_files( $result );
        }

        if ( is_wp_error( $result ) ) {
            /* @var WP_Error $result */
            return $result;
        }

        return __( 'Payload processed', 'writing-on-github' );
    }

    /**
     * Do compare
     * @param  Writing_On_GitHub_File_Info[]|WP_Error $files
     * @param  int[] &$delete_ids
     *
     * @return string|WP_Error
     */
    protected function compare( $files, &$delete_ids ) {
        if ( is_wp_error( $files ) ) {
            /* @var WP_Error $files */
            return $files;
        }

        $posts = array();
        $new   = array();

        $idsmap = array();

        foreach ( $files as $file ) {
            if ( ! $this->importable_file( $file ) ) {
                continue;
            }

            $blob = $this->app->api()->fetch()->blob( $file );
            // network error ?
            if ( ! $blob instanceof Writing_On_GitHub_Blob ) {
                continue;
            }

            if ( $this->importable_raw_file( $blob ) ) {
                $this->import_raw_file( $blob, $file->status == 'removed' );
                continue;
            }

            if ( ! $this->importable_blob( $blob ) ) {
                continue;
            }

            $post = $this->blob_to_post( $blob );

            if ( $file->status == 'removed' ) {
                if ( $blob->id() ) {
                    $idsmap[$blob->id()] = true;
                }
            } elseif ( $post != false ) {
                $posts[] = $post;
                if ( $post->is_new() ) {
                    $new[] = $post;
                }
            }
        }

        foreach ( $posts as $post ) {
            if ( $post->id() && isset( $idsmap[ $post->id() ] ) ) {
                unset( $idsmap[ $post->id() ] );
            }
        }
        $delete_ids = array();
        foreach ( $idsmap as $id => $value ) {
            $delete_ids[] = $id;
        }

        // $this->app->database()->save_posts( $posts, $commit->author_email() );

        $result = $this->app->database()->save_posts( $posts );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! empty( $new ) ) {
            $result = $this->app->export()->new_posts( $new );

            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        return $posts;
    }

    /**
     * Checks whether the provided blob should be imported.
     *
     * @param Writing_On_GitHub_File_Info $file
     *
     * @return bool
     */
    protected function importable_file( Writing_On_GitHub_File_Info $file ) {

        // only _pages and _posts
        if ( strncasecmp($file->path, '_pages/', strlen('_pages/') ) != 0 &&
             strncasecmp($file->path, '_posts/', strlen('_posts/') ) != 0 &&
             strncasecmp($file->path, 'images/', strlen('images/') ) != 0 ) {
            return false;
        }


        // if ( ! $file->has_frontmatter() ) {
        //  return false;
        // }

        return true;
    }

    /**
     * Checks whether the provided blob should be imported.
     *
     * @param Writing_On_GitHub_Blob $blob Blob to validate.
     *
     * @return bool
     */
    protected function importable_blob( Writing_On_GitHub_Blob $blob ) {
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
    }

    /**
     * Imports a single blob content into matching post.
     *
     * @param Writing_On_GitHub_Blob $blob Blob to transform into a Post.
     *
     * @return Writing_On_GitHub_Post|false
     */
    protected function blob_to_post( Writing_On_GitHub_Blob $blob ) {
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
                $blob->set_id($id);
                unset( $meta['ID'] );
            }
        }

        $meta['_wogh_sha'] = $blob->sha();

        if ( $id ) {
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
        $blob->set_id( $post->id() );

        return $post;
    }
}
