// admin.js
jQuery(document).ready(function($) {

    // Issue/contact form.
    $('.built-dash-nav span').on('click', function() {
        // Check if active.
        if ($(this).hasClass('active')) {return;}
        // Get ID.
        var formID = $(this).data('id');
        // Remove active.
        $('.built-dash-nav span.active').removeClass('active');
        $('.built-dash-forms .built-form.active').removeClass('active');
        // Add active.
        $(this).addClass('active');
        $('#' + formID).addClass('active');
    });

    // Issue/contact form.
    $('.built-issue-save input[type=submit]').on('click', function(e) {
        // Prevent default.
        e.preventDefault();
        // Declare variables.
        let type = $(this).attr('name');
        let project;
        let pm;
        let title;
        let desc;
        // Check type.
        if(type == 'built-issue-save') {
            // Create issue.
            project = $('input[name="built-issue-project"]').val();
            pm = $('input[name="built-issue-pm"]').val();
            title = $('input[name="built-issue-subject"]').val();
            desc = $('textarea[name="built-issue-description"]').val();
        } else {
            // Send contact.
            project = $('input[name="built-project-project"]').val();
            pm = $('input[name="built-project-pm"]').val();
            title = $('input[name="built-project-subject"]').val();
            desc = $('textarea[name="built-project-message"]').val();
        }
        // AJAX.
        $.ajax({
            url: built.ajax,
            type: 'POST',
            data: {
                action: 'built_process_form',
                type: type,
                project: project,
                pm: pm,
                title: title,
                desc: desc
            },
            success: function(data) {
                // JSON parse.
                data = JSON.parse(data);
                // Check data.
                if(data.status == 'success') {
                    // Clear form.
                    $('.built-dash-forms input[type=text]').val('');
                    $('.built-dash-forms textarea').val('');
                    // Add message.
                    $('.built-form-status p').text(data.message);
                    // Add class.
                    $('.built-form-status').addClass('success');
                    // Show success.
                    $('.built-form-status').show();
                    setTimeout(function() {
                        $('.built-form-status').fadeOut();
                        // Remove class.
                        $('.built-form-status').removeClass('success');
                    }, 3000);
                } else {
                    // Add message.
                    $('.built-form-status p').text(data.message);
                    // Add class.
                    $('.built-form-status').addClass('error');
                    // Show error.
                    $('.built-form-status').show();
                    setTimeout(function() {
                        $('.built-form-status').fadeOut();
                        // Remove class.
                        $('.built-form-status').removeClass('error');
                    }, 3000);
                }
            },
            error: function(data) {
                // Show error.
                $('.built-form-status').fadeIn();
                setTimeout(function() {
                    $('.built-form-status').fadeOut();
                }, 3000);
            }
        });
    });

});