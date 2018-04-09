<?php

namespace Drupal\live_feeds;

/**
 * Class LiveFeedsSmartLimit.
 *
 * @package Drupal\live_feeds
 */
class LiveFeedsSmartTrim {

  /**
   * Takes a long string and truncates it after a number of words.
   *
   * @param string $stringBig
   *   The string to be truncated.
   * @param int $wordLimit
   *   The number of words to limit to.
   *
   * @return string
   *   The truncated string.
   */
  public function liveFeedsLimit($stringBig, $wordLimit) {
    $string = explode(' ', $stringBig);
    if (count($string) > $wordLimit) {
      return implode(' ', array_slice($string, 0, $wordLimit)) . " ...";
    }
    return implode(' ', $string) . " ...";
  }

}
