// dev-admin.js
jQuery(document).ready(function($) {

    // On run.
    $('#built-process').on('click', function(e) {
        // Prevent default.
        e.preventDefault();
        // Get ID.
        let id = $(this).attr('id');
        // Confirm tool run.
        if( ! confirm('Are you sure you want to run?') ) {
            return false;
        }
        // Run tool.
        runAJAX(id);
    });

    // Run AJAX.
    function runAJAX(id) {
        // Get count.
        let count = $('#' + id).data('count');
        // Get offset.
        let offset = $('#' + id).data('offset');
        // Get total.
        let total = $('#' + id).data('total');
        // Get action.
        let action = $('#' + id).data('action');
        // AJAX.
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: action,
                nonce: built.nonce,
                count: count,
                offset: offset,
                total: total
            },
            success: function(response) {
                // Parse response.
                let data = JSON.parse(response);
                console.log(data);
                // If continue, rerun.
                if(data.continue == true) {
                    // Update count.
                    $('#' + id).data('count', data.count);
                    // Update offset.
                    $('#' + id).data('offset', data.offset);
                    // Update total.
                    $('#' + id).data('total', data.total);
                    // Run tool.
                    runAJAX(id);
                } else {
                    // Update count.
                    $('#' + id).data('count', data.count);
                    // Update offset.
                    $('#' + id).data('offset', data.offset);
                    // Update total.
                    $('#' + id).data('total', data.total);
                    // Update message.
                    $('#' + id).text('Complete');
                }
            }
        });
    }

});