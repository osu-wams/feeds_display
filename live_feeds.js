// Javascript

// Prefill the Title and URL
// with the defaults for this feed type
(function ($) {
  Drupal.behaviors.live_feeds = {
    attach: function(context, settings) {
        $('#edit-feed-type').change(function() {
          var feed_type  = $('#edit-feed-type').val();
          if (feed_type == 'select') {
            $('#edit-title').val('');
            $('#edit-feed-url').val('');
          }
          else {
            var feed_title = Drupal.settings.live_feeds[feed_type].name;
            var feed_url   = Drupal.settings.live_feeds[feed_type].url;

            $('#edit-title').val(feed_title);
            $('#edit-feed-url').val(feed_url);
          }
      });
   }
};
})(jQuery);
