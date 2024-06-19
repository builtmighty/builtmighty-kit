<?php
/**
 * Jira.
 * 
 * Adds connectivity to Jira.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Plugins;
class builtJira {

    /**
     * Variables.
     * 
     * @since   1.0.0
     */
    private $user_email;
    private $api_token;
    private $api_url;

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Set.
        $this->user_email   = ( ! empty( get_option( 'jira-user' ) ) ) ? get_option( 'jira-user' ) : false;
        $this->api_token    = ( ! empty( get_option( 'jira-token' ) ) ) ? unserialize( get_option( 'jira-token' ) ) : false;
        $this->api_url      = 'https://builtmighty.atlassian.net/rest/api/3/';

        // Check for values.
        if( $this->api_token ) {

            // Get keys.
            $keys = new \BuiltMightyKit\Security\builtKeys();

            // Decrypt token.
            $this->api_token = $keys->decrypt( $this->api_token );

        }

    }

    /**
     * Store projects.
     * 
     * @since   1.0.0
     */
    public function store_projects( $refresh = false ) {

        // Check for user email and API token.
        if( ! $this->user_email || ! $this->api_token ) return false;

        // Check if set or refreshing.
        if( $refresh == false && get_option( 'jira_api_projects' ) ) return;

        // Delete.
        delete_option( 'jira_api_projects' );

        // Get Jira Helper.
        $help = new \BuiltMightyKit\Plugins\builtJiraHelper();

        // Set range.
        $range = range( 0, 10 );

        // Set projects.
        $projects = [];

        // Loop. 
        foreach( $range as $count ) {

            // Set offset.
            $offset = $count * 50;

            // Get current.
            $current = $this->get_projects( $offset );

            // If current is empty, break.
            if( empty( $current['values'] ) ) break;

            // Sort.
            $current = $help->sort_projects( $current );

            // Get and store projects.
            $projects = array_merge( $projects, $current );

        }

        // Sort.
        $help = new \BuiltMightyKit\Plugins\builtJiraHelper();

        // Save.
        update_option( 'jira_api_projects', $projects );

        // Return.
        return $projects;

    }

    /**
     * Store users.
     * 
     * @since   1.0.0
     */
    public function store_users( $refresh = false ) {

            // Check for user email and API token.
            if( ! $this->user_email || ! $this->api_token ) return false;

            // Check if set.
            if( $refresh == false && get_option( 'jira_api_users' ) ) return;

            // Delete.
            delete_option( 'jira_api_users' );

            // Get Jira Helper.
            $help = new \BuiltMightyKit\Plugins\builtJiraHelper();
    
            // Set range.
            $range = range( 0, 20 );
    
            // Set users.
            $users = [];
    
            // Loop. 
            foreach( $range as $count ) {
    
                // Set offset.
                $offset = $count * 50;
    
                // Get current.
                $current = $this->get_users( $offset );
    
                // If current is empty, break.
                if( empty( $current ) ) break;
   
                // Sort users.
                $current = $help->sort_users( $current );

                // Get and store projects.
                $users = array_merge( $users, $current );
    
            }
    
            // Save.
            update_option( 'jira_api_users', $users );

            // Return.
            return $users;

    }

    /**
     * Get projects.
     * 
     * @since   1.0.0
     */
    public function get_projects( $offset = 0 ) {

        // Check for user email and API token.
        if( ! $this->user_email || ! $this->api_token ) return false;

        // Check if already saved.
        if( get_option( 'jira_api_projects' ) ) return get_option( 'jira_api_projects' );

        // Set params.
        $params = [
            'maxResults'    => '50',
            'orderBy'       => 'name',
            'startAt'       => $offset,
            'status'        => 'live',
            'properties'    => 'key,name',
        ];

        // Set endpoint.
        $endpoint = 'project/search?' . http_build_query( $params );

        // Request.
        $response = $this->request( $endpoint, $this->get_args( [], 'GET' ) );

        // Return.
        return $response;

    }

    /**
     * Get users.
     * 
     * @since   1.0.0
     */
    public function get_users( $offset = 0 ) {

        // Check for user email and API token.
        if( ! $this->user_email || ! $this->api_token ) return false;

        // Check if already saved.
        if( get_option( 'jira_api_users' ) ) return get_option( 'jira_api_users' );

        // Set params.
        $params = [
            'maxResults'    => '50',
            'startAt'       => $offset,
        ];

        // Set endpoint.
        $endpoint = 'users/search?' . http_build_query( $params );

        // Request.
        $response = $this->request( $endpoint, $this->get_args( [], 'GET' ) );

        // Return.
        return $response;

    }

    /**
     * Get user.
     * 
     * @since   1.0.0
     */
    public function get_user( $id ) {

        // Check for user email and API token.
        if( ! $this->user_email || ! $this->api_token ) return false;

        // Set endpoint.
        $endpoint = 'user?accountId=' . $id;

        // Request.
        $response = $this->request( $endpoint, $this->get_args( [], 'GET' ) );

        // Return.
        return $response;

    }

    /**
     * Get issues.
     * 
     * @since   1.0.0
     */
    public function get_issues() {

        // Check for user email and API token.
        if( ! $this->user_email || ! $this->api_token ) return false;

        // Check if Jira project is set.
        if( ! get_option( 'jira-project' ) ) return false;

        // Set params with project key.
        $params = [
            'jql'           => 'project = ' . get_option( 'jira-project' ),
            'maxResults'    => '5',
            'startAt'       => '0',
        ];

        // Request.
        $response = $this->request( 'search?' . http_build_query( $params ), $this->get_args( [], 'GET' ) );

        // Return.
        return $response;

    }

    /**
     * Create issue. 
     * 
     * @since   1.0.0
     */
    public function create_issue( $data ) {

        // Check for user email and API token.
        if( ! $this->user_email || ! $this->api_token ) return false;

        // Sanitize.
        $data = $this->sanitize( $data );

        // Get PM account ID.
        $pm = explode( '|', base64_decode( $data['pm'] ) );

        // Add some additional lines to the description.
        $data['desc'] .= "\n";

        // If there's a user.
        if( ! empty( $data['user'] ) ) {

            // Add user to description.
            $data['desc'] .= "\n — Submitted by: " . $data['user'];

        }

        // If there's a URL, add it to the description.
        if( ! empty( $data['url'] ) ) {

            // Append the URL to the description.
            $data['desc'] .= "\n — Relevant URL: " . $data['url'];

        }

        // Append the site URL to the description.
        $data['desc'] .= "\n — Submitted on: " . site_url( '/' );

        // Set body.
        $body = [
            'fields' => [
                'project'   => [
                    'key'   => $data['project'],
                ],
                'summary'   => stripslashes( $data['title'] ),
                'description'   => [
                    'type'  => 'doc',
                    'version'   => 1,
                    'content'   => [
                        [
                            'type'  => 'paragraph',
                            'content'   => [
                                [
                                    'type'  => 'text',
                                    'text'  => stripslashes( $data['desc'] ),
                                ]
                            ]
                        ]
                    ]
                ],
                'issuetype' => [
                    'name'  => 'Task',
                ],
                'assignee'  => [
                    'accountId' => $pm[0],
                ],
            ]
        ];

        // Request.
        $response = $this->request( 'issue', $this->get_args( $body ) );

        // Check if there's an attachment.
        if( ! empty( $response['key'] ) && ! empty( $data['screenshot'] ) ) {

            // Create attachment.
            $this->create_attachment( $response['key'], $data['screenshot'] );

        }

        // Return.
        return true;

    }

    /**
     * Create attachment.
     * 
     * @since   1.3.0
     */
    public function create_attachment( $key, $screenshot ) {

        // Check for user email and API token.
        if( ! $this->user_email || ! $this->api_token ) return false;

        // Decode the image.
        $image = str_replace( 'data:image/png;base64,', '', $screenshot );
        $image = str_replace( ' ', '+', $image );
        $image = base64_decode( $image );

        // Create a tmp directory, within the uploads dir.
        $upload_dir = wp_upload_dir();
        $tmp_dir = $upload_dir['basedir'] . '/built_tmp';

        // Check if the tmp dir exists.
        if( ! file_exists( $tmp_dir ) ) {

            // Create the tmp dir, with 755 permissions.
            mkdir( $tmp_dir, 0755 );

        }

        // Set filename.
        $filename = 'screenshot_' . date( 'Y-m-d-H-i-s' ) . '.png';

        // Save the decoded image to the tmp dir.
        file_put_contents( $tmp_dir . '/' . $filename, $image );

        // Set the API URL.
        $api_url = $this->api_url . 'issue/' . $key . '/attachments';        

        // Set headers.
        $headers = [
            'Authorization: ' . $this->get_auth(),
            'X-Atlassian-Token: no-check' 
        ];

        // Create a cURL file.
        $cfile = new CURLFile( $tmp_dir . '/' . $filename, 'image/png', $filename );

        // Set up POST fields.
        $post_fields = [ 'file' => $cfile ];

        // Start cURL.
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $api_url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_fields );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

        // Execute.
        $response = curl_exec( $ch );

        // Close.
        curl_close( $ch );

        // Delete the tmp file.
        unlink( $tmp_dir . '/' . $filename );

    }

    /**
     * Get auth.
     * 
     * @since   1.0.0
     */
    public function get_auth() {

        // Return auth.
        return 'Basic ' . base64_encode( $this->user_email . ':' . $this->api_token );

    }

    /**
     * Get args.
     * 
     * @since   1.0.0
     */
    public function get_args( $body = [], $method = 'POST' ) {

        // Set args.
        $args = [
            'method'    => $method,
            'headers'   => [
                'Authorization'     => $this->get_auth(),
                'Accept'            => '*/*',
                'Accept-Encoding'   => 'gzip, deflate, br',
                'Connection'        => 'keep-alive',
            ]
        ];

        // Check for body.
        if( ! empty( $body ) ) {

            // JSON encode.
            $args['body'] = json_encode( $body );

            // Add content length.
            $args['headers']['Content-Length'] = strlen( $args['body'] );

            // Add content type.
            $args['headers']['Content-Type'] = 'application/json';

        }

        // Return.
        return $args;

    }

    /**
     * Request.
     * 
     * @param   string  $endpoint   The endpoint of the request.
     * @param   array   $args       The args for the request.
     * 
     * @since   1.0.0
     */
    public function request( $endpoint, $args ) {

        // Request.
        $request = wp_remote_request( $this->api_url . $endpoint, $args );

        // Request.
        $response = json_decode( wp_remote_retrieve_body( $request ), true );

        // Return.
        return $response;

    }

    /**
     * Sanitize.
     * 
     * @since   1.0.0
     */
    public function sanitize( $data ) {

        // Loop through data array.
        foreach( $data as $key => $value ) {

            // Check if value is an array.
            if( is_array( $value ) ) {

                // Loop through array.
                foreach( $value as $k => $v ) {

                    // Sanitize.
                    $data[$key][$k] = sanitize_text_field( $v );

                }

            } else {

                // Sanitize.
                $data[$key] = sanitize_text_field( $value );

            }

        }

        // Return.
        return $data;
        
    }

}