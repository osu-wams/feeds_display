<?php

namespace Drupal\live_feeds\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\date_ap_style\ApStyleDateFormatter;
use Drupal\live_feeds\LiveFeedsSmartTrim;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Live Feeds News' block.
 *
 * @Block(
 *  id = "live_feeds_news",
 *  admin_label = @Translation("Live Feeds News"),
 * )
 */
class LiveFeedsNews extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Guzzle Client.
   *
   * @var \GuzzleHttp\Client
   */
  private $httpClient;

  /**
   * The Smart Trim.
   *
   * @var \Drupal\live_feeds\LiveFeedsSmartTrim
   */
  private $liveFeedsSmartTrim;

  /**
   * The AP Style date.
   *
   * @var \Drupal\date_ap_style\ApStyleDateFormatter
   */
  private ApStyleDateFormatter $apStyleDateFormatter;


  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP Client.
   * @param \Drupal\live_feeds\LiveFeedsSmartTrim $live_feeds_smart_trim
   *   The Live Feeds trimmer.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ClientInterface $http_client,
    LiveFeedsSmartTrim $live_feeds_smart_trim,
    ApStyleDateFormatter $apStyleDateFormatter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->liveFeedsSmartTrim = $live_feeds_smart_trim;
    $this->apStyleDateFormatter = $apStyleDateFormatter;
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
      $container->get('live_feeds.live_feeds_smart_trim'),
      $container->get('date_ap_style.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'live_feeds_news_link' => '',
        'live_feeds_items_total' => $this->t('5'),
        'live_feeds_news_word_limit' => $this->t('30'),
      ] + parent::defaultConfiguration();

  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['live_feeds_news_link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('News Feed URL'),
      '#description' => $this->t('The RSS feed from the News Page.'),
      '#default_value' => $this->configuration['live_feeds_news_link'],
      '#maxlength' => 256,
      '#size' => 64,
      '#weight' => '1',
      '#required' => TRUE,
    ];
    $form['live_feeds_items_total'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of Items to display.'),
      '#description' => $this->t('Enter a Number to change how many items are displayed in the block.'),
      '#default_value' => $this->configuration['live_feeds_items_total'],
      '#weight' => '2',
      '#min' => 1,
      '#required' => TRUE,
    ];
    $form['live_feeds_news_word_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Word Limit'),
      '#description' => $this->t('Enter a number to limit the number of words are displayed for each item.'),
      '#default_value' => $this->configuration['live_feeds_news_word_limit'],
      '#weight' => '3',
      '#min' => 5,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['live_feeds_news_link'] = $form_state->getValue('live_feeds_news_link');
    $this->configuration['live_feeds_items_total'] = $form_state->getValue('live_feeds_items_total');
    $this->configuration['live_feeds_news_word_limit'] = $form_state->getValue('live_feeds_news_word_limit');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $items = 0;
    $build['#markup'] = '';
    // $xml = simplexml_load_string($file_contents);
    $xml = $this->parseFeed($this->configuration['live_feeds_news_link']);
    if ($xml !== FALSE) {
      // Need this to parse the description.
      $html = new \DOMDocument('1.0', 'UTF-8');
      $internalErrors = libxml_use_internal_errors(TRUE);
      foreach ($xml->channel->item as $story) {
        if (++$items > (int) $this->configuration['live_feeds_items_total']) {
          break;
        }
        unset($teaser);
        // Parse the description into HTML divs and look for specific classes.
        $html->loadHTML(mb_convert_encoding($story->description, 'HTML-ENTITIES', 'UTF-8'));
        $thumb = (string) $story->enclosure['url'];
        $date_text = $story->pubDate;
        $teaser = $html->getElementsByTagName('div')->item(0)->nodeValue;
        $body = $html->getElementsByTagName('div')->item(1)->nodeValue;
        $pub_date = $this->apStyleDateFormatter->formatTimestamp(strtotime($date_text), ['always_display_year' => TRUE]);

        $build['#live_feeds_news_data']['#' . $items]['#news_thumb']['#markup'] = '<img src="' . $thumb . '" width="75" height="75" alt="OSU News Release" />';
        $url = Url::fromUri($story->link);
        $build['#live_feeds_news_data']['#' . $items]['#news_story_link'] = Link::fromTextAndUrl($story->title, $url);
        $build['#live_feeds_news_data']['#' . $items]['#news_date'] = $pub_date;

        // Display teaser if there is one, else truncate body.
        if (isset($teaser)) {
          $build['#live_feeds_news_data']['#' . $items]['#news_teaser']['#markup'] = $teaser;
        }
        else {
          $build['#live_feeds_news_data']['#' . $items]['#news_teaser']['#markup'] = $this->liveFeedsSmartTrim->liveFeedsLimit(trim($body), 20);
        }
      }
      libxml_clear_errors();
      $build['#theme'] = 'live_feeds_news';
      $build['#attached'] = [
        'library' => [
          'live_feeds/live_feeds_news',
        ],
      ];
    }
    else {
      $build['#markup'] .= "There was an error loading the feed.";
    }
    // Setting max age to 5 minutes. This is needed or it caches indefinitely.
    $build['#cache']['max-age'] = 300;
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
      return simplexml_load_string($file_contents);
    }
    catch (RequestException $e) {
      // Log the failed request to watchdog.
      watchdog_exception('live_feeds', $e);
    }
    return FALSE;
  }

}
