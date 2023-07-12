<?php

namespace Modena\Payment;

use Exception;
use Modena\Payment\model\ModenaOrderResponse;
use Modena\Payment\model\ModenaResponse;
use Modena\Payment\model\ModenaRequest;

class Modena {
  const HTTP_VERSION             = '1.1';
  const MAX_RETRIES              = 5;
  const RETRY_TIMEOUT_IN_SECONDS = 5;
  # DEV ENVIRONMENT PARAMS
  const DEV_API_URL   = 'https://api-dev.modena.ee';
  const DEV_TOKEN_URL = 'https://login-dev.modena.ee/oauth2/token';
  # LIVE ENVIRONMENT PARAMS
  const LIVE_API_URL   = 'https://api.modena.ee';
  const LIVE_TOKEN_URL = 'https://login.modena.ee/oauth2/token';
  # SLICE PAYMENT
  const SCOPE_SLICE             = 'slicepayment';
  const SLICE_PAYMENT_ORDER_URL = 'modena/api/merchant/slice-payment-order';
  # CREDIT PAYMENT
  const SCOPE_CREDIT             = 'creditpayment';
  const CREDIT_PAYMENT_ORDER_URL = 'modena/api/merchant/credit-payment-order';
  # BUSINESS LEASING PAYMENT
  const SCOPE_BUSINESS_LEASING             = 'businessleasing';
  const BUSINESS_LEASING_PAYMENT_ORDER_URL = 'modena/api/merchant/business-leasing-payment-order';
  # TRY NOW PAY LATER PAYMENT
  const SCOPE_CLICK                   = 'clickandtrypayment';
  const CLICK_PAYMENT_ORDER_URL       = 'modena/api/merchant/click-and-try-payment-order';
  const MODENA_APPLICATION_STATUS_URL = 'modena/api/merchant/applications/%s/status';
  # DIRECT PAYMENT
  const SCOPE_DIRECT                  = 'directpayment';
  const DIRECT_PAYMENT_ORDER_URL      = 'direct/api/partner-direct-payments/payment-order';
  const DIRECT_APPLICATION_STATUS_URL = 'direct/api/partner-direct-payments/%s/status';
  const DIRECT_PAYMENT_OPTION_URL     = 'direct/api/payment-options';
  const DEFAULT_ARGS
                                      = array(
        'httpversion' => self::HTTP_VERSION,
        'sslverify'   => false,
        'redirection' => 0,
        'headers'     => array(
           'Accept' => 'application/json'
        ),
        'cookies'     => array(),
     );
  protected $clientId;
  protected $clientSecret;
  protected $apiUrl;
  protected $tokenUrl;
  protected $pluginUserAgentData;

  /**
   * @param string $clientId
   * @param string $clientSecret
   * @param String $pluginUserAgentData
   * @param bool $isTestMode
   */
  public function __construct($clientId, $clientSecret, $pluginUserAgentData, $isTestMode = true) {

    $this->clientId = $clientId;
    $this->clientSecret = $clientSecret;
    $this->pluginUserAgentData = $pluginUserAgentData;
    $this->apiUrl = $isTestMode ? self::DEV_API_URL : self::LIVE_API_URL;
    $this->tokenUrl = $isTestMode ? self::DEV_TOKEN_URL : self::LIVE_TOKEN_URL;
  }

  /**
   * @param string $scope
   *
   * @return string
   * @throws Exception
   */
  private function getToken($scope) {

    $headers = array(
       'Content-Type'  => 'application/x-www-form-urlencoded',
       'Authorization' => 'Basic ' . base64_encode(sprintf('%s:%s', $this->clientId, $this->clientSecret))
    );
    $data = array(
       'grant_type' => 'client_credentials',
       'scope'      => $scope
    );
    $response = $this->sendRequest($this->tokenUrl, $data, $headers, 'POST');

    return $response->getBodyValue('access_token');
  }

  /**
   * @param string $requestUrl
   * @param array|string|null $body
   * @param array $headers
   * @param string $requestType
   *
   * @return ModenaResponse
   * @throws Exception
   */
  private function sendRequest($requestUrl, $body, $headers, $requestType = 'GET') {

    $defaultArgs = self::DEFAULT_ARGS;
    $defaultArgs['user-agent'] = __($this->pluginUserAgentData);
    $combinedHeaders = array_replace($defaultArgs['headers'], $headers);
    $args = array_replace(
       $defaultArgs, array(
       'body'    => $body,
       'headers' => $combinedHeaders,
       'method'  => $requestType
    ));
    $response = wp_remote_request($requestUrl, $args);
    if (is_wp_error($response)) {
      throw new Exception($response->get_error_message());
    }
    $modenaResponse = new ModenaResponse($response['headers']->getAll(), $response['body'], $response['response']);
    if ($modenaResponse->hasError()) {
      throw new Exception($modenaResponse->getErrorMessage());
    }

    return $modenaResponse;
  }

  /**
   * @param string $serviceUrl
   *
   * @return string
   */
  private function getServiceURL($serviceUrl) {

    return sprintf('%s/%s', rtrim($this->apiUrl, '/'), $serviceUrl);
  }

