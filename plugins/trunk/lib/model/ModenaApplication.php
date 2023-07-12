<?php

namespace Modena\Payment\model;

class ModenaApplication extends ModenaBaseModel {
  /**
   * @var string
   */
  public $selectedOption;
  /**
   * @var string
   */
  public $orderId;
  /**
   * @var float
   */
  public $totalAmount;
  /**
   * @var ModenaOrderItem[]
   */
  public $orderItems;
  /**
   * @var ModenaCustomer
   */
  public $customer;
  /**
   * @var string
   */
  public $timestamp;
  /**
   * @var string
   */
  public $currency;

  /**
   * @param string $selectedOption
   * @param string $orderId
   * @param float $totalAmount
   * @param ModenaOrderItem[] $orderItems
   * @param ModenaCustomer $customer
   * @param string $timestamp - format Y-m-d\TH:i:s.u\Z
   * @param string $currency
   */
  public function __construct(
      $selectedOption,
     $orderId,
     $totalAmount,
     $orderItems,
     $customer,
     $timestamp,
     $currency = 'EUR') {
    $this->selectedOption = $selectedOption;
    $this->orderId = $orderId;
    $this->totalAmount = $totalAmount;
    $this->orderItems = $orderItems;
    $this->customer = $customer;
    $this->timestamp = $timestamp;
    $this->currency = $currency;
  }
}