<?php
/**
 * Jira Issues.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */

// Jira.
$jira = new \BuiltMightyKit\Plugins\builtJira();
$help = new \BuiltMightyKit\Plugins\builtJiraHelper();

// Get issues.
$issues = $help->sort_issues( $jira->get_issues() );

// Check for issues.
if( ! $issues ) return;

// Set output of issues. ?>
<div class="built-panel">
    <p style="margin-top:0;"><strong>✅ Issues</strong></p>
    <div class="built-jira-issues">
        <ul style="margin:0;"><?php

            // Loop through issues.
            foreach( $issues as $issue_key => $issue ) {

                // Output. ?>
                <li class="built-jira-issue">
                    <a href="https://builtmighty.atlassian.net/browse/<?php echo $issue_key; ?>" class="jira-issue-summary" target="_blank"><?php echo $issue['summary']; ?></a>
                    <span class="jira-issue-status <?php echo $issue['class']; ?>"><?php echo strtoupper( $issue['status']['name'] ); ?></span>
                    <span class="jira-issue-assignee"><img src="<?php echo $issue['assignee']['avatarUrls']['24x24']; ?>" /> <?php echo $issue['assignee']['displayName']; ?></span>
                </li><?php

            } ?>

        </ul>
        <p style="margin:0;">
            <a href="https://builtmighty.atlassian.net/projects/<?php echo get_option( 'jira-project' ); ?>" target="_blank" class="built-button" style="margin-top:10px;">View Project</a>
        </p>
    </div>
</div>