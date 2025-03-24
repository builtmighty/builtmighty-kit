<?php
/**
 * Custom Post Type.
 *
 * An extendable class for creating custom post types.
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

abstract class CPT {

    /**
     * Post type.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     string
     */
    protected string $post_type;

    /**
     * Arguments.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     array
     */
    protected array $args = [];

    /**
     * Labels.
     * 
     * @since   1.0.0
     * @access  protected
     * @var     array
     */
    protected array $labels = [];

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Check if post type is set.
        if( empty( $this->post_type ) ) {

            // Throw exception.
            throw new \Exception( 'Post type must be defined in the child class.' );

        }

        // Register post type.
        add_action( 'init', [ $this, 'register' ] );

    }

    /**
     * Registers the Custom Post Type.
     * 
     * @since   1.0.0
     */
    public function register() {

        // Set defaults.
        $defaults = [
            'labels'        => $this->get_labels(),
            'public'        => true,
            'show_in_menu'  => true,
            'has_archive'   => true,
            'supports'      => [ 'title', 'editor', 'thumbnail' ],
            'show_in_rest'  => true, // Enables Gutenberg support
        ];

        // Register post type.
        register_post_type( $this->post_type, array_merge( $defaults, $this->args ) );

    }

    /**
     * Get labels.
     * 
     * Retrieves the labels for the Custom Post Type.
     * 
     * @since   1.0.0
     */
    protected function get_labels(): array {

        // Return.
        return array_merge( [
            'name'              => ucfirst( $this->post_type ),
            'singular_name'     => ucfirst( $this->post_type ),
            'menu_name'         => ucfirst( $this->post_type ),
            'add_new'           => 'Add New',
            'add_new_item'      => 'Add New ' . ucfirst( $this->post_type ),
            'edit_item'         => 'Edit ' . ucfirst( $this->post_type ),
            'new_item'          => 'New ' . ucfirst( $this->post_type ),
            'view_item'         => 'View ' . ucfirst( $this->post_type ),
            'all_items'         => 'All ' . ucfirst( $this->post_type ) . 's',
            'search_items'      => 'Search ' . ucfirst( $this->post_type ),
            'not_found'         => 'No ' . $this->post_type . ' found.',
        ], $this->labels );

    }

}