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

});