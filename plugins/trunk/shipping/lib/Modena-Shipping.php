<?php


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

  public function getParcelTerminalsHTTPrequest($urltoJSONlist) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $urltoJSONlist);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set to true if you want to verify SSL certificate
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Set to 2 to check that common name exists and matches the hostname provided
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Sets a timeout for cURL

    $GETrequestResponse = curl_exec($ch);

    if (curl_errno($ch)) {
      error_log('Something went wrong with curling the terminals list. Finding fallback from local file. ' . curl_error($ch) . $urltoJSONlist);
    }
    curl_close($ch);
    return $GETrequestResponse;
  }

  public function parseParcelTerminalsJSON($urltoJSONlist) {
    $parcelTerminalsJSON = $this->getParcelTerminalsHTTPrequest($urltoJSONlist);

    if(!$parcelTerminalsJSON) {
      $fallbackFile = MODENA_PLUGIN_PATH . '/shipping/json/smartpostterminals.json';

      if (file_exists($fallbackFile) && is_readable($fallbackFile)) {
        return json_decode(file_get_contents($fallbackFile));
      } else {
        error_log('Fallback JSON file not found or not readable.');
      }
    }
    return json_decode($parcelTerminalsJSON);
  }

  public function getParcelTerminals($modena_shipping_method) {
    return $this->parseParcelTerminalsJSON('https://monte360.com/itella/index.php?action=displayParcelsList')->item;
  }
}