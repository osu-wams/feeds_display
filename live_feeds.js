// Javascript

// Prefill the Title and URL
// with the defaults for this feed type
(function ($) {
  Drupal.behaviors.live_feeds = {
    attach: function(context, settings) {
        $('#edit-feed-type-und').change(function() {
          var feed_type  = $('#edit-feed-type-und').val();
          if (feed_type == 'select') {
            $('#edit-feed-title-und-0-value').val('');
            $('#edit-url-und-0-value').val('');
          }
          else {
            var feed_title = Drupal.settings.live_feeds[feed_type].name;
            var feed_url   = Drupal.settings.live_feeds[feed_type].url;
            $('#edit-feed-title-und-0-value').val(feed_title);
            $('#edit-url-und-0-value').val(feed_url);
          }
      });
   }
};
})(jQuery);
