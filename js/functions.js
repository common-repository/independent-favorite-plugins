var i_fv_toggle;
jQuery(document).ready(function ($) {
    var i_fv_encode_file_path = function (str) {
        var path = decodeURIComponent(str).replace('/', '252F');
        return path;
    };
    i_fv_toggle = function (el) {
        el = $(el);
        el.children().toggle();
        var path = i_fv_encode_file_path(el.attr('data-plugin')),
            action = $(el.children()[0]).is(':visible') ? 'i_fv_add_plugin' : 'i_fv_delete_plugin',
            data = {
                'action': action,
                'file': path
            };
        $.post(ajaxurl, data, function (response) {
        });
    };

    //Upload image

});