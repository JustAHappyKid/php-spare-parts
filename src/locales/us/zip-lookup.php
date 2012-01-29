<?php

namespace MyPHPLibs\Locales\US;

require_once dirname(dirname(dirname(__FILE__))) . '/http-client.php';
require_once dirname(dirname(dirname(__FILE__))) . '/us-address.php';

use \HttpClient, \ZipCode;

function lookupZipCode($addr, HttpClient $client) {
  $vars = array('visited' => '1', 'pagenumber' => '0', 'firmname' => '',
                'address1' => $addr['street1'], 'address2' => $addr['street2'],
                'city' => $addr['city'], 'state' => $addr['state'], 'urbanization' => '',
                'zip5' => $addr['zip5']);
  $response = $client->post('http://zip4.usps.com/zip4/zcl_0_results.jsp', $vars);
  $matches = null;
  preg_match_all('/[0-9]{5}-[0-9]{4}/', $response->content, $matches);
  if (count($matches[0]) >= 2) {
    $parts = explode('-', $matches[0][1]);
    return new ZipLookupResult(new ZipCode($parts[0], $parts[1]));
    if (stristr($response->content, 'returned more than one result')) {
      $result->multipleResults = true;
    }
    return $result;
  } else if (stristr($response->content, 'We were unable to process your request')) {
    throw new UnableToProcessRequest;
  } else if (stristr($response->content, 'not recognized by the US Postal Service')) {
    throw new AddressNotRecognized;
  } else {
    throw new ZipCodeLookupError("Could not find ZIP Code or error message in data returned " .
      "from USPS website (zip4.usps.com)");
  }
}

class ZipLookupResult {
  public $zipCode = null, $multipleResults = false;
  public function __construct(ZipCode $zc) {
    $this->zipCode = $zc;
  }
}

class ZipCodeLookupError     extends \Exception {}
class UnableToProcessRequest extends ZipCodeLookupError {}
class AddressNotRecognized   extends ZipCodeLookupError {}
