<?php

namespace TikTok\Core\Libraries;

use TikTok\Core\Exceptions\TikTokException;


class Request {

  public $config;

  public $data = false;

  public function __construct ($config) {
    $this->config = $config;
  }

  public function call ($endpoint, $customHeaders = []) {

    // Grab headers that will be used based on endpoint
    $headers = $this->getHeaders($endpoint);

	  // Initiate CURL
	  $ch = curl_init();

    $timeout = isset($this->config->timeout) ? $this->config->timeout : 30;

    // Set the URL
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    // Proxy setup:
    if (isset($this->config->proxy)) {

      // Make sure address is set.
      if (isset($this->config->proxy['address'])) {

        // Check for protocol setting
        if (isset($this->config->proxy['protocol'])) {

          // Auth should be: username:password
          curl_setopt(
            $ch,
            CURLOPT_PROXYTYPE,
            (
              $this->config->proxy['protocol'] === 'https' ?
              CURLPROXY_HTTPS :
              CURLPROXY_HTTP
            )
          );
        } else {
          curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        }

        // Address should be 0.0.0.0:0000
        curl_setopt($ch, CURLOPT_PROXY, $this->config->proxy['address']);

        // Check if auth is provided
        if (isset($this->config->proxy['auth'])) {

          // Auth should be: username:password
          curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->config->proxy['auth']);
        }
      }
    }

    // Set other headers
	  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, $customHeaders));
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

    // Get the response
    $response = curl_exec($ch);

    // Curl info
    $info = curl_getinfo($ch);

    // Close CURL
    curl_close ($ch);

    if ($info['http_code'] !== 200) {
      $this->data = (object) [
        'error' => true,
        'error_message' => 'Status code ' . $info['http_code'] . ' was returned'
      ];

      return $this;
    }

    if ($this->endpointType($endpoint) === 'm') {
      try {
        $response = json_decode($response);
      } catch (Exception $e) {

        $this->data = (object) [
          'error' => true,
          'error_message' => 'Unable to decode JSON data'
        ];

        return $this;
      }
    }

    $this->data = $response;
    return $this;
	}

  public function response () {
    return $this->data;
  }

  public function extract () {
    if (preg_match_all('#\<script id=\"__NEXT_DATA__\" type=\"application/json\" crossorigin=\"anonymous\">(.*?)\<\/script\>#', $this->data, $out)) {
      return json_decode($out[1][0], true, 512, JSON_BIGINT_AS_STRING);
    }

    return null;
  }

  private function endpointType ($endpoint) {
    if (strpos($endpoint, 'www.') !== false) return 'web';
    return 'm';
  }

  private function getHeaders($endpoint) {
    $headers = [
      'web' => [
        'Authority'       => 'www.tiktok.com',
        'Upgrade-Insecure-Requests' => '1',
        'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36',
        'Sec-Fetch-Dest'  => 'document',
        'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
        'Sec-Fetch-Site'  => 'none',
        'Sec-Fetch-Mode'  => 'navigate',
        'Sec-Fetch-User'  => '?1',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Referer'         => 'https://www.tiktok.com/'
      ],
      'm'  => [
        'Accept' => 'application/json, text/plain, */*',
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.113 Safari/537.36',
        'Origin' => 'https://www.tiktok.com',
        'Referer' => 'https://www.tiktok.com/',
        'Accept-Language' => 'en-US,en;q=0.9',
      ]
    ];

    return $this->formatHeaders($headers[$this->endpointType($endpoint)]);
  }

  private function formatHeaders($headers = array()) {
    $res = [];
    foreach ($headers as $key => $header) $res[] = $key . ': ' . $header;
    return $res;
  }
}