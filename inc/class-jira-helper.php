<?php
/**
 * Jira.
 * 
 * A helper class for formatting Jira responses.
 * 
 * @package Built Mighty Kit
 * @since   1.0.0
 */
class builtJiraHelper {

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
            $user_value = base64_encode( $user['accountId'] . '|' . $user['displayName'] );

            // Add to users.
            $users[$user_value] = $user['displayName'];

        }

        // Return.
        return $users;
        
    }

}