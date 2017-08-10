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
