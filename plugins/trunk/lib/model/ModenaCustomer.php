<?php

namespace Modena\Payment\model;

class ModenaCustomer extends ModenaBaseModel {
  /**
   * @var string
   */
  public $firstName;
  /**
   * @var string
   */
  public $lastName;
  /**
   * @var string
   */
  public $email;
  /**
   * @var string
   */
  public $phoneNumber;
  /**
   * @var string
   */
  public $fullAddress;

  /**
   * @param string $firstName
   * @param string $lastName
   * @param string $email
   * @param string $phoneNumber
   * @param string $fullAddress
   */
  public function __construct($firstName, $lastName, $email, $phoneNumber, $fullAddress) {

    $this->firstName = $firstName;
    $this->lastName = $lastName;
    $this->phoneNumber = $phoneNumber;
    $this->email = $email;
    $this->fullAddress = $fullAddress;
  }
}