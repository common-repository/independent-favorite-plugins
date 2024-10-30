jQuery(function ($) {
    $('.upload-image-button').click(function () {
        var button = $(this), file_frame;
        if (file_frame) {
            file_frame.open();
            return;
        }
        // Create the media frame.
        file_frame = wp.media.frames.file_frame = wp.media({
            library: {type: 'image'},
            multiple: false  // Set to true to allow multiple files to be selected
        });
        // When an image is selected, run a callback.
        file_frame.on('select', function () {
            // We set multiple to false so only get one image from the uploader
            attachment = file_frame.state().get('selection').first().toJSON();
            $($(button.parents('.media-uploader')[0]).find('.input-upload-image')[0]).val(attachment.url).trigger("change");
            $($(button.parents('.media-uploader')[0]).find('img')[0]).attr('src', attachment.url);
            if ($($('.input-upload-image')[0]).val == $($('.input-upload-image')[1]).val)
                $('#height_icon').val(Math.floor(attachment.height / 2));
            else
                $('#height_icon').val(attachment.height);
            $('#width_icon').val(attachment.width);
        });

        // Finally, open the modal
        file_frame.open();
        return false;
    });
})
;