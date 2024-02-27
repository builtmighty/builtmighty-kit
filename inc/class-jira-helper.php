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

            // Check if suspended.
            if( strpos( $user['displayName'], 'suspend_' ) !== false ) continue;

            // Set account ID and display name.
            $user_value = base64_encode( $user['accountId'] . '|' . $user['displayName'] );

            // Add to users.
            $users[$user_value] = $user['displayName'];

        }

        // Return.
        return $users;
        
    }

    /**
     * Organize issues.
     * 
     * @since   1.0.0
     */
    public function sort_issues( $response ) {

        // Set.
        $issues = [];

        // Loop through response.
        foreach( $response['issues'] as $issue ) {

            // Get status class.
            $class = $this->issue_class( $issue['fields']['status']['name'] );

            // Shorten status.
            $issue['fields']['status']['name'] = str_replace( 'In Development', 'Dev', $issue['fields']['status']['name'] );

            // Set.
            $issues[$issue['key']] = [
                'summary'   => $issue['fields']['summary'],
                'status'    => $issue['fields']['status'],
                'assignee'  => $issue['fields']['assignee'],
                'class'     => $class,
            ];

        }

        // Return.
        return $issues;

    }

    /**
     * Set issue class.
     * 
     * @since   1.0.0
     */
    public function issue_class( $status ) {

        // Set statuses.
        if( $status === 'Ready' ) {

            // Set.
            $class = 'is-ready';

        } elseif( $status === 'Done' ) {

            // Set.
            $class = 'is-done';

        } else {

            // Set.
            $class = 'is-dev';

        }

        // Return.
        return $class;

    }
 
}