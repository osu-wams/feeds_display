<?php

namespace Drupal\live_feeds\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\live_feeds\LiveFeedsSmartTrim;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Live Feeds Events' block.
 *
 * @Block(
 *  id = "live_feeds_events",
 *  admin_label = @Translation("Live Feeds Events"),
 * )
 */
class LiveFeedsEvents extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * GuzzleHttp\Client definition.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;
  /**
   * Drupal\live_feeds\LiveFeedsSmartTrim definition.
   *
   * @var \Drupal\live_feeds\LiveFeedsSmartTrim
   */
  protected $liveFeedsSmartTrim;

  /**
   * Creates an Live Events block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Client $http_client,
    LiveFeedsSmartTrim $live_feeds_smart_trim
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->liveFeedsSmartTrim = $live_feeds_smart_trim;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('live_feeds.live_feeds_smart_trim')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'live_feeds_event_link' => $this->t(''),
      'live_feeds_event_total' => $this->t('5'),
      'live_feeds_event_word_limit' => $this->t('30'),
    ] + parent::defaultConfiguration();

  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['live_feeds_event_link'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Event RSS Feed'),
      '#description' => $this->t('Enter the RSS feeds to the Event Calender.'),
      '#default_value' => $this->configuration['live_feeds_event_link'],
      '#maxlength' => 256,
      '#size' => 64,
      '#weight' => '1',
      '#required' => TRUE,
    );
    $form['live_feeds_event_total'] = array(
      '#type' => 'number',
      '#title' => $this->t('Limit number of events.'),
      '#description' => $this->t('Enter a number grater than 0 to limit the display of events.'),
      '#default_value' => $this->configuration['live_feeds_event_total'],
      '#min' => 1,
      '#max' => 5,
      '#maxlength' => 1,
      '#size' => 1,
      '#weight' => '2',
      '#required' => TRUE,
    );
    $form['live_feeds_event_word_limit'] = array(
      '#type' => 'number',
      '#title' => $this->t('Word Limit'),
      '#description' => $this->t('Number of words to show from the body of the feed.'),
      '#default_value' => $this->configuration['live_feeds_event_word_limit'],
      '#min' => 5,
      '#maxlength' => 3,
      '#size' => 2,
      '#weight' => '3',
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    parent::blockValidate($form, $form_state);
    if ($form_state->getValue('live_feeds_event_total') > 5) {
      drupal_set_message($this->t('Maximum number of events is 5.'), 'error');
      $form_state->setErrorByName('live_feeds_event_total', $this->t('Too many events, 5 or less can be set.'));
    }
    if ($form_state->getValue('live_feeds_event_total') < 0) {
      drupal_set_message($this->t('Can not have a negative number of events to show.'), 'error');
      $form_state->setErrorByName('live_feeds_event_total', $this->t('Must be a positive number.'));
    }
    if ($form_state->getValue('live_feeds_event_word_limit') < 0) {
      drupal_set_message($this->t('Can not set a word limit below 0.'));
      $form_state->setErrorByName('live_feeds_event_word_limit', $this->t('Must be a positive number.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['live_feeds_event_link'] = $form_state->getValue('live_feeds_event_link');
    $this->configuration['live_feeds_event_total'] = $form_state->getValue('live_feeds_event_total');
    $this->configuration['live_feeds_event_word_limit'] = $form_state->getValue('live_feeds_event_word_limit');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $feed_url = $this->configuration['live_feeds_event_link'];
    $xml = $this->parseFeed($this->configuration['live_feeds_event_link']);
    // Need this to parse the description.
    $html = new \DOMDocument();

    $items = 0;

    // Parse the feed_url to determine the calendar link.
    preg_match('/\/(\w+-*\w*-*\w*)\/rss20.xml/', $feed_url, $match);
    $cal_link = 'http://calendar.oregonstate.edu/' . $match[1];
    if ($xml !== FALSE) {
      foreach ($xml->channel->item as $event) {
        if (++$items > (int) $this->configuration['live_feeds_event_total']) {
          break;
        }

        // Get the event data.
        $title = $event->title;
        $link = $event->link;
        $desc = $event->description;

        // Remove the date div from the description.
        $html->loadHTMl((string) $desc);
        $date = $html->getElementsByTagName('div')->item(0);
        $div = $date->parentNode;
        $div->removeChild($date);

        $desc = utf8_decode($html->saveXML($div));

        // Strip html except for links.
        $desc = strip_tags($desc, '<a>');

        // Start and end dates use the osu namespace.
        $nodes = $event->children('edu.oregonstate.calendar', TRUE);
        $start = $nodes->dtstart;
        $end = $nodes->dtend;

        // Parse the date.
        $date = strtotime($start);
        $day = date('d', $date);
        $mon = date('m', $date);
        $month = date('M', $date);
        $year = date('Y', $date);
        $spell_mon = date('F', $date);

        // Get today's date.
        $today_mon = date('m', time());
        $today_day = date('d', time());
        $today_year = date('Y', time());

        // Skip if earlier than today.
        if (
          ($year < $today_year) ||
          ($year == $today_year && $mon < $today_mon) ||
          ($year == $today_year && $mon == $today_mon && $day < $today_day)
        ) {
          $items--;
          continue;
        }
        // Truncate the body and fix all unclosed tags.
        $body = $this->liveFeedsSmartTrim->liveFeedsLimit(trim($desc), (int) $this->configuration['live_feeds_event_word_limit']);
        // $body = trim($desc);
        $tidy = new \tidy();
        $body = $tidy->repairString($body, array('show-body-only' => 1));
        // Build output string.
        $build['#live_feeds_cal_data']['#' . $items]['#day']['#markup'] = $day;
        $build['#live_feeds_cal_data']['#' . $items]['#month']['#markup'] = $month;
        $build['#live_feeds_cal_data']['#' . $items]['#month_spell']['#markup'] = $spell_mon;
        $build['#live_feeds_cal_data']['#' . $items]['#year']['#markup'] = $year;
        $build['#live_feeds_cal_data']['#' . $items]['#description']['title']['#markup'] = '<a href="' . $link . '">' . $title . '</a >';
        $build['#live_feeds_cal_data']['#' . $items]['#description']['body']['#markup'] = $body;
      }
      $build['#live_feeds_event_link']['#markup'] = '<a href="' . $cal_link . '">View the Entire Calendar</a>';
      $build['#theme'] = 'live_feeds_events';
      $build['#attached'] = array(
        'library' => array(
          'live_feeds/live_feeds_events',
        ),
      );
    }
    else {
      $build['#markup'] .= "There was an error loading the Event Calendar.";
    }
    return $build;
  }

  /**
   * Get the feed data from outside the site.
   *
   * @param string $feed
   *   URL of the Feeds.
   *
   * @return mixed
   *   XML of the string data.
   */
  private function parseFeed($feed) {
    // Try to request the feed.
    try {
      $request = $this->httpClient->request('GET', $feed);
      $response = $request->getBody();
      $file_contents = preg_replace('/[^[:print:]\r\n]/', '', $response);
      $xml = simplexml_load_string($file_contents);
      return $xml;
    } catch (RequestException $e) {
      // Log the failed request to watchdog.
      watchdog_exception('live_feeds', $e);
    }
    return FALSE;
  }
}
