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
