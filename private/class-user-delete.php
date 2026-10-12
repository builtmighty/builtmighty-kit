<?php
/**
 * User Delete.
 *
 * Keeps the Delete Users screen usable on sites with large user tables.
 *
 * Core fills the "Attribute all content to another user" drop-down with every
 * account on the site. Once a site has a few hundred thousand users, building
 * that list exhausts PHP memory and the screen fails before it can render. This
 * limits the drop-down to a few suggestions (people who can write content, or
 * only the current user on sites core considers large), adds a paginated search
 * for everyone else, and explains what "Delete all content" would remove,
 * preselecting it when deleting the account would remove nothing.
 *
 * @package Built Mighty Kit
 * @since   5.2.0
 * @version 5.2.0
 */
namespace BuiltMightyKit\Private;
class user_delete {

    /**
     * AJAX action, nonce action, and minimum search length.
     *
     * @since   5.2.0
     */
    const ACTION    = 'kit_user_delete_search';
    const NONCE     = 'kit-user-delete-search';
    const MIN_CHARS = 2;

    /**
     * Construct.
     *
     * @since   5.2.0
     */
    public function __construct() {

        // Check. Enabled unless explicitly disabled.
        if( get_option( 'kit_user_delete_search' ) === 'disable' ) return;

        // Actions.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_ajax_' . self::ACTION, [ $this, 'search' ] );

        // Filters.
        add_filter( 'wp_dropdown_users_args', [ $this, 'dropdown_args' ], 20, 2 );

    }

    /**
     * Limit the reassign drop-down.
     *
     * Without a limit, core loads every account on the site into it.
     *
     * @param   array  $query_args   Arguments for get_users().
     * @param   array  $parsed_args  Arguments passed to wp_dropdown_users().
     * @return  array
     *
     * @since   5.2.0
     */
    public function dropdown_args( $query_args, $parsed_args ) {

        // Check.
        if( ! $this->is_reassign_dropdown( $parsed_args ) ) return $query_args;

        // Cap the list. Everyone else is reachable through the search.
        $query_args['number'] = max( 1, (int) apply_filters( 'kit_user_delete_dropdown_limit', 50 ) );

        // On large sites, any role or capability query scans every account (36 seconds on a
        // 592,000-user site), so suggest only the current user, which is a primary-key lookup.
        $large = function_exists( 'wp_is_large_user_count' ) && wp_is_large_user_count();
        if( ! (bool) apply_filters( 'kit_user_delete_suggest_authors', ! $large ) ) {

            // Set.
            $query_args['include'] = [ get_current_user_id() ];

            // Return.
            return $query_args;

        }

        // Suggest people who can write content, unless another filter already limited the list.
        if( empty( $query_args['role'] ) && empty( $query_args['role__in'] ) && empty( $query_args['capability'] ) && empty( $query_args['capability__in'] ) ) {

            // Set.
            $query_args['capability'] = [ 'edit_posts' ];

        }

        // Return.
        return $query_args;

    }

    /**
     * Is this the reassign drop-down?
     *
     * WordPress 7.0+ names it reassign_user[ID] (one per user); older versions name it reassign_user.
     *
     * @param   array  $parsed_args  Arguments passed to wp_dropdown_users().
     * @return  bool
     *
     * @since   5.2.0
     */
    public function is_reassign_dropdown( $parsed_args ) {

        // Return.
        return is_admin() && ! empty( $parsed_args['name'] ) && strpos( (string) $parsed_args['name'], 'reassign_user' ) === 0;

    }

