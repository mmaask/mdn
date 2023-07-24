<?php


use Modena\Payment\model\ModenaResponse;

class Modena_Shipping {
  const HTTP_VERSION             = '1.1';
  const MAX_RETRIES              = 5;
  const RETRY_TIMEOUT_IN_SECONDS = 5;
  # DEV ENVIRONMENT PARAMS
  const DEV_API_URL   = 'https://api-dev.modena.ee';
  const DEV_TOKEN_URL = 'https://login-dev.modena.ee/oauth2/token';
  # LIVE ENVIRONMENT PARAMS
  const LIVE_API_URL   = 'https://api.modena.ee';
  const LIVE_TOKEN_URL = 'https://login.modena.ee/oauth2/token';

  const MODENA_SHIPPING_APPLICATION_STATUS = 'modena/api/merchant/shipping/%s/status';

  # ITELLA SHIPPING METHOD
  const ITELLA_LIST_URL    = 'https://monte360.com/itella/index.php?action=displayParcelsList';
  const ITELLA_BARCODE_URL = 'https://monte360.com/itella/index.php?action=createShipment';
  const ITELLA_LABEL_URL   = 'https://monte360.com/itella/index.php?action=getLable&barcode=';
  const ITELLA_SHIPPING    = 'itella_shipping';
  const ITELLA_SHIPPING_ID = 'modena-shipping-itella-terminals';

  const DEFAULT_ARGS = array('httpversion' => self::HTTP_VERSION, 'sslverify' => false, 'redirection' => 0, 'headers' => array('Accept' => 'application/json'), 'cookies' => array(),);

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
   * @param string $applicationId
   *
   * @return string
   * @throws Exception
   */
  public function get_itella_shipping_api_status() {
    return $this->send_shipping_status_request(self::MODENA_SHIPPING_APPLICATION_STATUS, self::ITELLA_SHIPPING);
  }

  /**
   * @param string $applicationUrl
   * @param string $applicationId
   * @param string $scope
   *
   * @return string
   * @throws Exception
   */
  public function send_shipping_status_request($applicationUrl, $scope) {

    $token = $this->get_modena_token($scope);
    $headers = array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $token);
    $response = $this->send_Request($applicationUrl, [], $headers, 'GET');
    $retryCount = 0;
    while (!$response->getBodyValue('status') && $retryCount < self::MAX_RETRIES) {
      $response = $this->send_Request($applicationUrl, [], $headers, 'GET');
      $retryCount++;
      sleep(self::RETRY_TIMEOUT_IN_SECONDS);
    }

    return $response->getBodyValue('status');
  }

  /**
   * @param string $scope
   *
   * @return string
   * @throws Exception
   */
  private function get_modena_token($scope) {

    $headers = array('Content-Type' => 'application/x-www-form-urlencoded', 'Authorization' => 'Basic ' . base64_encode(sprintf('%s:%s', $this->clientId, $this->clientSecret)));
    $data = array('grant_type' => 'client_credentials', 'scope' => $scope);
    $response = $this->send_Request($this->tokenUrl, $data, $headers, 'POST');

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
  private function send_Request($requestUrl, $body, $headers, $requestType = 'GET') {

    $defaultArgs = self::DEFAULT_ARGS;
    $defaultArgs['user-agent'] = __($this->pluginUserAgentData);
    $combinedHeaders = array_replace($defaultArgs['headers'], $headers);
    $args = array_replace(
       $defaultArgs, array('body' => $body, 'headers' => $combinedHeaders, 'method' => $requestType));
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

  public function get_modena_parcel_terminals_http() {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, self::ITELLA_LIST_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set to true if you want to verify SSL certificate
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Set to 2 to check that common name exists and matches the hostname provided
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Sets a timeout for cURL

    $GETrequestResponse = curl_exec($ch);

    if (curl_errno($ch)) {
      error_log('Something went wrong with curling the terminals list. Finding fallback from local file. ' . curl_error($ch) . self::ITELLA_LIST_URL);
    }
    curl_close($ch);

    return $GETrequestResponse;
  }

  public function parse_modena_parcel_terminal_json() {
    $parcelTerminalsJSON = $this->get_modena_parcel_terminals_http();

    if (!$parcelTerminalsJSON) {
      $fallbackFile = MODENA_PLUGIN_PATH . '/shipping/json/smartpostterminals.json';

      if (file_exists($fallbackFile) && is_readable($fallbackFile)) {
        return json_decode(file_get_contents($fallbackFile));
      }
      else {
        error_log('Fallback JSON file not found or not readable.');
      }
    }

    return json_decode($parcelTerminalsJSON);
  }

  public function get_modena_parcel_terminal_list($modena_shipping_method) {
    if ($modena_shipping_method === self::ITELLA_SHIPPING_ID) {
      return $this->parse_modena_parcel_terminal_json()->item;
    } //todo to add new shipping methods later on
  }

  public function get_modena_shipping_barcode_id($modena_shipping_request) {

    $curl = curl_init(self::ITELLA_BARCODE_URL);

    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT, 2); // Add a 5-second wait time for the response
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($modena_shipping_request));

    $POSTrequestResponse = curl_exec($curl);

    if (curl_errno($curl)) {
      error_log('Error in POST response: ' . curl_error($curl));
    }
    else {
      $this->get_modena_barcode_json_parser($POSTrequestResponse);
    }
    curl_close($curl);
  }

  public function get_modena_barcode_json_parser($POSTrequestResponse) {

    if (is_null($POSTrequestResponse)) {
      error_log('Response is NULL. Exiting get_label function.');
      exit;
    }

    $POSTrequestResponse = trim($POSTrequestResponse, '"');
    $array = json_decode($POSTrequestResponse, true);

    if (is_null($array) || !isset($array['item']['barcode'])) {
      error_log('Cannot access barcode_id. Invalid JSON or missing key in array.');

      return Null;
    }

    return $array['item']['barcode'];
  }

  public function save_modena_shipping_label_PDF_in_User($barcode_id) {

    error_log("attempting to download label");

    $pdfUrl = self::ITELLA_LABEL_URL . $barcode_id;
    $tempFileName = $barcode_id . '.pdf';

    $ch = curl_init($pdfUrl);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $pdfContent = curl_exec($ch);

    if ($pdfContent === false) {
      $error = curl_error($ch);
      curl_close($ch);
      error_log("cURL error: " . $error);
    }
    curl_close($ch);

    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename=' . $tempFileName);
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($pdfContent));
    ob_clean();
    flush();
    echo $pdfContent;
    exit;
  }
}