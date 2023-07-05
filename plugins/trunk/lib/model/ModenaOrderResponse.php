<?php

namespace Modena\Payment\model;

class ModenaOrderResponse {
  /**
   * @var string
   */
  private $applicationId;
  /**
   * @var string
   */
  private $orderId;
  /**
   * @var string
   */
  private $redirectLocation;

  /**
   * @param string $applicationId
   * @param string $orderId
   * @param string $redirectLocation
   */
  public function __construct($applicationId, $orderId, $redirectLocation) {

    $this->applicationId = $applicationId;
    $this->orderId = $orderId;
    $this->redirectLocation = $redirectLocation;
  }

  /**
   * @return string
   */
  public function getApplicationId() {

    return $this->applicationId;
  }

  /**
   * @return string
   */
  public function getOrderId() {

    return $this->orderId;
  }

  /**
   * @return string
   */
  public function getRedirectLocation() {

    return $this->redirectLocation;
  }
}