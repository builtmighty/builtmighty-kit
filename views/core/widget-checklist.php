<?php
/**
 * Checklist.
 * 
 * @package Built Mighty Kit
 * @since   2.0.0
 */
// Output. ?>
<div class="built-panel">
    <p style="margin-top:0;"><strong>✔️ Checklist</strong></p>
    <ul style="margin:0;"><?php

        // Check if complete.
        if( $list['complete'] ) {

            // Output all done. ?>
            <li>All done! Check list is complete.</li><?php

        } else {

            // Loop throgh todo.
            foreach( $list['todo'] as $task_id => $task ) {

                // If task is complete, set class.
                $class = ( isset( $_POST['built-task-' . $task_id] ) || $task['status'] ) ? ' built-task-complete' : '';

                // Output. ?>
                <li class="built-tasklist">
                    <form method="POST">
                        <div class="built-task-list-actions<?php echo $class; ?>">
                            <button type="submit" class="built-task-button" name="built-task-<?php echo $task_id; ?>" value="1">✔</button>
                            <input type="hidden" id="built-task-<?php echo $task_id; ?>" name="built-task-<?php echo $task_id; ?>" value="1" <?php checked( $task['status'], true ); ?>>
                            <label for="built-task-<?php echo $task_id; ?>"><?php echo $task['title']; ?></label>
                        </div>
                        <p><?php echo $task['desc']; ?></p>
                    </form>
                </li><?php

            }

        } ?>
    </ul>
</div>