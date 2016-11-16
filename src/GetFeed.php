<?php

namespace Drupal\live_feeds;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;

/**
 * Class GetFeed.
 *
 * @package Drupal\live_feeds
 */
class GetFeed {
  protected $httpClient;

  /**
   * Constructor.
   */
  public function __construct(Client $httpClient) {
    $this->httpClient = $httpClient;
  }

  public function getFeed($feed_url) {
    // Try to request the feed.
    try {
      $request = $this->httpClient->request('GET', $feed_url);
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
