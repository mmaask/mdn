<?php

class HomeUrl {
  public static function baseOnly() {

    $url = get_home_url();
    $urlParts = parse_url($url);
    $scheme = "http://";
    if (isset($urlParts['scheme'])) {
      $scheme = "{$urlParts['scheme']}://";
    }
    $domain = $urlParts['host'] ?? $urlParts['path'];

    return untrailingslashit("{$scheme}{$domain}");
  }
}