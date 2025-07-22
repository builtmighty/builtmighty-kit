/* admin.js */
jQuery(document).ready(function($) {

    // Issue screenshot.
    $('div#slack_screenshot').on('paste', function(e) {
        // Get clipboard data.
        let items = e.originalEvent.clipboardData.items;
        // Only allow if image.
        if (items[0].type.indexOf('image') == -1) { return; }
        // Clear text from div.
        $(this).text('');
        // Loop through items.
        for (let i = 0; i < items.length; i++) {
            // Check if image.
            if (items[i].type.indexOf('image') == -1) { continue; }
            // Get image.
            let file = items[i].getAsFile();
            // Create reader.
            let reader = new FileReader();
            // Read file.
            reader.onload = function(event) {
                // Get base64 string.
                let base64 = event.target.result;
                // Add background.
                $('div#slack_screenshot').css('background', 'url(' + base64 + ')');
                // Add class.
                $('div#slack_screenshot').addClass('has-image');
                // Remove the data URL prefix to get only the base64 string.
                base64 = base64.replace(/^data:image\/(png|jpg|jpeg);base64,/, '');
                // Set base64 string.
                $('input[name="slack_screenshot"]').val(base64);
            };
            // Read as data URL.
            reader.readAsDataURL(file);
        }
    });

    function builtmightyToggleDarkMode() {
        // Toggle dark mode class.
        var $body = $('body'),
            $select = $('[name="builtmighty_admin_color_mode"]');

        // On change
        $select.on('change', function() {
            var val = $(this).val();
            if (val === 'dark') {
                $body.addClass('builtmighty-admin-dark-mode').removeClass('builtmighty-admin-light-mode');
                localStorage.setItem('builtmighty_admin_color_mode', 'dark');
            } else if (val === 'light') {
                $body.addClass('builtmighty-admin-light-mode').removeClass('builtmighty-admin-dark-mode');
                localStorage.setItem('builtmighty_admin_color_mode', 'light');
            } else {
                $body.removeClass('builtmighty-admin-dark-mode').removeClass('builtmighty-admin-light-mode');
                localStorage.removeItem('builtmighty_admin_color_mode');
                // Let CSS handle system mode
            }
        });

        // On page load, apply mode if not system
        var saved = $select.val() || localStorage.getItem('builtmighty_admin_color_mode');
        if (saved === 'dark') {
            $body.addClass('builtmighty-admin-dark-mode').removeClass('builtmighty-admin-light-mode');
        } else if (saved === 'light') {
            $body.removeClass('builtmighty-admin-dark-mode').addClass('builtmighty-admin-light-mode');
        } else {
            $body.removeClass('builtmighty-admin-dark-mode').removeClass('builtmighty-admin-light-mode');
            // Let CSS handle system mode
        }
    }

    builtmightyToggleDarkMode();

});