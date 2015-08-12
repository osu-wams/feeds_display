<?php
/**
 * @file
 * osu_live_feeds_view.context.inc
 */

/**
 * Implements hook_context_default_contexts().
 */
function osu_live_feeds_view_context_default_contexts() {
  $export = array();

  $context = new stdClass();
  $context->disabled = FALSE; /* Edit this to true to make a default context disabled initially */
  $context->api_version = 3;
  $context->name = 'live-feeds-contextual-blocks';
  $context->description = 'A context that displays RSS Feeds for each child unit';
  $context->tag = 'live feeds contextual blocks';
  $context->conditions = array(
    'node' => array(
      'values' => array(
        'book' => 'book',
      ),
      'options' => array(
        'node_form' => '1',
      ),
    ),
    'path' => array(
      'values' => array(
        '~main' => '~main',
        '~main/*' => '~main/*',
        '~group/*' => '~group/*',
      ),
    ),
  );
  $context->reactions = array(
    'block' => array(
      'blocks' => array(
        'views-osu_live_feeds-news' => array(
          'module' => 'views',
          'delta' => 'osu_live_feeds-news',
          'region' => 'main_first',
          'weight' => '-10',
        ),
        'views-osu_live_feeds-announcements' => array(
          'module' => 'views',
          'delta' => 'osu_live_feeds-announcements',
          'region' => 'main_first',
          'weight' => '-9',
        ),
        'views-osu_live_feeds-events' => array(
          'module' => 'views',
          'delta' => 'osu_live_feeds-events',
          'region' => 'main_second',
          'weight' => '-10',
        ),
        'views-osu_live_feeds-wordpress' => array(
          'module' => 'views',
          'delta' => 'osu_live_feeds-wordpress',
          'region' => 'main_second',
          'weight' => '-9',
        ),
      ),
    ),
  );
  $context->condition_mode = 1;

  // Translatables
  // Included for use with string extractors like potx.
  t('A context that displays RSS Feeds for each child unit');
  t('live feeds contextual blocks');
  $export['live-feeds-contextual-blocks'] = $context;

  return $export;
}
