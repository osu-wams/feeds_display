<?php

declare(strict_types=1);

namespace Drupal\live_feeds;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\live_feeds\Exception\FeedsDisplayParserException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Simple class to provide functions for requesting RSS feeds.
 *
 * @package Drupal\live_feeds
 */
class GetFeed implements TrustedCallbackInterface {

  use LoggerChannelTrait;

  /**
   * The Guzzle HTTP Client.
   *
   * @var \GuzzleHttp\Client
   */
  private ClientInterface $httpClient;

  /**
   * The logger instance used for logging messages and errors.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Constructor.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP Client.
   */
  public function __construct(ClientInterface $httpClient) {
    $this->httpClient = $httpClient;
    $this->logger = $this->getLogger('live_feeds');
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks(): array {
    return ['getFeed'];
  }

  /**
   * Get the RSS feed from given URL.
   *
   * @param string $feed_url
   *   The feed URL to retrieve.
   *
   * @return \SimpleXMLElement|false|null
   *   The feed as a SimpleXMLElement, or FALSE on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getFeed($feed_url): \SimpleXMLElement | false | null {
    // Try to request the feed.
    try {
      $http_response = $this->httpClient->request('GET', $feed_url);
      $response = (string) $http_response->getBody();

      return $this->parseResponseToXml($response);
    }
    catch (RequestException $e) {
      // Log the failed request to watchdog.
      $this->logger->error('Failed request for "@feed": @message', [
        '@feed' => $feed_url,
        '@message' => $e->getMessage(),
      ]);
    }
    catch (FeedsDisplayParserException $e) {
      $this->logger->error('Failed to parse the feed: "@feed": @message', [
        '@feed' => $feed_url,
        '@message' => $e->getMessage(),
      ]);
    }
    return FALSE;
  }

  /**
   * Cleans the response and converts it to XML.
   *
   * @param string $response
   *   The feed response to parse.
   *
   * @return \SimpleXMLElement|null
   *   The parsed feed as a SimpleXMLElement, or NULL on failure.
   *
   * @throws \Drupal\live_feeds\Exception\FeedsDisplayParserException
   *   If the feed cannot be loaded.
   */
  private function parseResponseToXml(string $response): ?\SimpleXMLElement {
    $cleaned_response = preg_replace(['/[^[:print:]\r\n]/', '/&nbsp;/'], '', $response);
    $feedXml = simplexml_load_string($cleaned_response, 'SimpleXMLElement', LIBXML_HTML_NOIMPLIED | LIBXML_NOCDATA | LIBXML_NOBLANKS );

    if ($feedXml === FALSE) {
      throw new FeedsDisplayParserException('Failed to parse the feed');
    }

    return $feedXml;
  }

}
