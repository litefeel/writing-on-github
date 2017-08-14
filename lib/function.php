<?php


/**
 * Append error
 * @param  mixed|WP_Error $error
 * @param  WP_Error   $error2
 * @return WP_Error
 */
function wogh_append_error( $error, $error2 ) {
    if ( is_wp_error( $error ) ) {
        $error->add( $error2->get_error_code(), $error2->get_error_message() );
    }
    return $error2;
}

/**
 * Test is equal front matter of post and blob
 * @param  Writing_On_GitHub_Post $post
 * @param  Writing_On_GitHub_Blob $blob
 * @return bool
 */
function wogh_equal_front_matter( $post, $blob ) {
    $str1 = $post->front_matter();
    $str2 = $blob->front_matter();
    return trim($str1) === trim($str2);
}

/**
 * Check is dont export wordpress content
 * @return bool
 */
function wogh_is_dont_export_content() {
    return 'yes' === get_option( 'wogh_dont_export_content' );
}

/**
 * Calc git sha
 * https://git-scm.com/book/en/v2/Git-Internals-Git-Objects#_object_storage
 * @param  string $content
 * @return string
 */
function wogh_git_sha( $content ) {
    // $header = "blob $len\0"
    // sha1($header . $content)
    $len = strlen( $content );
    return sha1( "blob $len\0$content" );
}
