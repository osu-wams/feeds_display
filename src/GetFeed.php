<?php

namespace Drupal\live_feeds;

use Drupal\Core\Security\TrustedCallbackInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Simple class to provide functions for requesting RSS feeds.
 *
 * @package Drupal\live_feeds
 */
class GetFeed implements TrustedCallbackInterface {

  /**
   * The Guzzle HTTP Client.
   *
   * @var \GuzzleHttp\Client
   */
  private $httpClient;

  /**
   * Constructor.
   */
  public function __construct(ClientInterface $httpClient) {
    $this->httpClient = $httpClient;
  }

  public static function trustedCallbacks() {
    return ['getFeed'];
  }

  /**
   * Get the RSS feed from given URL.
   *
   * @param string $feed_url
   *   The Feed url will attempt to retrieve.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getFeed($feed_url) {
    // Try to request the feed.
    try {
      $request = $this->httpClient->request('GET', $feed_url);
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
