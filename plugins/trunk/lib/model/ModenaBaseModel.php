<?php

namespace Modena\Payment\model;

class ModenaBaseModel {
  /**
   * Convert Object to Array with json_decode because (array) changes key-s
   *
   * @return array
   */
  public function toArray() {

    return json_decode(json_encode($this), true);
  }
}