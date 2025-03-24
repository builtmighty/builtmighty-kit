<?php
/**
 * REST Controller.
 *
 * An extendable class for creating REST API routes.
 *
 * @package Built Mighty Kit
 * @since   1.0.0
 * @version 1.0.0
 */
namespace BuiltMightyKit\Utility;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Disallow direct access.
 * 
 * @since   1.0.0
 */
if( ! defined( 'ABSPATH' ) ) { exit; }

abstract class Controller extends WP_REST_Controller {

    /**
     * Resource name.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     string
     */
    protected string $resource_name = '';

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check if resource name is set.
        if( empty( $this->resource_name ) ) {

            // Throw exception.
            throw new \Exception( 'Resource name must be set in the child class.' );

        }

    }

    /**
     * Registers routes.
     * 
     * @since   1.0.0
     */
    public function register_routes() {

        // Register routes.
        register_rest_route( $this->namespace, '/' . $this->resource_name, [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_items' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => $this->get_endpoint_args( 'GET' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'create_item' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => $this->get_endpoint_args( 'POST' ),
            ],
        ] );

        // Register routes.
        register_rest_route( $this->namespace, '/' . $this->resource_name . '/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_item' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => $this->get_endpoint_args( 'GET' ),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_item' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
        ] );

    }

    /**
     * Retrieves items.
     * 
     * @since   1.0.0
     * 
     * @param   WP_REST_Request  $request
     * 
     * @return  WP_REST_Response
     */
    public function get_items( $request ): WP_REST_Response {

        // Return response.
        return rest_ensure_response( [
            'success' => true,
            'message' => 'GET items not implemented',
        ] );

    }

    /**
     * Retrieves a single item.
     * 
     * @since   1.0.0
     * 
     * @param   WP_REST_Request  $request
     * 
     * @return  WP_REST_Response
     */
    public function get_item( $request ): WP_REST_Response {

        // Return response.
        return rest_ensure_response( [
            'success' => true,
            'message' => 'GET item not implemented',
            'id'      => $request['id'],
        ] );

    }

    /**
     * Creates an item.
     * 
     * @since   1.0.0
     * 
     * @param   WP_REST_Request  $request
     * 
     * @return  WP_REST_Response
     */
    public function create_item( $request ): WP_REST_Response {

        // Return response.
        return rest_ensure_response( [
            'success' => true,
            'message' => 'POST item not implemented',
            'data'    => $request->get_params(),
        ] );

    }

    /**
     * Deletes an item.
     * 
     * @since   1.0.0
     * 
     * @param   WP_REST_Request  $request
     * 
     * @return  WP_REST_Response
     */
    public function delete_item( $request ): WP_REST_Response {

        // Return response.
        return rest_ensure_response( [
            'success' => true,
            'message' => 'DELETE item not implemented',
            'id'      => $request['id'],
        ] );

    }

    /**
     * Permissions.
     * 
     * @since   1.0.0
     * 
     * @param   WP_REST_Request  $request
     * 
     * @return  bool
     */
    public function permissions_check( WP_REST_Request $request ): bool {

        // Check permissions.
        return current_user_can( 'manage_options' );

    }

    /**
     * Defines argument validation for requests.
     * 
     * @since   1.0.0
     * 
     * @param   string  $method
     * 
     * @return  array
     */
    protected function get_endpoint_args( string $method ): array {

        // Define args.
        $args = [];

        // Check method.
        if( $method === 'GET' ) {

            // Define args.
            $args['id'] = [
                'validate_callback' => function ( $param ) {
                    return is_numeric( $param );
                },
                'description'       => 'The ID of the resource',
                'type'              => 'integer',
            ];

        }

        // Check method.
        if( $method === 'POST' ) {

            // Define args.
            $args['name'] = [
                'required'          => true,
                'validate_callback' => function ( $param ) {
                    return is_string( $param ) && !empty( $param );
                },
                'description'       => 'The name of the resource',
                'type'              => 'string',
            ];
        }

        // Return args.
        return $args;

    }
    
}