<?php

namespace Modena\Payment\model;

class ModenaResponse {
  private $header;
  private $body;
  private $status;

  /**
   * @param $header
   * @param $body
   * @param $status
   */
  public function __construct($header, $body, $status) {

    $this->header = is_array($header) ? $header : json_decode($header, true);
    $this->body = is_array($body) ? $body : json_decode($body, true);
    $this->status = is_array($status) ? $status : json_decode($status, true);
  }

  public function getLocationFromHeader() {

    return $this->header['location'];
  }

  public function getBody() {

    return $this->body;
  }

  /**
   * @return string
   */
  public function getErrorMessage() {

    if (!$this->hasError()) {
      return '';
    }
    if ($this->getBodyValue('error')) {
      return sprintf('Error: %s | Message: %s', $this->getBodyValue('error', 'N/A'), $this->getBodyValue('error_description', '--EMPTY--'));
    }
    else {
      return sprintf('Error header: %s | body: %s', json_encode($this->header), json_encode($this->body));
    }
  }

  /**
   * @return bool
   */
  public function hasError() {

    return $this->status['code'] >= 400;
  }

  /**
   * @param string $key
   * @param null $default
   *
   * @return string|null
   */
  public function getBodyValue($key, $default = null) {

    return isset($this->body[$key]) ? $this->body[$key] : $default;
  }
}