<?php
/**
 * @file
 * osu_live_feeds_view.views_default.inc
 */

/**
 * Implements hook_views_default_views().
 */
function osu_live_feeds_view_views_default_views() {
  $export = array();

  $view = new view();
  $view->name = 'osu_live_feeds';
  $view->description = 'A contextual view to display RSS feed nodes.';
  $view->tag = 'Live Feeds';
  $view->base_table = 'node';
  $view->human_name = 'OSU Live Feeds Contextual View';
  $view->core = 7;
  $view->api_version = '3.0';
  $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

  /* Display: Master */
  $handler = $view->new_display('default', 'Master', 'default');
  $handler->display->display_options['css_class'] = 'osu-live-feeds';
  $handler->display->display_options['use_more_always'] = FALSE;
  $handler->display->display_options['access']['type'] = 'perm';
  $handler->display->display_options['cache']['type'] = 'none';
  $handler->display->display_options['query']['type'] = 'views_query';
  $handler->display->display_options['exposed_form']['type'] = 'basic';
  $handler->display->display_options['pager']['type'] = 'some';
  $handler->display->display_options['pager']['options']['items_per_page'] = '1';
  $handler->display->display_options['pager']['options']['offset'] = '0';
  $handler->display->display_options['style_plugin'] = 'default';
  $handler->display->display_options['row_plugin'] = 'entity';
  $handler->display->display_options['row_options']['view_mode'] = 'contextual_view';
  /* Relationship: OG membership: OG membership from Node */
  $handler->display->display_options['relationships']['og_membership_rel']['id'] = 'og_membership_rel';
  $handler->display->display_options['relationships']['og_membership_rel']['table'] = 'node';
  $handler->display->display_options['relationships']['og_membership_rel']['field'] = 'og_membership_rel';
  /* Contextual filter: OG membership: Group ID */
  $handler->display->display_options['arguments']['gid']['id'] = 'gid';
  $handler->display->display_options['arguments']['gid']['table'] = 'og_membership';
  $handler->display->display_options['arguments']['gid']['field'] = 'gid';
  $handler->display->display_options['arguments']['gid']['relationship'] = 'og_membership_rel';
  $handler->display->display_options['arguments']['gid']['default_action'] = 'default';
  $handler->display->display_options['arguments']['gid']['default_argument_type'] = 'og_context';
  $handler->display->display_options['arguments']['gid']['summary']['number_of_records'] = '0';
  $handler->display->display_options['arguments']['gid']['summary']['format'] = 'default_summary';
  $handler->display->display_options['arguments']['gid']['summary_options']['items_per_page'] = '25';
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
  /* Filter criterion: Content: Type */
  $handler->display->display_options['filters']['type']['id'] = 'type';
  $handler->display->display_options['filters']['type']['table'] = 'node';
  $handler->display->display_options['filters']['type']['field'] = 'type';
  $handler->display->display_options['filters']['type']['value'] = array(
    'feed' => 'feed',
  );
  $handler->display->display_options['filters']['type']['group'] = 1;

  /* Display: OSU News */
  $handler = $view->new_display('block', 'OSU News', 'news');
  $handler->display->display_options['defaults']['filter_groups'] = FALSE;
  $handler->display->display_options['defaults']['filters'] = FALSE;
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
  /* Filter criterion: Content: Type */
  $handler->display->display_options['filters']['type']['id'] = 'type';
  $handler->display->display_options['filters']['type']['table'] = 'node';
  $handler->display->display_options['filters']['type']['field'] = 'type';
  $handler->display->display_options['filters']['type']['value'] = array(
    'feed' => 'feed',
  );
  $handler->display->display_options['filters']['type']['group'] = 1;
  /* Filter criterion: Feed: Feed Type */
  $handler->display->display_options['filters']['feed_type_value']['id'] = 'feed_type_value';
  $handler->display->display_options['filters']['feed_type_value']['table'] = 'field_data_feed_type';
  $handler->display->display_options['filters']['feed_type_value']['field'] = 'feed_type_value';
  $handler->display->display_options['filters']['feed_type_value']['value'] = array(
    'osu_news' => 'osu_news',
  );

  /* Display: OSU Events */
  $handler = $view->new_display('block', 'OSU Events', 'events');
  $handler->display->display_options['defaults']['filter_groups'] = FALSE;
  $handler->display->display_options['defaults']['filters'] = FALSE;
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
  /* Filter criterion: Content: Type */
  $handler->display->display_options['filters']['type']['id'] = 'type';
  $handler->display->display_options['filters']['type']['table'] = 'node';
  $handler->display->display_options['filters']['type']['field'] = 'type';
  $handler->display->display_options['filters']['type']['value'] = array(
    'feed' => 'feed',
  );
  $handler->display->display_options['filters']['type']['group'] = 1;
  /* Filter criterion: Feed: Feed Type */
  $handler->display->display_options['filters']['feed_type_value']['id'] = 'feed_type_value';
  $handler->display->display_options['filters']['feed_type_value']['table'] = 'field_data_feed_type';
  $handler->display->display_options['filters']['feed_type_value']['field'] = 'feed_type_value';
  $handler->display->display_options['filters']['feed_type_value']['value'] = array(
    'osu_events' => 'osu_events',
  );

  /* Display: OSU Announcements */
  $handler = $view->new_display('block', 'OSU Announcements', 'announcements');
  $handler->display->display_options['defaults']['filter_groups'] = FALSE;
  $handler->display->display_options['defaults']['filters'] = FALSE;
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
  /* Filter criterion: Content: Type */
  $handler->display->display_options['filters']['type']['id'] = 'type';
  $handler->display->display_options['filters']['type']['table'] = 'node';
  $handler->display->display_options['filters']['type']['field'] = 'type';
  $handler->display->display_options['filters']['type']['value'] = array(
    'feed' => 'feed',
  );
  $handler->display->display_options['filters']['type']['group'] = 1;
  /* Filter criterion: Feed: Feed Type */
  $handler->display->display_options['filters']['feed_type_value']['id'] = 'feed_type_value';
  $handler->display->display_options['filters']['feed_type_value']['table'] = 'field_data_feed_type';
  $handler->display->display_options['filters']['feed_type_value']['field'] = 'feed_type_value';
  $handler->display->display_options['filters']['feed_type_value']['value'] = array(
    'osu_announcements' => 'osu_announcements',
  );

  /* Display: OSU Wordpress */
  $handler = $view->new_display('block', 'OSU Wordpress', 'wordpress');
  $handler->display->display_options['defaults']['filter_groups'] = FALSE;
  $handler->display->display_options['defaults']['filters'] = FALSE;
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
  /* Filter criterion: Content: Type */
  $handler->display->display_options['filters']['type']['id'] = 'type';
  $handler->display->display_options['filters']['type']['table'] = 'node';
  $handler->display->display_options['filters']['type']['field'] = 'type';
  $handler->display->display_options['filters']['type']['value'] = array(
    'feed' => 'feed',
  );
  $handler->display->display_options['filters']['type']['group'] = 1;
  /* Filter criterion: Feed: Feed Type */
  $handler->display->display_options['filters']['feed_type_value']['id'] = 'feed_type_value';
  $handler->display->display_options['filters']['feed_type_value']['table'] = 'field_data_feed_type';
  $handler->display->display_options['filters']['feed_type_value']['field'] = 'feed_type_value';
  $handler->display->display_options['filters']['feed_type_value']['value'] = array(
    'osu_wordpress' => 'osu_wordpress',
  );

  /* Display: OSU Google Calendar */
  $handler = $view->new_display('block', 'OSU Google Calendar', 'gcal');
  $handler->display->display_options['defaults']['filter_groups'] = FALSE;
  $handler->display->display_options['defaults']['filters'] = FALSE;
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
  /* Filter criterion: Content: Type */
  $handler->display->display_options['filters']['type']['id'] = 'type';
  $handler->display->display_options['filters']['type']['table'] = 'node';
  $handler->display->display_options['filters']['type']['field'] = 'type';
  $handler->display->display_options['filters']['type']['value'] = array(
    'feed' => 'feed',
  );
  $handler->display->display_options['filters']['type']['group'] = 1;
  /* Filter criterion: Feed: Feed Type */
  $handler->display->display_options['filters']['feed_type_value']['id'] = 'feed_type_value';
  $handler->display->display_options['filters']['feed_type_value']['table'] = 'field_data_feed_type';
  $handler->display->display_options['filters']['feed_type_value']['field'] = 'feed_type_value';
  $handler->display->display_options['filters']['feed_type_value']['value'] = array(
    'gcal_feed' => 'gcal_feed',
  );
  $export['osu_live_feeds'] = $view;

  return $export;
}

