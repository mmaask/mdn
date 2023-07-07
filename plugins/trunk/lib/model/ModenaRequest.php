<?php

namespace Modena\Payment\model;

class ModenaRequest {
  /**
   * @var ModenaApplication
   */
  public $application;
  /**
   * @var string
   */
  public $returnUrl;
  /**
   * @var string
   */
  public $cancelUrl;
  /**
   * @var string
   */
  public $callbackUrl;

  /**
   * @param ModenaApplication $application
   * @param string $returnUrl
   * @param string $cancelUrl
   * @param string $callbackUrl
   */
  public function __construct($application, $returnUrl, $cancelUrl, $callbackUrl) {

    $this->application = json_decode(json_encode($application), true);
    $this->returnUrl = $returnUrl;
    $this->cancelUrl = $cancelUrl;
    $this->callbackUrl = $callbackUrl;
  }

  public function getAsPOSTFields() {

    return json_encode($this->toArray());
  }

  public function toArray() {

    $data = $this->application;
    $data['returnUrl'] = $this->returnUrl;
    $data['cancelUrl'] = $this->cancelUrl;
    $data['callbackUrl'] = $this->callbackUrl;

    return $data;
  }
}