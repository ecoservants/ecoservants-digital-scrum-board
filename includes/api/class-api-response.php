<?php
/**
 * Standardized API Response Helpers for EcoServants Scrum Board
 *
 * Provides consistent JSON response formatting across all REST endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EcoServants_API_Response {

    /**
     * Create a success response.
     *
     * @param mixed $data   Response data.
     * @param int   $status HTTP status code.
     * @return WP_REST_Response
     */
    public static function success( $data, $status = 200 ) {
        return new WP_REST_Response( $data, $status );
    }

    /**
     * Create an error response.
     *
     * @param string $code    Error code identifier.
     * @param string $message Human-readable error message.
     * @param int    $status  HTTP status code.
     * @return WP_Error
     */
    public static function error( $code, $message, $status = 400 ) {
        return new WP_Error( $code, $message, array( 'status' => $status ) );
    }

    /**
     * Create a paginated response with standard WP headers.
     *
     * @param array $data     Array of result items.
     * @param int   $total    Total number of items across all pages.
     * @param int   $page     Current page number.
     * @param int   $per_page Items per page.
     * @return WP_REST_Response
     */
    public static function paginated( $data, $total, $page, $per_page ) {
        $max_pages = ceil( $total / $per_page );

        $response = rest_ensure_response( $data );
        $response->header( 'X-WP-Total', (int) $total );
        $response->header( 'X-WP-TotalPages', (int) $max_pages );

        return $response;
    }

    /**
     * Parse pagination parameters from a request.
     *
     * @param WP_REST_Request $request The REST request.
     * @param int             $default_per_page Default items per page.
     * @param int             $max_per_page Maximum allowed items per page.
     * @return array { page: int, per_page: int, offset: int }
     */
    public static function parse_pagination( $request, $default_per_page = 20, $max_per_page = 100 ) {
        // Explicitly cast to integer and enforce a minimum of 1.
        $raw_page = $request->get_param( 'page' );
        $page     = max( 1, (int) $raw_page );
        
        $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : $default_per_page;

        // Cap per_page to prevent abuse
        $per_page = max( 1, min( $per_page, $max_per_page ) );
        $offset   = ( $page - 1 ) * $per_page;

        return array(
            'page'     => $page,
            'per_page' => $per_page,
            'offset'   => $offset,
        );
    }
}
