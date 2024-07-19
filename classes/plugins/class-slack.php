<?php
/**
 * Slack.
 * 
 * Adds connectivity to Slack.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
namespace BuiltMightyKit\Plugins;
class builtSlack {

    /**
     * Construct.
     * 
     * @since   1.0.0
     */
    public function __construct() {

        // Routes.
        add_action( 'rest_api_init', [ $this, 'routes' ] );

    }

    /**
     * Set routes.
     * 
     * @since   1.0.0
     */
    public function routes() {

        // Register.
        register_rest_route( 'builtmighty-kit/v1', '/slack', [
            'methods'   => 'POST',
            'callback'  => [ $this, 'authorize' ],
            'permission_callback' => '__return_true'
        ] );

    }

    /**
     * Get channels.
     * 
     * @since   1.0.0
     */
    public function get_channels() {

        // Check.
        if( ! $this->get_args() ) return;

        // Get args.
        $args = $this->get_args();

        // Set body.
        $args['body'] = [
            'exclude_archived'  => true,
            'limit'             => 1000
        ];

        // Get channels.
        $response = wp_remote_get( $this->get_api( 'conversations.list' ), $args );

        // Check for error.
        if( is_wp_error( $response ) ) return false;

        // Get body.
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Check body.
        if( ! $body['ok'] ) return false;

        // Return.
        return $this->format_channels( $body['channels'] );

    }

    /**
     * Post message.
     * 
     * @since   1.0.0
     */
    public function message( $message ) {

        // Check.
        if( ! $this->get_args() ) return;

        // Get args.
        $args = $this->get_args();
        
        // Get channel.
        if( empty( get_option( 'slack-channel' ) ) ) return;

        // Set channel.
        $channel = get_option( 'slack-channel' );

        // Check.
        $this->check_channel( $channel );

        // Set body.
        $args['body'] = [
            'channel'   => get_option( 'slack-channel' ),
            'text'      => $message
        ];

        // Post message.
        $response = wp_remote_post( $this->get_api( 'chat.postMessage' ), $args );

        // Check for error.
        if( is_wp_error( $response ) ) return false;

        // Get body.
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Check body.
        if( ! $body['ok'] ) return false;

        // Return.
        return true;

    }

    /** 
     * Image.
     * 
     * @since   1.0.0
     */
    public function image( $image ) {

        // Check.
        if( ! $this->get_args() ) return;

        // Get channel.
        if( empty( get_option( 'slack-channel' ) ) ) return;

        // Check channel.
        $this->check_channel( get_option( 'slack-channel' ) );

        // Decode the image.
        $binary = base64_decode( $image );

        // Get bytes of image.
        $length = strlen( $binary );

        // Set filename.
        $filename = 'screenshot.png';

        // Get upload data.
        $upload_data = $this->get_upload( $filename, $length );

        // Check upload.
        if( ! $upload_data ) return;

        // Create image.
        $image = $this->create_image( $filename, $binary );

        // Upload.
        $upload = $this->image_upload( $upload_data, $image );

        // Check if uploaded.
        if( ! $upload ) return;

        // Finish upload.
        $this->finish_upload( $upload_data['file_id'] );

    }

    /**
     * Get upload.
     * 
     * @since   1.0.0
     * 
     * @param   string  $filename
     * @param   int     $length
     */
    public function get_upload( $filename, $length ) {

        // Check.
        if( ! $this->get_args() ) return;

        // Get args.
        $args = $this->get_args();

        // Update header.
        $args['headers'] = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        // Get token.
        $token = get_option( 'built_slack_token' );

        // Body.
        $args['body'] = http_build_query( [
            'token'     => $token,
            'filename'  => $filename,
            'length'    => $length
        ] );  
        
        // Post.
        $response = wp_remote_post( $this->get_api( 'files.getUploadURLExternal' ), $args );

        // Check for error.
        if( is_wp_error( $response ) ) return false;

        // Get body.
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Check body.
        if( ! $body['ok'] ) return false;

        // Return.
        return $body;

    }

    /**
     * Create image.
     * 
     * @since   1.0.0
     */
    public function create_image( $filename, $binary ) {

        // Set temp file.
        $temp = wp_tempnam( $filename );

        // Save.
        file_put_contents( $temp, $binary );

        // Return.
        return $temp;

    }

    /**
     * Image upload.
     * 
     * @since   1.0.0
     */
    public function image_upload( $upload, $image ) {

        // Check.
        if( ! $this->get_args() ) return;

        // Get args.
        $args = $this->get_args();

        // Update header.
        $args['headers'] = [
            'Content-Type' => 'image/png',
        ];

        // Get image.
        $file = fopen( $image, 'r' );
        $file_contents = fread( $file, filesize( $image ) );
        fclose( $file );

        // Set body.
        $args['body'] = $file_contents;

        // Remove image.
        unlink( $image );

        // Post.
        $response = wp_remote_post( $upload['upload_url'], $args );

        // Check for error.
        if( is_wp_error( $response ) ) return false;

        // Return.
        return true;

    }

    /**
     * Finish upload.
     * 
     * @since   1.0.0
     */
    public function finish_upload( $file_id ) {

        // Check.
        if( ! $this->get_args() ) return;

        // Get args.
        $args = $this->get_args();

        // Update content-type.
        $args['headers'] = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . get_option( 'built_slack_token' ),
        ];

        // Body.
        $args['body'] = json_encode( [
            'files'     => [
                [
                    'id'    => $file_id,
                    'title' => 'Screenshot',
                ],
            ],
            'channel_id'    => get_option( 'slack-channel' ),
        ] );

        error_log( '[' . __FUNCTION__ . '] Args: ' . print_r( $args, true ) );
        
        // Post.
        $response = wp_remote_post( $this->get_api( 'files.completeUploadExternal' ), $args );

        error_log( '[' . __FUNCTION__ . '] Response: ' . print_r( $response, true ) );

        // Check for error.
        if( is_wp_error( $response ) ) return false;

        // Get body.
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Check body.
        if( ! $body['ok'] ) return false;

        // Return.
        return true;

    }

    /**
     * Check channel.
     * 
     * @since   1.0.0
     */
    public function check_channel( $channel ) {

        // Check.
        if( empty( get_option( 'joined-slack-channel' ) ) || get_option( 'joined-slack-channel' ) !== $channel ) {

            // Join channel.
            $this->add_channel( $channel );

        }

    }

    /**
     * Add to channel.
     * 
     * @since   1.0.0
     */
    public function add_channel( $channel ) {

        // Check.
        if( ! $this->get_args() ) return;

        // Get args.
        $args = $this->get_args();

        // Set channel.
        $args['body'] = [
            'channel'   => $channel
        ];

        // Join channel.
        $response = wp_remote_post( $this->get_api( 'conversations.join' ), $args );

        // Check for error.
        if( is_wp_error( $response ) ) return false;

        // Get body.
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
        // Check body.
        if( ! $body['ok'] ) return false;

        // Set option.
        update_option( 'joined-slack-channel', $channel );

        // Return.
        return true;

    }

    /**
     * Authorize.
     * 
     * @since   1.0.0
     */
    public function authorize( $request ) {

        // Get param key.
        $key = $request->get_param( 'key' );

        // Check.
        if( ! $this->authorize_key( $key ) ) return;

        // Get token.
        $token = $request->get_param( 'token' );

        // Save token.
        update_option( 'built_slack_token', $token );

    }

    /**
     * Authorize key.
     * 
     * @since   1.0.0
     */
    public function authorize_key( $key ) {

        // Set keys.
        $saved_key  = get_option( 'built_api_key' );
        $passed_key = base64_decode( $key );

        // Delete.
        delete_option( 'built_api_key' );

        // Return.
        return ( $passed_key === $saved_key ) ? true : false;

    }

    /**
     * Get args.
     * 
     * @since   1.0.0
     */
    public function get_args() {

        // Get token.
        $token = get_option( 'built_slack_token' );

        // Check for token.
        if( ! $token ) return;

        // Return.
        return [ 
            'headers' => [
                'Authorization'     => 'Bearer ' . $token,
                'Content-Type'      => 'application/x-www-form-urlencoded',
            ] 
        ];

    }

    /**
     * Get API.
     * 
     * @since   1.0.0
     */
    public function get_api( $endpoint ) {

        // API URL.
        return 'https://slack.com/api/' . $endpoint;

    }

    /**
     * Format channels.
     * 
     * @since   1.0.0
     */
    public function format_channels( $channels ) {

        // Set data.
        $data = [];

        // Loop through channels.
        foreach( $channels as $channel ) {

            // Set name.
            $name = str_replace( '-', ' ', $channel['name'] );
            $name = str_replace( '_', ' ', $name );
            
            // Save.
            $data[$channel['id']] = ucwords( $name );

        }

        // Order by name.
        asort( $data );

        // Return.
        return $data;

    }

}