  /**
   * @param string $applicationUrl
   * @param string $applicationId
   * @param string $scope
   *
   * @return string
   * @throws Exception
   */
  public function sendApplicationStatusRequest($applicationUrl, $applicationId, $scope) {

    $token = $this->getToken($scope);
    $headers = array(
       'Content-Type'  => 'application/json',
       'Authorization' => 'Bearer ' . $token
    );
    $response
       = $this->sendRequest($this->getServiceURL(sprintf($applicationUrl, $applicationId)), [], $headers, 'GET');
    $retryCount = 0;
    while (!$response->getBodyValue('status') && $retryCount < self::MAX_RETRIES) {
      $response
         = $this->sendRequest($this->getServiceURL(sprintf($applicationUrl, $applicationId)), [], $headers, 'GET');
      $retryCount++;
      sleep(self::RETRY_TIMEOUT_IN_SECONDS);
    }

    return $response->getBodyValue('status');
  }

  /**
   * @param string $serviceUrl
   * @param ModenaRequest $request
   * @param string $scope
   *
   * @return ModenaOrderResponse
   * @throws Exception
   */
  private function sendPaymentOrderRequest($serviceUrl, $request, $scope) {

    $token = $this->getToken($scope);
    $headers = array(
       'Content-Type'  => 'application/json',
       'Authorization' => 'Bearer ' . $token
    );
    $response = $this->sendRequest($this->getServiceURL($serviceUrl), $request->getAsPOSTFields(), $headers, 'POST');

    return new ModenaOrderResponse($response->getBodyValue('id'), $response->getBodyValue('orderId'), $response->getLocationFromHeader());
  }

  /**
   *
   *
   * @param ModenaRequest $request
   *
   * @return ModenaOrderResponse
   * @throws Exception
   */
  public function postDirectPaymentOrder($request) {

    return $this->sendPaymentOrderRequest(self::DIRECT_PAYMENT_ORDER_URL, $request, self::SCOPE_DIRECT);
  }

  /**
   * @param ModenaRequest $request
   *
   * @return ModenaOrderResponse
   * @throws Exception
   */
  public function postSlicePaymentOrder($request) {

    return $response = $this->sendPaymentOrderRequest(self::SLICE_PAYMENT_ORDER_URL, $request, self::SCOPE_SLICE);
  }

  /**
   * @param ModenaRequest $request
   *
   * @return ModenaOrderResponse
   * @throws Exception
   */
  public function postCreditPaymentOrder($request) {

    return $response = $this->sendPaymentOrderRequest(self::CREDIT_PAYMENT_ORDER_URL, $request, self::SCOPE_CREDIT);
  }

  /**
   * @param ModenaRequest $request
   *
   * @return ModenaOrderResponse
   * @throws Exception
   */
  public function postBusinessLeasingPaymentOrder($request) {

    return $response
       = $this->sendPaymentOrderRequest(self::BUSINESS_LEASING_PAYMENT_ORDER_URL, $request, self::SCOPE_BUSINESS_LEASING);
  }

  /**
   * @param ModenaRequest $request
   *
   * @return ModenaOrderResponse
   * @throws Exception
   */
  public function postClickPaymentOrder($request) {

    return $response = $this->sendPaymentOrderRequest(self::CLICK_PAYMENT_ORDER_URL, $request, self::SCOPE_CLICK);
  }

  /**
   * @param string $applicationId
   *
   * @return string
   * @throws Exception
   */
  public function getDirectPaymentApplicationStatus($applicationId) {

    return $this->sendApplicationStatusRequest(self::DIRECT_APPLICATION_STATUS_URL, $applicationId, self::SCOPE_DIRECT);
  }

  /**
   * @param string $applicationId
   *
   * @return string
   * @throws Exception
   */
  public function getSlicePaymentApplicationStatus($applicationId) {

    return $this->sendApplicationStatusRequest(self::MODENA_APPLICATION_STATUS_URL, $applicationId, self::SCOPE_SLICE);
  }

  /**
   * @param string $applicationId
   *
   * @return string
   * @throws Exception
   */
  public function getCreditPaymentApplicationStatus($applicationId) {

    return $this->sendApplicationStatusRequest(self::MODENA_APPLICATION_STATUS_URL, $applicationId, self::SCOPE_CREDIT);
  }

  /**
   * @param string $applicationId
   *
   * @return string
   * @throws Exception
   */
  public function getBusinessLeasingPaymentApplicationStatus($applicationId) {

    return $this->sendApplicationStatusRequest(self::MODENA_APPLICATION_STATUS_URL, $applicationId, self::SCOPE_BUSINESS_LEASING);
  }

  /**
   * @param string $applicationId
   *
   * @return string
   * @throws Exception
   */
  public function getClickPaymentApplicationStatus($applicationId) {

    return $this->sendApplicationStatusRequest(self::MODENA_APPLICATION_STATUS_URL, $applicationId, self::SCOPE_CLICK);
  }

  /**
   * @param array $postOrRequestData
   *
   * @return ModenaOrderResponse
   */
  public function getOrderResponseFromRequest($postOrRequestData) {

    return new ModenaOrderResponse(
       isset($postOrRequestData['id']) ? sanitize_text_field($postOrRequestData['id'])
          : null, isset($postOrRequestData['orderId']) ? sanitize_text_field($postOrRequestData['orderId']) : null, null);
  }

  /**
   * @throws Exception
   */
  public function getPaymentOptions() {

    $headers = array(
       'Content-Type' => 'application/json'
    );
    $response = $this->sendRequest($this->getServiceURL(self::DIRECT_PAYMENT_OPTION_URL), [], $headers, 'GET');

    return $response->getBody();
  }
}