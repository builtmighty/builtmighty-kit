// admin.js
jQuery(document).ready(function($) {

    // Issue/contact form switch.
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

    // Issue screenshot.
    $('div.built-issue-screenshot').on('paste', function(e) {
        // Get clipboard data.
        let items = e.originalEvent.clipboardData.items;
        // Only allow if image.
        if (items[0].type.indexOf('image') == -1) {return;}
        // Clear text from div.
        $(this).text('');
        // Loop through items.
        for (let i = 0; i < items.length; i++) {
            // Check if image.
            if (items[i].type.indexOf('image') == -1) {continue;}
            // Get image.
            let file = items[i].getAsFile();
            // Create reader.
            let reader = new FileReader();
            // Read file.
            reader.onload = function(event) {
                // Get base64 string.
                let base64 = event.target.result;
                // Set base64 string.
                $('input[name="built-issue-screenshot"]').val(base64);
            };
            // Read as data URL.
            reader.readAsDataURL(file);
        }
    });
    
    // Issue/contact form submit.
    $('.built-issue-save input[type=submit]').on('click', function(e) {
        // Prevent default.
        e.preventDefault();
        // Declare variables.
        let type = $(this).attr('name');
        let project;
        let pm;
        let title;
        let desc;
        let url;
        let screenshot;
        // Check type.
        if(type == 'built-issue-save') {
            // Create issue.
            project = $('input[name="built-issue-project"]').val();
            pm = $('input[name="built-issue-pm"]').val();
            title = $('input[name="built-issue-subject"]').val();
            desc = $('textarea[name="built-issue-description"]').val();
            url = $('input[name="built-issue-url"]').val();
            screenshot = $('input[name="built-issue-screenshot"]').val();
        } else {
            // Send contact.
            project = $('input[name="built-project-project"]').val();
            pm = $('input[name="built-project-pm"]').val();
            title = $('input[name="built-project-subject"]').val();
            desc = $('textarea[name="built-project-message"]').val();
            url = '';
            screenshot = '';
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
                desc: desc,
                url: url,
                screenshot: screenshot
            },
            success: function(data) {
                // JSON parse.
                data = JSON.parse(data);
                // Check data.
                if(data.status == 'success') {
                    // Clear form.
                    $('.built-dash-forms input[type=text], .built-dash-forms input[type=url]').val('');
                    $('.built-dash-forms textarea').val('');
                    $('.built-issue-screenshot').text('Paste Screenshot Here');
                    $('.built-dash-forms input[name="built-issue-screenshot"]').val('');
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