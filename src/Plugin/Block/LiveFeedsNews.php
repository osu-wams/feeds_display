<?php

namespace Drupal\live_feeds\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\date_ap_style\ApStyleDateFormatter;
use Drupal\live_feeds\GetFeed;
use Drupal\live_feeds\LiveFeedsSmartTrim;
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
   * The Live Feeds Service.
   *
   * @var \Drupal\live_feeds\GetFeed
   */
  private GetFeed $getFeed;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\live_feeds\LiveFeedsSmartTrim $live_feeds_smart_trim
   *   The Live Feeds trimmer.
   * @param \Drupal\live_feeds\GetFeed $getFeed
   *   Service to retrieve RSS feeds.
   * @param \Drupal\date_ap_style\ApStyleDateFormatter $apStyleDateFormatter
   *   The Date AP Style Formatter.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LiveFeedsSmartTrim $live_feeds_smart_trim,
    GetFeed $getFeed,
    ApStyleDateFormatter $apStyleDateFormatter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->liveFeedsSmartTrim = $live_feeds_smart_trim;
    $this->getFeed = $getFeed;
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
      $container->get('live_feeds.live_feeds_smart_trim'),
      $container->get('live_feeds.live_feed'),
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
      '#max' => 10,
      '#required' => TRUE,
    ];
    $form['live_feeds_news_word_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Word Limit'),
      '#description' => $this->t('Enter a number to limit the number of words are displayed for each item. A value greater than 20 will use the teaser from the RSS feed.'),
      '#default_value' => $this->configuration['live_feeds_news_word_limit'],
      '#weight' => '3',
      '#min' => 5,
      '#max' => 140,
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
    $word_limit = (int) $this->configuration['live_feeds_news_word_limit'];
    $max_items = (int) $this->configuration['live_feeds_items_total'];
    $current_item = 0;
    $build['#markup'] = '';
    $xml = $this->getFeed->getFeed(($this->configuration['live_feeds_news_link']));
    if ($xml !== FALSE) {
      // Need this to parse the description.
      libxml_use_internal_errors(TRUE);
      /** @var \SimpleXMLElement $story */
      foreach ($xml->channel->item as $story) {
        if (++$current_item > $max_items) {
          break;
        }
        $body = $this->liveFeedsSmartTrim->liveFeedsLimit(trim($story->description), $word_limit);
        $thumb = (string) $story->enclosure['url'];
        $date_text = $story->pubDate;
        $pub_date = $this->apStyleDateFormatter->formatTimestamp(strtotime($date_text), ['always_display_year' => TRUE]);
        $url = Url::fromUri($story->link);
        $read_more_link = Link::fromTextAndUrl($this->t('Read full story'), $url)
          ->toString();
        if ($thumb) {
          $build['#live_feeds_news_data']['#' . $current_item]['#news_thumb']['#markup'] = '<img src="' . $thumb . '" width="75" alt="OSU News Release" />';
        }
        if ($url) {
          $build['#live_feeds_news_data']['#' . $current_item]['#news_story_link'] = Link::fromTextAndUrl($story->title, $url);
        }
        $build['#live_feeds_news_data']['#' . $current_item]['#news_date'] = $pub_date;
        $build['#live_feeds_news_data']['#' . $current_item]['#news_teaser']['#markup'] = $body . ' ' . $read_more_link;
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

}
