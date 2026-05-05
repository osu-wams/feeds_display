<?php

namespace Drupal\live_feeds\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\date_ap_style\ApStyleDateFormatter;
use Drupal\live_feeds\GetFeed;
use Drupal\live_feeds\LiveFeedsSmartTrim;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Live Feeds News' block.
 */
#[Block(
  id: 'live_feeds_news',
  admin_label: new TranslatableMarkup('OSU Live Feeds News'),
  category: new TranslatableMarkup('OSU')
)]
final class LiveFeedsNews extends BlockBase implements ContainerFactoryPluginInterface {

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
    $word_limit = (int) $this->configuration['live_feeds_news_word_limit'];
    $max_items = (int) $this->configuration['live_feeds_items_total'];
    $xml = $this->getFeed->getFeed(($this->configuration['live_feeds_news_link']));
    if ($xml === FALSE) {
      return ['#markup' => 'There was an error loading the feed.'];
    }
    $items = [];
    $current_count = 0;
    /** @var \SimpleXMLElement $item */
    foreach ($xml->channel->item as $item) {
      if (++$current_count > $max_items) {
        break;
      }
      $url = Url::fromUri((string) $item->link);
      $item_title_link = Link::fromTextAndUrl((string) $item->title, $url)
        ->toRenderable();
      $read_more = Link::fromTextAndUrl($this->t('Read full story'), $url)
        ->toString();
      $thumb_url = (string) $item->enclosure['url'] ?? '';
      $filtered_description = Xss::filter($this->liveFeedsSmartTrim->liveFeedsLimit(trim((string) $item->description), $word_limit));
      $teaser = $filtered_description . ' ' . $read_more;
      $pub_date = $this->apStyleDateFormatter->formatTimestamp(strtotime((string) $item->pubDate), ['always_display_year' => TRUE]);
      $time_stamp = DrupalDateTime::createFromTimestamp(strtotime((string) $item->pubDate))
        ->format('c');
      $items[] = [
        'title_link' => $item_title_link,
        'date' => $pub_date,
        'timestamp' => $time_stamp,
        'teaser' => [
          '#markup' => $teaser,
        ],
        'thumbnail' => $thumb_url ? [
          '#theme' => 'image',
          '#uri' => $thumb_url,
          '#alt' => '',
          '#width' => 75,
          '#attributes' => ['class' => ['news-item__image']],
        ] : [],
      ];
    }
    return [
      '#type' => 'component',
      '#component' => 'live_feeds:feed-list',
      '#props' => [
        'wrapper_class' => 'live-feeds live-feeds--news',
        'items' => $items,
      ],
      '#cache' => [
        'max-age' => 300,
      ],
    ];
  }

}
