/* Gallery image picker: Select / Replace / Remove with preview. */
(function ($) {
  'use strict';
  $(function () {
    var box = $('.pgl-image-picker');
    if (!box.length || !wp.media) return;
    var frame = null;
    box.on('click', '.pgl-select', function (e) {
      e.preventDefault();
      if (frame) { frame.open(); return; }
      frame = wp.media({
        title: 'Select gallery image',
        button: { text: 'Use this image' },
        library: { type: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'] },
        multiple: false
      });
      frame.on('select', function () {
        var att = frame.state().get('selection').first();
        if (!att) return;
        var id = att.get('id');
        var url = att.get('sizes') && att.get('sizes').medium ? att.get('sizes').medium.url : att.get('url');
        $('#pgl_attachment_id').val(id);
        box.attr('data-has', '1');
        box.find('.pgl-preview').html('<img class="pgl-preview-img" alt="">').find('img').attr('src', url);
        box.find('.pgl-filename').text(att.get('filename') || '');
        box.find('.pgl-select').text('Replace Image');
        if (!box.find('.pgl-remove').length) {
          box.find('.pgl-select').after(' <button type="button" class="button pgl-remove">Remove Image</button>');
        }
      });
      frame.open();
    });
    box.on('click', '.pgl-remove', function (e) {
      e.preventDefault();
      $('#pgl_attachment_id').val('');
      box.attr('data-has', '0');
      box.find('.pgl-preview').html('<span class="pgl-no-image">No image selected.</span>');
      box.find('.pgl-filename').text('');
      box.find('.pgl-select').text('Select Image');
      $(this).remove();
    });
  });
})(jQuery);
