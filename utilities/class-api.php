<?php
/**
 * API.
 *
 * An extendable class for interacting with APIs.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Utility;

/**
 * Disallow direct access.
 * 
 * @since   1.0.0
 */
if( ! defined( 'ABSPATH' ) ) { exit; }

abstract class API {

    /**
     * Base URL.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     string
     */
    protected string $base_url;

    /**
     * Headers.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     array
     */
    protected array $headers = [];

    /**
     * Construct.
     * 
     * @param  string  $base_url
     * @param  array   $headers
     * 
     * @since   1.0.0
     */
    public function __construct( string $base_url, array $headers = [] ) {

        // Set base URL.
        $this->base_url = rtrim( $base_url, '/' );

        // Set headers.
        $this->headers  = array_merge( [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ], $headers );

    }

    /**
     * Performs a GET request.
     * 
     * @param  string  $endpoint
     * @param  array   $query_params
     * 
     * @since   1.0.0
     */
    protected function get( string $endpoint, array $query_params = [] ): array {

        // Build URL.
        $url = $this->build_url( $endpoint, $query_params );

        // Perform request.
        $response = wp_remote_get( $url, [
            'headers' => $this->headers,
            'timeout' => 10,
        ] );

        // Handle response and return.
        return $this->handle_response( $response );

    }

    /**
     * Performs a POST request.
     * 
     * @param  string  $endpoint
     * @param  array   $body
     * 
     * @since   1.0.0
     */
    protected function post( string $endpoint, array $body = [] ): array {

        // Build URL.
        $url = $this->base_url . '/' . ltrim( $endpoint, '/' );

        // Perform request.
        $response = wp_remote_post( $url, [
            'headers' => $this->headers,
            'body'    => json_encode( $body ),
            'timeout' => 10,
        ] );

        // Handle response and return.
        return $this->handle_response( $response );

    }

    /**
     * Performs a PUT request.
     * 
     * @param  string  $endpoint
     * @param  array   $body
     * 
     * @since   1.0.0
     */
    protected function put( string $endpoint, array $body = [] ): array {

        // Build URL.
        $url = $this->base_url . '/' . ltrim( $endpoint, '/' );

        // Perform request.
        $response = wp_remote_post( $url, [
            'headers' => $this->headers,
            'body'    => json_encode( $body ),
            'timeout' => 10,
        ] );

        // Handle response and return.
        return $this->handle_response( $response );

    }

    /**
     * Performs a DELETE request.
     * 
     * @param  string  $endpoint
     * 
     * @since   1.0.0
     */
    protected function delete( string $endpoint ): array {

        // Build URL.
        $url = $this->base_url . '/' . ltrim( $endpoint, '/' );

        // Perform request.
        $response = wp_remote_post( $url, [
            'headers' => $this->headers,
            'timeout' => 10,
        ] );

        // Handle response and return.
        return $this->handle_response( $response );

    }

    /**
     * Handles the response from the API.
     * 
     * @param  mixed  $response
     * 
     * @since   1.0.0
     */
    private function handle_response( $response ): array {

        // Check for errors.
        if( is_wp_error( $response ) ) return [ 'success' => false, 'error' => $response->get_error_message() ];

        // Get response code and body.
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Check for success.
        if( $code >= 200 && $code < 300 ) return [ 'success' => true, 'data' => $body ];

        // Return error.
        return [
            'success' => false,
            'error'   => $body['message'] ?? 'Unknown API error',
            'status'  => $code,
        ];

    }

    /**
     * Builds a full URL with query parameters.
     * 
     * @param  string  $endpoint
     * @param  array   $params
     * 
     * @since   1.0.0
     */
    private function build_url( string $endpoint, array $params = [] ): string {

        // Set URL.
        $url = $this->base_url . '/' . ltrim( $endpoint, '/' );

        // Return URL.
        return empty( $params ) ? $url : add_query_arg( $params, $url );

    }

}