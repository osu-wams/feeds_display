<?php

declare(strict_types=1);

namespace Drupal\live_feeds\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\date_ap_style\ApStyleDateFormatter;
use Drupal\live_feeds\GetFeed;
use Drupal\live_feeds\LiveFeedsSmartTrim;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
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

  use LoggerChannelTrait;

  /**
   * Defines the view modes for displaying live feeds.
   *
   * This constant specifies the configuration for each view mode, including the components
   * used for rendering and corresponding CSS wrapper classes.
   *
   * Available view modes:
   * - 'list': Displays feeds in a list format.
   *   - 'component': Identifier for the list component.
   *   - 'wrapper': CSS classes applied to the list wrapper container.
   * - 'card': Displays feeds in a card-based format.
   *   - 'component': Identifier for the card component.
   *   - 'wrapper': CSS classes applied to the card wrapper container.
   */
  private const array VIEW_MODE = [
    'list' => [
      'component' => 'live_feeds:feed-list',
      'wrapper' => 'live-feeds live-feeds--news',
    ],
    'card' => [
      'component' => 'live_feeds:cards',
      'wrapper' => 'live-feeds live-feeds--news live-feeds--cards',
    ],
  ];

  /**
   * The logger instance used for logging messages and errors.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\live_feeds\LiveFeedsSmartTrim $liveFeedsSmartTrim
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
    private readonly LiveFeedsSmartTrim $liveFeedsSmartTrim,
    private readonly GetFeed $getFeed,
    private readonly ApStyleDateFormatter $apStyleDateFormatter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $this->getLogger('feeds_display');
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
  public function defaultConfiguration(): array {
    return [
      'live_feeds_news_link' => '',
      'live_feeds_items_total' => 5,
      'live_feeds_news_word_limit' => 30,
      'live_feeds_news_display_mode' => 'default',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
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
      '#max' => 5,
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
    $form['live_feeds_news_display_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#description' => $this->t('Select a different display of the news stories'),
      '#default_value' => $this->configuration['live_feeds_news_display_mode'] ?? NULL,
      '#weight' => '4',
      '#options' => [
        'list' => $this->t('List'),
        'card' => $this->t('Cards'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['live_feeds_news_link'] = $form_state->getValue('live_feeds_news_link');
    $this->configuration['live_feeds_items_total'] = $form_state->getValue('live_feeds_items_total');
    $this->configuration['live_feeds_news_word_limit'] = $form_state->getValue('live_feeds_news_word_limit');
    $this->configuration['live_feeds_news_display_mode'] = $form_state->getValue('live_feeds_news_display_mode');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $word_limit = (int) $this->configuration['live_feeds_news_word_limit'];
    $max_items = (int) $this->configuration['live_feeds_items_total'];
    try {
      $xml = $this->getFeed->getFeed(($this->configuration['live_feeds_news_link']));
    }
    catch (GuzzleException $e) {
      $this->logger->error('Error fetching feed: @error', ['@error' => $e->getMessage()]);
      return ['#markup' => 'There was an error loading the feed.'];
    }
    $view_mode = $this->configuration['live_feeds_news_display_mode'];
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
      $items[] = $this->buildItem($item, $word_limit, $view_mode);
    }
    $mode = self::VIEW_MODE[$view_mode];
    return [
      '#type' => 'component',
      '#component' => $mode['component'],
      '#props' => [
        'wrapper_class' => $mode['wrapper'],
        'items' => $items,
        'max_items' => $max_items,
      ],
      '#cache' => [
        'max-age' => 300,
      ],
    ];
  }

  /**
   * Constructs the feed item with transformed data.
   *
   * @param \SimpleXMLElement $item
   *   The XML element representing the item.
   * @param int $wordLimit
   *   The maximum number of words to limit the description.
   * @param string $viewMode
   *   The view mode for rendering the thumbnail.
   *
   * @return array|null
   *   An associative array containing the processed item data, or NULL if the
   *   item is invalid.
   */
  private function buildItem(\SimpleXMLElement $item, int $wordLimit, string $viewMode): ?array {
    $itemLink = (string) $item->link;
    if ($itemLink === '') {
      return NULL;
    }
    try {
      $url = Url::fromUri((string) $item->link);
    }
    catch (\InvalidArgumentException) {
      return NULL;
    }
    $category = '';
    if (is_countable($item->category) && count($item->category) > 0) {
      $category = (string) $item->category[0];
    }
    $item_title_link = Link::fromTextAndUrl((string) $item->title, $url)
      ->toRenderable();
    $read_more = Link::fromTextAndUrl($this->t('Read full story'), $url)
      ->toString();
    $thumb_url = (string) $item->enclosure['url'] ?? '';
    $thumbnail = $this->buildThumbnail($thumb_url, $viewMode);
    $filtered_description = Xss::filter($this->liveFeedsSmartTrim->liveFeedsLimit(trim((string) $item->description), $wordLimit));
    $teaser = $filtered_description . ' ' . $read_more;
    $timestamp = strtotime((string) $item->pubDate);
    if ($timestamp) {
      $pub_date = $this->apStyleDateFormatter->formatTimestamp($timestamp, ['always_display_year' => TRUE]);
      $iso_date = DrupalDateTime::createFromTimestamp($timestamp)
        ->format('c');
    }
    else {
      $pub_date = $iso_date = '';
    }
    return [
      'title_link' => $item_title_link,
      'title' =>$item->title,
      'link' => $item->link,
      'date' => $pub_date,
      'timestamp' => $iso_date,
      'teaser' => [
        '#markup' => $teaser,
      ],
      'thumbnail' => $thumbnail,
      'category' => $category,
    ];
  }

  /**
   * Builds a thumbnail representation based on the provided URL and view mode.
   *
   * @param string $thumbUrl
   *   The URL of the thumbnail image.
   * @param string $viewMode
   *   The view mode for rendering the thumbnail (e.g., 'card').
   *
   * @return string|array
   *   The thumbnail URL as a string if the view mode is 'card', an empty array
   *   if the URL is empty, or a renderable array representing the thumbnail
   *   image.
   */
  private function buildThumbnail(string $thumbUrl, string $viewMode): string | array {
    if ($viewMode === 'card') {
      return $thumbUrl;
    }
    if ($thumbUrl === '') {
      return [];
    }
    return [
      '#theme' => 'image',
      '#uri' => $thumbUrl,
      '#alt' => '',
      '#width' => 75,
      '#attributes' => ['class' => ['news-item__image']],
    ];
  }

}
