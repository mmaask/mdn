<?php

namespace Modena\Payment\model;

class ModenaOrderItem extends ModenaBaseModel {
  /**
   * @var string
   */
  public $description;
  /**
   * @var float
   */
  public $amount;
  /**
   * @var int
   */
  public $quantity;
  /**
   * @var int
   */
  public $currency;

  /**
   * @param string $description
   * @param float $amount
   * @param int $quantity
   * @param string $currency
   */
  public function __construct($description, $amount, $quantity, $currency) {

    $this->description = $description;
    $this->amount = $amount;
    $this->quantity = $quantity;
    $this->currency = $currency;
  }
}