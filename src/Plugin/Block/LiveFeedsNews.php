<?php

namespace Drupal\live_feeds\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\live_feeds\LiveFeedsSmartTrim;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;
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
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
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
        'live_feeds_news_link' => $this->t(''),
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
      '#description' => $this->t('Enter a Number to change how many items are displaed in the block.'),
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
    $thumb = '';
    $date_text = '';
    $body = '';
    $build['#markup'] = '';
    //$xml = simplexml_load_string($file_contents);
    $xml = $this->parseFeed($this->configuration['live_feeds_news_link']);
    if ($xml !== FALSE) {
      // Need this to parse the description.
      $html = new \DOMDocument();

      foreach ($xml->channel->item as $story) {
        if (++$items > (int) $this->configuration['live_feeds_items_total']) {
          break;
        }
        // Don't reuse content from previous iteration.
        unset($teaser);

        // Parse the description into HTML divs and look for specific classes.
        $html->loadHTML($story->description);
        foreach ($html->getElementsByTagName('div') as $div) {
          $class = $div->getAttribute('class');

          // Get the Date.
          if (strpos($class, 'field-field-date')) {
            $date_text = $div->getElementsByTagName('span')->item(0)->nodeValue;
          }

          // Get the thumbnail URL.
          elseif (strpos($class, 'field-field-thumbnail')) {
            $img = $div->getElementsByTagName('img')->item(0);
            // We want to preserve all of the html for the img tag.
            $thumb = str_replace('http://', '//', $html->saveXML($img));
          }

          // Get the teaser.
          elseif (strpos($class, 'field-field-teaser')) {
            $teaser = utf8_decode($div->getElementsByTagName('p')
              ->item(0)->nodeValue);
          }

          // Get the body.
          elseif (strpos($class, 'field-field-body')) {
            if (isset($div->getElementsByTagName('div')->item(2)->nodeValue)) {
              $body = mb_convert_encoding($div->getElementsByTagName('div')
                ->item(2)->nodeValue, "ISO_8859-1", "UTF-8");
            }
          }
        }
        /*
         * Pass the data to Twig.
         */
        $build['#live_feeds_news_data']['#' . $items]['#news_thumb']['#markup'] = $thumb;
        $url = Url::fromUri($story->link);
        $build['#live_feeds_news_data']['#' . $items]['#news_story_link'] = Link::fromTextAndUrl($story->title, $url);
        $build['#live_feeds_news_data']['#' . $items]['#news_date'] = date('M j, Y', strtotime($date_text));

        // Display teaser if there is one, else truncate body.
        if (isset($teaser)) {
          $build['#live_feeds_news_data']['#' . $items]['#news_teaser']['#markup'] = $teaser;
        }
        else {
          $build['#live_feeds_news_data']['#' . $items]['#news_teaser']['#markup'] = $this->liveFeedsSmartTrim->liveFeedsLimit(trim($body), 20);
        }
      }
      $build['#theme'] = 'live_feeds_news';
      $build['#attached'] = array(
        'library' => array(
          'live_feeds/live_feeds_news',
        ),
      );
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
      $xml = simplexml_load_string($file_contents);
      return $xml;
    } catch (RequestException $e) {
      // Log the failed request to watchdog.
      watchdog_exception('live_feeds', $e);
    }
    return FALSE;
  }
}
