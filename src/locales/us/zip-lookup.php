<?php

namespace SpareParts\Locales\US;

require_once dirname(dirname(dirname(__FILE__))) . '/web-client/web-browser.php';
require_once dirname(__FILE__) . '/address.php';

use \SpareParts\WebClient\WebBrowser;

function lookupZipCode($addr, WebBrowser $client) {
  $vars = array('mode' => 0,
                'tAddress' => $addr['street1'], 'tApt' => $addr['street2'],
                'tCity' => $addr['city'], 'sState' => $addr['state'],
                'zip' => $addr['zip5']);
  $client->followRedirects(true);
  $response = $client->post('https://tools.usps.com/go/ZipLookupAction.action', $vars);
  $errNode = current($client->findMatchingNodes("//p[@id='nonDeliveryMsg']"));
  if (empty($errNode)) $errNode = current(
    $client->findMatchingNodes("//div[@class='noresults-container']/ul/li"));
  $z5node = current($client->findMatchingNodes("//div[@id='result-list']//span[@class='zip']"));
  if ($errNode) {
    $err = $errNode->textContent;
    if (stristr($err, 'We were unable to process your request')) {
      throw new UnableToProcessRequest;
    } else if (stristr($err, "not recognized by the US Postal Service") ||
               stristr($err, "this address wasn't found")) {
      throw new AddressNotRecognized;
    } else {
      throw new ZipCodeLookupError("Did not recognize following error message given by " .
        "USPS website: " . $err);
    }
  } else if ($z5node) {
    $z4node = current($client->findMatchingNodes("//div[@id='result-list']//span[@class='zip4']"));
    $result = new ZipLookupResult(new ZipCode($z5node->textContent, $z4node->textContent));
    if (stristr($response->content, 'Several addresses matched the information you provided')) {
      $result->multipleResults = true;
    }
    return $result;
  } else {
    throw new ZipCodeLookupError("Could not find ZIP Code or error message in data returned " .
      "from USPS website (tools.usps.com) for following address: " . asString($addr));
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