    /**
     * Enqueue.
     *
     * @param   string  $hook  Current admin page.
     *
     * @since   5.2.0
     */
    public function enqueue( $hook ) {

        // Check. Single-site Delete Users screen only; core checks the nonce before this runs.
        if( $hook !== 'users.php' || is_multisite() ) return;
        if( ! isset( $_REQUEST['action'] ) || $_REQUEST['action'] !== 'delete' ) return;
        if( ! current_user_can( 'delete_users' ) ) return;

        // Users being deleted. Core never deletes the current user.
        $user_ids = array_values( array_diff( $this->requested_user_ids(), [ get_current_user_id() ] ) );

        // Assets.
        wp_enqueue_style( 'builtmighty-kit-user-delete', KIT_URI . 'public/css/user-delete.css', [], $this->asset_version( 'public/css/user-delete.css' ) );
        wp_enqueue_script( 'builtmighty-kit-user-delete', KIT_URI . 'public/js/user-delete.js', [ 'common' ], $this->asset_version( 'public/js/user-delete.js' ), true );

        // Data.
        wp_localize_script( 'builtmighty-kit-user-delete', 'kitUserDelete', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'action'   => self::ACTION,
            'nonce'    => wp_create_nonce( self::NONCE ),
            'minChars' => self::MIN_CHARS,
            'exclude'  => $user_ids,
            'users'    => $this->content_summaries( $user_ids ),
            'i18n'     => [
                'label'         => __( 'Search for a user to attribute the content to', 'builtmighty-kit' ),
                'placeholder'   => __( 'Search by name, username, or email', 'builtmighty-kit' ),
                'selectUser'    => __( 'Select a user', 'builtmighty-kit' ),
                'suggestedOne'  => __( '1 suggested user. Type to search all users.', 'builtmighty-kit' ),
                /* translators: %d: Number of suggested users. */
                'suggestedMany' => __( '%d suggested users. Type to search all users.', 'builtmighty-kit' ),
                'typeToSearch'  => __( 'Type to search all users.', 'builtmighty-kit' ),
                /* translators: %d: Minimum number of characters. */
                'typeMore'      => sprintf( __( 'Type at least %d characters to search.', 'builtmighty-kit' ), self::MIN_CHARS ),
                'searching'     => __( 'Searching…', 'builtmighty-kit' ),
                'noResults'     => __( 'No users found.', 'builtmighty-kit' ),
                'resultOne'     => __( '1 user found.', 'builtmighty-kit' ),
                /* translators: %d: Number of users shown. */
                'resultMany'    => __( '%d users found.', 'builtmighty-kit' ),
                'moreAvailable' => __( 'More are available: choose "Load more results".', 'builtmighty-kit' ),
                'loadMore'      => __( 'Load more results', 'builtmighty-kit' ),
                /* translators: %s: Selected user's name and username. */
                'selected'      => __( 'Selected: %s', 'builtmighty-kit' ),
                'chooseUser'    => __( 'Choose a user to attribute the content to, or select "Delete all content".', 'builtmighty-kit' ),
                'error'         => __( 'The search failed. Try again.', 'builtmighty-kit' ),
            ],
        ] );

    }

    /**
     * Search.
     *
     * Paginated user search for the reassign field. Core's /wp/v2/users endpoint is
     * not used because the Kit removes it to prevent user enumeration.
     *
     * @since   5.2.0
     */
    public function search() {

        // Check.
        check_ajax_referer( self::NONCE, 'nonce' );
        if( ! current_user_can( 'delete_users' ) ) {
            wp_send_json_error( [ 'message' => __( 'Sorry, you are not allowed to search users.', 'builtmighty-kit' ) ], 403 );
        }

        // Term. Wildcards are added below, so strip any the user typed.
        $term = isset( $_GET['q'] ) ? trim( str_replace( '*', '', sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) ) : '';
        if( mb_strlen( $term ) < self::MIN_CHARS ) {
            wp_send_json_success( [ 'results' => [], 'more' => false ] );
        }

        // Paging.
        $page     = max( 1, absint( $_GET['page'] ?? 1 ) );
        $per_page = min( 100, max( 1, (int) apply_filters( 'kit_user_delete_search_per_page', 20 ) ) );
        $exclude  = array_values( array_filter( array_map( 'absint', (array) ( $_GET['exclude'] ?? [] ) ) ) );

        // Query. One extra row tells us whether there is another page without counting every match.
        $args = apply_filters( 'kit_user_delete_search_args', [
            'search'      => '*' . $term . '*',
            'exclude'     => $exclude,
            'number'      => $per_page + 1,
            'offset'      => ( $page - 1 ) * $per_page,
            'orderby'     => 'display_name',
            'order'       => 'ASC',
            'fields'      => [ 'ID', 'user_login', 'user_email', 'display_name' ],
            'count_total' => false,
        ], $term );
        $users = get_users( $args );

        // More?
        $more  = count( $users ) > $per_page;
        $users = array_slice( $users, 0, $per_page );

        // Results. Same "Name (login)" format as core's drop-down, or just the login when there is no name.
        $results = array_map( function( $user ) {
            return [
                'id'    => (int) $user->ID,
                'text'  => trim( $user->display_name ) !== '' ? sprintf( _x( '%1$s (%2$s)', 'user dropdown' ), $user->display_name, $user->user_login ) : $user->user_login,
                'email' => $user->user_email,
            ];
        }, $users );

        // Send.
        wp_send_json_success( [ 'results' => $results, 'more' => $more ] );

    }

    /**
     * Content summaries.
     *
     * Works out what "Delete all content" would remove for each user, using the
     * same rules as wp_delete_user(). Post types that are not deleted with the user,
     * such as WooCommerce order placeholders, stay in place either way.
     *
     * @param   int[]  $user_ids  Users being deleted.
     * @return  array             Keyed by user ID. Users with no content are omitted.
     *
     * @since   5.2.0
     */
    public function content_summaries( $user_ids ) {

        // Global.
        global $wpdb;

        // Summaries.
        $summaries = [];

        // Loop.
        foreach( $user_ids as $user_id ) {

            // User.
            $user = get_userdata( $user_id );
            if( ! $user ) continue;

            // Content.
            $rows       = $wpdb->get_results( $wpdb->prepare( "SELECT post_type, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_author = %d GROUP BY post_type", $user_id ) );
            $links      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->links} WHERE link_owner = %d", $user_id ) );
            $additional = (bool) apply_filters( 'users_have_additional_content', false, [ $user_id ] );

            // Check. Core shows no choice for users without content.
            if( empty( $rows ) && ! $links && ! $additional ) continue;

            // Sort into deleted and kept.
            $deletable = $this->post_types_deleted_with( $user_id );
            $deleted   = [];
            $kept      = [];
            foreach( $rows as $row ) {
                $label = $this->count_label( $row->post_type, (int) $row->total );
                if( in_array( $row->post_type, $deletable, true ) ) {
                    $deleted[] = $label;
                } else {
                    $kept[] = $label;
                }
            }
            if( $links ) {
                /* translators: %s: Number of links. */
                $deleted[] = sprintf( _n( '%s link', '%s links', $links, 'builtmighty-kit' ), number_format_i18n( $links ) );
            }

            // Safe when deleting removes nothing.
            $safe = empty( $deleted ) && ! $additional;

            // Message.
            if( $safe ) {
                /* translators: %s: User login. */
                $title  = sprintf( __( '"Delete all content" is selected for %s.', 'builtmighty-kit' ), $user->user_login );
                /* translators: %s: List of content, such as "2 WooCommerce order placeholders". */
                $detail = sprintf( __( 'This account owns only %s, which WordPress keeps when an account is deleted. Deleting removes the account and nothing else.', 'builtmighty-kit' ), wp_sprintf( '%l', $kept ) );
            } elseif( ! empty( $deleted ) ) {
                /* translators: 1: List of content, such as "3 Posts and 1 Page". 2: User login. */
                $title  = sprintf( __( '"Delete all content" would permanently delete %1$s owned by %2$s.', 'builtmighty-kit' ), wp_sprintf( '%l', $deleted ), $user->user_login );
                $detail = __( 'To keep it, attribute the content to another user.', 'builtmighty-kit' );
                if( ! empty( $kept ) ) {
                    /* translators: %s: List of content that is kept. */
                    $detail .= ' ' . sprintf( __( 'Kept either way: %s.', 'builtmighty-kit' ), wp_sprintf( '%l', $kept ) );
                }
            } else {
                $title  = '';
                $detail = '';
            }
            if( $additional ) {
                /* translators: %s: User login. */
                $notice = sprintf( __( 'A plugin reports other content owned by %s, which "Delete all content" may remove. Check before choosing.', 'builtmighty-kit' ), $user->user_login );
                if( $title === '' ) {
                    $title = $notice;
                } else {
                    $detail .= ' ' . $notice;
                }
            }

            // Add.
            $summaries[ $user_id ] = [
                'login'  => $user->user_login,
                'safe'   => $safe,
                'tone'   => $safe ? 'info' : 'warning',
                'title'  => $title,
                'detail' => $detail,
            ];

        }

        // Return.
        return $summaries;

    }

    /**
     * Post types deleted with a user.
     *
     * Mirrors wp_delete_user(): a post type is deleted when it opts in with
     * delete_with_user, or leaves that unset and supports authors.
     *
     * @param   int  $user_id  User being deleted.
     * @return  string[]
     *
     * @since   5.2.0
     */
    public function post_types_deleted_with( $user_id ) {

        // Types.
        $types = [];

        // Loop.
        foreach( get_post_types( [], 'objects' ) as $post_type ) {
            if( $post_type->delete_with_user ) {
                $types[] = $post_type->name;
            } elseif( null === $post_type->delete_with_user && post_type_supports( $post_type->name, 'author' ) ) {
                $types[] = $post_type->name;
            }
        }

        // Return. Same filter wp_delete_user() applies.
        return (array) apply_filters( 'post_types_to_delete_with_user', $types, $user_id );

    }

    /**
     * Count label.
     *
     * @param   string  $post_type  Post type.
     * @param   int     $count      Number of items.
     * @return  string              For example "2 WooCommerce order placeholders".
     *
     * @since   5.2.0
     */
    public function count_label( $post_type, $count ) {

        // WooCommerce order types register without labels, so core would call them "Posts".
        $known = apply_filters( 'kit_user_delete_post_type_labels', [
            'shop_order_placehold' => [ __( 'WooCommerce order placeholder', 'builtmighty-kit' ), __( 'WooCommerce order placeholders', 'builtmighty-kit' ) ],
            'shop_order'           => [ __( 'WooCommerce order', 'builtmighty-kit' ), __( 'WooCommerce orders', 'builtmighty-kit' ) ],
            'shop_order_refund'    => [ __( 'WooCommerce refund', 'builtmighty-kit' ), __( 'WooCommerce refunds', 'builtmighty-kit' ) ],
            'shop_subscription'    => [ __( 'subscription', 'builtmighty-kit' ), __( 'subscriptions', 'builtmighty-kit' ) ],
        ] );

        // Labels.
        if( isset( $known[ $post_type ] ) ) {
            [ $singular, $plural ] = $known[ $post_type ];
        } else {
            $object   = get_post_type_object( $post_type );
            $singular = $object ? $object->labels->singular_name : $post_type;
            $plural   = $object ? $object->labels->name : $post_type;

            // Unlabeled types fall back to core's "Posts"/"Pages"; add the type so they aren't mistaken for blog posts.
            $generic = [ _x( 'Posts', 'post type general name' ), _x( 'Pages', 'post type general name' ) ];
            if( $object && ! in_array( $post_type, [ 'post', 'page' ], true ) && in_array( $plural, $generic, true ) ) {
                $singular .= ' (' . $post_type . ')';
                $plural   .= ' (' . $post_type . ')';
            }
        }

        // Return.
        return number_format_i18n( $count ) . ' ' . ( $count === 1 ? $singular : $plural );

    }

    /**
     * Requested user IDs.
     *
     * @return  int[]
     *
     * @since   5.2.0
     */
    public function requested_user_ids() {

        // Users.
        if( ! empty( $_REQUEST['users'] ) ) {
            $ids = (array) $_REQUEST['users'];
        } elseif( ! empty( $_REQUEST['user'] ) ) {
            $ids = [ $_REQUEST['user'] ];
        } else {
            $ids = [];
        }

        // Return.
        return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

    }

    /**
     * Asset version.
     *
     * Adds the file's modified time so browsers pick up changes between Kit releases.
     *
     * @param   string  $path  Path relative to the plugin root.
     * @return  string
     *
     * @since   5.2.0
     */
    public function asset_version( $path ) {

        // Modified.
        $modified = @filemtime( KIT_PATH . $path );

        // Return.
        return $modified ? KIT_VERSION . '.' . $modified : KIT_VERSION;

    }

}
