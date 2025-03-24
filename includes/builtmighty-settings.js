// builtmighty-settings.js
jQuery(document).ready(function($) {

    // Create image upload field
    $('.builtmighty-upload-image-field').each(function() {
        var $this = $(this);
        var imageId = $this.attr('id');
        var $button = $('#' + imageId + '-button');
        var $preview = $('#' + imageId + '-preview');
        $button.on('click', function(e) {
            e.preventDefault();
            var image = wp.media({ 
                title: 'Upload Image',
                multiple: false
            }).open().on('select', function(e) {
                var uploaded_image = image.state().get('selection').first();
                var image_url = uploaded_image.toJSON().url;
                $this.val(image_url);
                $preview.attr('src', image_url);
            });
        });
    });

    // Create color picker field
    $('.builtmighty-color-field').wpColorPicker();

    // On tab.
    $('ul#builtmighty-tabs li.builtmighty-tab').on('click', function() {
        // Get section ID.
        var section = $(this).data('id');
        // Remove active.
        $('ul#builtmighty-tabs li.builtmighty-tab').removeClass('active');
        $('.builtmighty-tab-content').removeClass('active');
        // Add active.
        $(this).addClass('active');
        $('#' + section).addClass('active');
    });

});