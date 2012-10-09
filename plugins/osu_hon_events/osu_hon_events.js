// Javascript

// Hide date style if not Event

Drupal.behaviors.osu_hon_events = function(context) {

  // Check feed type when form first loads
  var feed_type  = $('#edit-feed-type').val();
  if (feed_type == 'osu_events') {
    $('#edit-date-style-wrapper').show();
  }
  else {
    $('#edit-date-style-wrapper').hide();
	}

  // And provide a callback for when it changes
  $('#edit-feed-type').change(function() {
   	var feed_type  = $('#edit-feed-type').val();
    if (feed_type == 'osu_events') {
      $('#edit-date-style-wrapper').show();
    }
    else {
      $('#edit-date-style-wrapper').hide();
		}
  });
}
