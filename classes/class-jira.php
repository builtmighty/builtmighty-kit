<?php
/**
 * Jira.
 * 
 * Adds connectivity to Jira.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
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
        $this->user_email = 'tyler@builtmighty.com';
        $this->api_token = 'ATATT3xFfGF02Ym4QAjg6bypOHn6O1AKteWZPqnb2GxRL5ABE_dqlVEthXgi1uWmrk880nQlpbRH8uitA27Y47QE_KwKRX-Qq2AuSTM4sYVRRRv7xKx6vnUn9KZMZLUtXAq43Qf0-eWEmnNhggnYF8SR_3TDN7HDitXakB3llXvTpRVGeHaMrUQ=7DB6D76D';
        $this->api_url = 'https://builtmighty.atlassian.net/rest/api/3/';

    }

    /**
     * Get projects.
     * 
     * @since   1.0.0
     */
    public function get_projects() {

        // Set params.
        $params = [
            'maxResults'    => '50',
            'orderBy'       => 'lastIssueUpdatedTime',
            'startAt'       => '0',
            'status'        => 'live',
        ];

        // Set endpoint.
        $endpoint = 'project/search?' . http_build_query( $params );

        // Request.
        $response = $this->request( $endpoint, $this->get_args( [], 'GET' ) );

        // Return.
        return $this->sort_projects( $response );

    }

    /**
     * Get users.
     * 
     * @since   1.0.0
     */
    public function get_users() {

        // Set params.
        $params = [
            'maxResults'    => '50',
            'startAt'       => '0',
        ];

        // Set endpoint.
        $endpoint = 'users/search?' . http_build_query( $params );

        // Request.
        $response = $this->request( $endpoint, $this->get_args( [], 'GET' ) );

        // Return.
        return $this->sort_users( $response );

    }

    /**
     * Organize projects.
     * 
     * @since   1.0.0
     */
    public function sort_projects( $response ) {

        // Set.
        $projects = [];

        // Loop through response.
        foreach( $response['values'] as $project ) {

            // Add to projects.
            $projects[$project['key']] = $project['name'];

        }

        // Return.
        return $projects;
        
    }

    /**
     * Organize users.
     * 
     * @since   1.0.0
     */
    public function sort_users( $response ) {

        // Set.
        $users = [];

        // Loop through response.
        foreach( $response as $user ) {

            // Set account ID and display name.
            $user_value = base64_encode( $user['accountId'] . ':' . $user['displayName'] );

            // Add to users.
            $users[$user_value] = $user['displayName'];

        }

        // Return.
        return $users;
        
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
        $response = json_decode( wp_remote_retrieve_body( wp_remote_request( $this->api_url . $endpoint, $args ) ), true );

        // Return.
        return $response;

    }

}