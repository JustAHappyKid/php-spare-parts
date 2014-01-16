<?php

namespace SpareParts\WebClient;

require_once dirname(dirname(__FILE__)) . '/types.php'; # asString
require_once dirname(dirname(__FILE__)) . '/http.php';  # HTTP\Response
require_once dirname(__FILE__) . '/exceptions.php';     # HttpConnectionError, ...

use \Exception, \InvalidArgumentException, \SpareParts\HTTP;

class HttpClient {

  # Override these methods with a sub-class if you want to see the relevant log messages...
  protected function debug($message) {}
  protected function info($message) {}
  protected function notice($message) {}
  protected function warn($message) {}

  const defaultChunkSize = 1024;

  var $hostName = null;
  var $remotePort = 0;
  var $proxyHostName = null;
  var $proxy_remotePort=80;
  var $socks_host_name = '';
  var $socks_remotePort = 1080;

  private $httpProtocolVersion = "1.1";
  public $userAgent = null;
  private $protocol = "http", $requestMethod = "GET", $relativeURI;

  /* XXX: Re-implement authentication...
  var $authentication_mechanism="";
  var $user;
  var $password;
  var $realm;
  var $workstation;
  var $proxy_authentication_mechanism=""; */

  /* XXX: Re-implement proxy support...
  var $proxy_user;
  var $proxy_password;
  var $proxy_realm;
  var $proxy_workstation; */

  var $request="";
  //var $request_headers=array();
  var $request_user;
  var $request_password;
  var $request_realm;
  var $request_workstation;
  var $proxy_request_user;
  var $proxy_request_password;
  var $proxy_request_realm;
  var $proxy_request_workstation;
  //var $request_arguments = array();
  var $timeout=0;
  var $data_timeout=0;
  var $debug=0;
  var $support_cookies=1;
  var $cookies=array();
  var $error="";
  //var $exclude_address="";
  //var $response_status="";
  //var $response_message="";
  var $file_buffer_length=8000;
  var $force_multipart_form_post=0;

  var $use_curl = 0;
  //var $prefer_curl = 0;

  public $currentLocation = null;

  # Configuration attributes
  private $followRedirects = false, $redirectionLimit = 5;

  # private variables
  private $state = "Disconnected";
  private $connection = null;
  private $contentLength, $contentLengthGivenInHeader;
  private $response="";
  private $read_response=0;
  //private $numBytesRead = 0;
  private $hostForRequest = null;
  private $next_token="";
  private $chunked=0;
  private $bytesLeftForChunk = 0;
  private $lastChunkRead = false;
  private $months = array("Jan" => "01", "Feb" => "02", "Mar" => "03", "Apr" => "04",
    "May" => "05", "Jun" => "06", "Jul" => "07", "Aug" => "08", "Sep" => "09",
    "Oct" => "10", "Nov" => "11", "Dec" => "12");
  private $session='';
  private $connection_close=0;

  function __construct() {
    $this->userAgent = 'Mozilla/4.0 (compatible; php-spare-parts HttpClient class; ' .
    'User-Agent string is totally useless)';
  }

  # To be sure no non-existent/renamed/deprecated properties are set...
  function __set($name, $value) {
    throw new InvalidArgumentException("HttpClient has no property '$name'");
  }

  public function followRedirects($follow, $limit = null) {
    $this->followRedirects = $follow;
    if ($limit) $this->redirectionLimit = $limit;
  }

  public function get($url) {
    return $this->makeRequest($url, new HTTP\Request('GET'));
  }

  public function post($url, $postParams, $extraHeaders = array()) {
    $req = new HTTP\Request('POST');
    $req->postParams = $postParams;
    $req->headers = $extraHeaders;
    return $this->makeRequest($url, $req);
  }

  protected function makeRequest($url, HTTP\Request $req, $redirectionLevel = 0) {
    if (strstr($url, ' ')) {
      $this->warn("Escaping space characters in following URL: $url");
      $url = str_replace(' ', '%20', $url);
    }
    $this->populateRequestObj($url, $req);
    if (empty($req->method)) {
      throw new InvalidArgumentException("No request method specified");
    }
    $this->info("Making {$req->method} request to URL $url");
    $this->open($req);
    $this->sendRequest($req);
    try {
      // $body = $this->readReplyBody();
      $response = $this->readResponse();
      $this->close();
      $this->currentLocation = $url;
      //$response = new HttpResponse($body);
      //$response->url = $url;
      //$response->statusCode = $this->response_status;
      if ($response->statusCode != 200) {
        $this->notice('Got non-200 response code: ' . $response->statusCode);
      }
      return $response;
    } catch (HttpClientRedirect $e) {
      if ($redirectionLevel >= $this->redirectionLimit) {
        $this->closeConnection();
        throw new TooManyRedirects("The 'redirectionLimit' of {$this->redirectionLimit} " .
                                   "was exceeded");
      }
      $this->info('Redirecting to ' . $e->location);
      $this->close();
      // $response = $this->get($e->location);
      $response = $this->makeRequest($e->location, new HTTP\Request('GET'), $redirectionLevel + 1);
      return $response;
    }
  }

  private function readLine() {
    $line = "";
    while (substr($line, -1, 1) != "\n") {
      if ($this->feof($this->connection)) {
        $this->dataAccessError("Reached end-of-file (end of data stream) when attempting " .
                               "to read another line");
      }
      $data = $this->fgets($this->connection, 100);
      if ($data === false || strlen($data) == 0) {
        $this->dataAccessError("Failed to read line");
      }
      $line .= $data;
    }
    $trimmedLine = $this->trimNewline($line);
    $this->debug("Read line: $trimmedLine");
    return $trimmedLine;
  }

  private function trimNewline($line) {
    $charsToTrim = substr($line, -2) == "\r\n" ? 2 : 1;
    return substr($line, 0, -$charsToTrim);
  }

  function putLine($line) {
    $this->debug("putLine: $line");
    if (!$this->fputs($this->connection, "$line\r\n")) {
      $this->dataAccessError("it was not possible to send a line to the HTTP server");
    }
  }

  function putData($data) {
    if (strlen($data) > 0) {
      $this->debug("putData: $data");
      if (!$this->fputs($this->connection, $data)) {
        $this->dataAccessError("it was not possible to send data to the HTTP server");
      }
    }
  }

  function flushData() {
    if (!fflush($this->connection)) {
      $this->dataAccessError("it was not possible to send data to the HTTP server");
    }
  }

  private function readChunkSize() {
    $line = $this->readLine();
    if (gettype($line) != 'string') {
      return $this->raiseError("Could not read chunk start: " . $this->error);
    } else if (strlen($line) == 0) {
      $this->httpProtocolError("Got empty-string when attempting to read size of chunk");
    }
    $chunkSize = hexdec($line);
    if ($chunkSize == 0 && $line != '0') {
      $this->httpProtocolError("Received invalid chunk size: $line");
    }
    return $chunkSize;
  }

  private function readBytes($length) {
    if (!is_integer($length)) throw new InvalidArgumentException("\$length must be an integer");
    $bytes = "";
    if ($this->chunked) {
      $remaining = $length;
      while ($remaining > 0) {
        if ($this->bytesLeftForChunk == 0) {
          $chunkSize = $this->readChunkSize();
          if ($chunkSize == 0) {
            $this->lastChunkRead = true;
            break;
          }
          $this->bytesLeftForChunk = $chunkSize;
        }
        $bytesToAskFor = min($this->bytesLeftForChunk, $remaining);
        $chunk = @ $this->fread($this->connection, $bytesToAskFor);
        $numBytesRead = strlen($chunk);
        if ($numBytesRead == 0) {
          $this->dataAccessError("Unable to read data chunk from the server");
        }
        $this->debug("Read bytes: " . $chunk);
        $bytes .= $chunk;
        $this->bytesLeftForChunk -= $numBytesRead;
        $remaining -= $numBytesRead;
        if ($this->bytesLeftForChunk == 0) {
          if ($this->feof($this->connection)) {
            $this->httpProtocolError("Reached end-of-file while attempting to read the " .
                                     "end-of-data-chunk mark from the HTTP server");
          }
          $data = @ $this->fread($this->connection, 2);
          # This is a peculiar case, but sometimes the first 'fread' call only returns one byte,
          # despite the fact there is another one available in the stream...
          if (strlen($data) == 1) $data .= @ $this->fread($this->connection, 1);
          if ($data != "\r\n") {
            $this->warn("Expected to get carriage-return-newline (\\r\\n) sequence for " .
              "end-of-chunk, but got the following content: " . $data);
            $this->httpProtocolError("It was not possible to read carriage-return-newline " .
                                     "sequence expected after data chunk");
          }
        }
      }
    } else if ($length > 0) {
      $bytes = @ $this->fread($this->connection, $length);
      if (strlen($bytes)) {
        $this->debug("Read bytes: " . $bytes);
      } else {
        $this->debug("No bytes read in call to 'fread'");
      }
      if (strlen($bytes) == 0 && !$this->feof($this->connection)) {
        $this->dataAccessError("It was not possible to read data from the HTTP server",
                              $this->connection_close);
      }
    }
    return $bytes;
  }

  private function endOfInput() {
    if ($this->chunked) {
      return $this->lastChunkRead;
    } else {
      return $this->feof($this->connection);
    }
  }

  private function resolve($domain, $server_type) {
    $ip = '';
    if (preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $domain)) {
      $ip = $domain;
    } else {
      $this->debug('Resolving ' . $server_type . ' server domain "' . $domain . '"...');
      $ip = $this->gethostbyname($domain);
      if ($ip == $domain) {
        throw new HostNameResolutionError("Could not resolve hostname \"$domain\"");
      }
      //if (!strcmp($ip = $this->gethostbyname($domain), $domain)) $ip = "";
    }
    /*
    if (strlen($ip) == 0 || (strlen($this->exclude_address)
                            && !strcmp($this->gethostbyname($this->exclude_address), $ip))) {
      $this->raiseError("Could not resolve the host domain \"$domain\"");
    }
    */
    return $ip;
  }

  protected function connect($host_name, $remotePort, $ssl, $server_type = 'HTTP') {
    $domain = $host_name;
    $port = $remotePort;
    $ip = $this->resolve($domain, $server_type);
    if (strlen($this->socks_host_name)) {
      $host_ip = $ip;
      $port = $this->socks_remotePort;
      $host_server_type = $server_type;
      $server_type = 'SOCKS';
      if(strlen($error = $this->resolve($this->socks_host_name, $ip, $server_type)))
        return($error);
    }
    $this->debug('Connecting to ' . $server_type . ' server IP ' . $ip . ' port ' . $port . '...');
    $hostname = $ssl ? ("ssl://" . $domain) : $ip;
    $this->connection = $this->timeout ?
      $this->fsockopen($hostname, $port, $errno, $error, $this->timeout) :
      $this->fsockopen($hostname, $port, $errno, $error);
    //$this->connection = $this->timeout ?
    //  @fsockopen($hostname, $port, $errno, $error, $this->timeout) :
    //  @fsockopen($hostname, $port, $errno, $error);
    if ($this->connection == false) {
      switch ($errno) {
        case -3:
          $this->raiseError("Socket could not be created (-3)");
        case -4:
          throw new HttpConnectionError('DNS lookup on hostname "' . $host_name . '" failed (-4)');
        case -5:
          throw new HttpConnectionError("Connection refused or timed out (-5)");
        case -6:
          $this->raiseError("fdopen() call failed (-6)");
        case -7:
          $this->raiseError("setvbuf() call failed (-7)");
        default:
          throw new HttpConnectionError("Could not connect to host $host_name ($errno)");
      }
    } else {
      if ($this->data_timeout && function_exists("socket_set_timeout")) {
        socket_set_timeout($this->connection, $this->data_timeout, 0);
      }
      if(strlen($this->socks_host_name))
      {
        $this->debug('Connected to the SOCKS server ' . $this->socks_host_name);
        $this->debug('Negotiating the authentication method ...');
        $send_error = 'it was not possible to send data to the SOCKS server';
        $receive_error = 'it was not possible to receive data from the SOCKS server';
        $version = 5;
        $methods = 1;
        $method = 0;
        if(!$this->fputs($this->connection, chr($version).chr($methods).chr($method)))
          $error = $this->dataAccessError($send_error);
        else
        {
          $response = $this->fgets($this->connection, 3);
          if(strlen($response) != 2)
            $error = $this->dataAccessError($receive_error);
          elseif(Ord($response[1]) != $method)
            $error = 'the SOCKS server requires an authentication method that is not yet supported';
          else
          {
            $this->debug('Connecting to ' . $host_server_type . ' server IP ' . $host_ip .
                        ' port ' . $remotePort . '...');
            $command = 1;
            $address_type = 1;
            if(!$this->fputs($this->connection, chr($version).chr($command)."\x00".chr($address_type).pack('Nn', ip2long($host_ip), $remotePort)))
              $error = $this->dataAccessError($send_error);
            else
            {
              $response = $this->fgets($this->connection, 11);
              if(strlen($response) != 10)
                $error = $this->dataAccessError($receive_error);
              else
              {
                $socks_errors = array(
                  "\x00"=>'',
                  "\x01"=>'general SOCKS server failure',
                  "\x02"=>'connection not allowed by ruleset',
                  "\x03"=>'Network unreachable',
                  "\x04"=>'Host unreachable',
                  "\x05"=>'Connection refused',
                  "\x06"=>'TTL expired',
                  "\x07"=>'Command not supported',
                  "\x08"=>'Address type not supported'
                );
                $error_code = $response[1];
                $error = (IsSet($socks_errors[$error_code]) ? $socks_errors[$error_code] : 'unknown');
                if(strlen($error))
                  $error = 'SOCKS error: '.$error;
              }
            }
          }
        }
        if (strlen($error)) {
          $this->fclose($this->connection);
          $this->raiseError($error);
        }
      }
      $this->debug("Connected to $host_name");
      $this->state="Connected";
      return("");
    }
  }

  protected function disconnect() {
    $this->debug("Disconnected from " . $this->hostName);
    $this->fclose($this->connection);
    $this->state = "Disconnected";
  }

  private function populateRequestObj($url, HTTP\Request $req) {
    if (empty($url)) throw new InvalidArgumentException("No URL given");
    $urlParts = parse_url($url);
    if (empty($urlParts["scheme"])) {
      throw new InvalidArgumentException("No scheme (e.g., HTTP or HTTPS) was given");
    }
    if (in_array(strtolower($urlParts["scheme"]), array('http', 'https'))) {
      $req->protocol = $urlParts["scheme"];
      //$arguments["Protocol"] = $urlParts["scheme"];
    } else {
      throw new InvalidArgumentException("Connection scheme '" . $urlParts["scheme"] .
                                         "' is not supported");
    }
    if (empty($urlParts["host"])) { throw new InvalidArgumentException("No host was specified"); }
    $req->hostName = $urlParts["host"];
    //$arguments["HostName"] = $urlParts["host"];
    $req->headers['Host'] = $urlParts["host"] .
      (isset($urlParts["port"]) ? (":" . $urlParts["port"]) : "");

    // TODO: Implement support for HTTP authentication...
    /*if (isset($urlParts["user"])) {
      $arguments["AuthUser"] = urldecode($urlParts["user"]);
      if (empty($urlParts["pass"])) $arguments["AuthPassword"] = "";
    }
    if (isset($urlParts["pass"])) {
      if (!isset($urlParts["user"])) { $arguments["AuthUser"]=""; }
      $arguments["AuthPassword"] = urldecode($urlParts["pass"]);
    }*/

    if (isset($urlParts["port"])) {
      if ($urlParts["port"] !== strval(intval($urlParts["port"]))) {
        throw new InvalidArgumentException("An invalid port was specified");
      }
      //$arguments["HostPort"] = intval($urlParts["port"]);
      $req->remotePort = intval($urlParts["port"]);
    } else {
      $req->remotePort = null;
      //$arguments["HostPort"] = 0;
    }
    //$arguments["RequestURI"] = (isset($urlParts["path"]) ? $urlParts["path"] : "/") .
    $req->relativeURI = (isset($urlParts["path"]) ? $urlParts["path"] : "/") .
      (isset($urlParts["query"]) ? ("?" . $urlParts["query"]) : "");
    if (isset($this->userAgent)) {
      $req->headers["User-Agent"] = $this->userAgent;
    }
  }

  protected function open(HTTP\Request $req) {
    if ($this->state != 'Disconnected') $this->raiseError("Already connected");
    $this->protocol = $req->protocol;
    $this->hostName = $req->hostName;
    $this->remotePort = $req->remotePort;

    /* TODO: Re-implement support for proxying...
    if(IsSet($arguments["ProxyHostName"]))
      $this->proxyHostName=$arguments["ProxyHostName"];
    if(IsSet($arguments["ProxyHostPort"]))
      $this->proxy_remotePort=$arguments["ProxyHostPort"];
    if(IsSet($arguments["SOCKSHostName"]))
      $this->socks_host_name=$arguments["SOCKSHostName"];
    if(IsSet($arguments["SOCKSHostPort"]))
      $this->socks_remotePort=$arguments["SOCKSHostPort"];
    */

    switch (strtolower($this->protocol)) {
      case "http":
        $defaultPort = 80;
        break;
      case "https":
        $defaultPort = 443;
        break;
      default:
        throw new InvalidArgumentException("Invalid connection protocol " .
                                           "({$this->protocol}) specified");
    }
    if (empty($this->proxyHostName)) {
      if (empty($this->hostName)) throw new InvalidArgumentException("No hostname specified");
      $hostName = $this->hostName;
      $remotePort = ($this->remotePort ? $this->remotePort : $defaultPort);
      $server_type = 'HTTP';
    } else {
      $hostName = $this->proxyHostName;
      $remotePort=$this->proxy_remotePort;
      $server_type = 'HTTP proxy';
    }
    $ssl = (strtolower($this->protocol)=="https" && strlen($this->proxyHostName)==0);
    if ($ssl && strlen($this->socks_host_name))
      $this->raiseError('Establishing SSL connections via SOCKS server not yet supported');
    //$this->use_curl=($ssl && $this->prefer_curl && function_exists("curl_init"));
    $this->debug("Connecting to " . $this->hostName);
    $error = "";
    if (strlen($this->proxyHostName) &&
        (IsSet($arguments["SSLCertificateFile"]) || IsSet($arguments["SSLCertificateFile"]))) {
      $error = "establishing SSL connections using certificates or private keys via non-SSL proxies is not supported";
    } else {
      if ($ssl) {
        if(IsSet($arguments["SSLCertificateFile"]))
          $error="establishing SSL connections using certificates is only supported when the cURL extension is enabled";
        elseif(IsSet($arguments["SSLKeyFile"]))
          $error="establishing SSL connections using a private key is only supported when the cURL extension is enabled";
        else {
          $version=explode(".",function_exists("phpversion") ? phpversion() : "3.0.7");
          $php_version=intval($version[0])*1000000+intval($version[1])*1000+intval($version[2]);
          if($php_version<4003000)
            $error="establishing SSL connections requires at least PHP version 4.3.0 or having the cURL extension enabled";
          elseif(!function_exists("extension_loaded")
                || !extension_loaded("openssl"))
            $error="establishing SSL connections requires the OpenSSL extension enabled";
        }
      }
      if (strlen($error) == 0) {
        $error = $this->connect($hostName, $remotePort, $ssl, $server_type);
      }
    }
    if (strlen($error)) { $this->raiseError($error); }
    $this->state = "Connected";
    $this->session = md5(uniqid(""));
  }

  function close() {
    if ($this->state == "Disconnected") $this->raiseError("Already disconnected");
    $this->disconnect();
    $this->state = "Disconnected";
  }

  function pickCookies(&$cookies, $secure) {
    if (isset($this->cookies[$secure])) {
      $now = gmdate("Y-m-d H-i-s");
      for ($domain = 0, reset($this->cookies[$secure]); $domain < count($this->cookies[$secure]); next($this->cookies[$secure]), $domain++) {
        $domain_pattern = key($this->cookies[$secure]);
        $match = strlen($this->hostForRequest) - strlen($domain_pattern);
        if ($match >= 0 && !strcmp($domain_pattern, substr($this->hostForRequest, $match)) &&
            ($match == 0 || $domain_pattern[0] == "." || $this->hostForRequest[$match-1] == ".")) {
          for (reset($this->cookies[$secure][$domain_pattern]), $path_part = 0; $path_part < count($this->cookies[$secure][$domain_pattern]); next($this->cookies[$secure][$domain_pattern]), $path_part++) {
            $path = key($this->cookies[$secure][$domain_pattern]);
            if (strlen($this->relativeURI) >= strlen($path) && substr($this->relativeURI, 0, strlen($path)) == $path) {
              for(Reset($this->cookies[$secure][$domain_pattern][$path]),$cookie=0;$cookie<count($this->cookies[$secure][$domain_pattern][$path]);Next($this->cookies[$secure][$domain_pattern][$path]),$cookie++)
              {
                $cookie_name = key($this->cookies[$secure][$domain_pattern][$path]);
                $expires = $this->cookies[$secure][$domain_pattern][$path][$cookie_name]["expires"];
                if ($expires == "" || strcmp($now, $expires) < 0)
                  $cookies[$cookie_name] = $this->cookies[$secure][$domain_pattern][$path][$cookie_name];
              }
            }
          }
        }
      }
    }
  }

  function GetFileDefinition(&$file,&$definition)
  {
    $name="";
    if(IsSet($file["FileName"]))
      $name=basename($file["FileName"]);
    if(IsSet($file["Name"]))
      $name=$file["Name"];
    if(strlen($name)==0)
      return("it was not specified the file part name");
    if(IsSet($file["Content-Type"]))
    {
      $content_type=$file["Content-Type"];
      $type=$this->tokenize(strtolower($content_type),"/");
      $sub_type=$this->tokenize("");
      switch($type)
      {
        case "text":
        case "image":
        case "audio":
        case "video":
        case "application":
        case "message":
          break;
        case "automatic":
          switch($sub_type)
          {
            case "name":
              switch(GetType($dot=strrpos($name,"."))=="integer" ? strtolower(substr($name,$dot)) : "")
              {
                case ".xls":
                  $content_type="application/excel";
                  break;
                case ".hqx":
                  $content_type="application/macbinhex40";
                  break;
                case ".doc":
                case ".dot":
                case ".wrd":
                  $content_type="application/msword";
                  break;
                case ".pdf":
                  $content_type="application/pdf";
                  break;
                case ".pgp":
                  $content_type="application/pgp";
                  break;
                case ".ps":
                case ".eps":
                case ".ai":
                  $content_type="application/postscript";
                  break;
                case ".ppt":
                  $content_type="application/powerpoint";
                  break;
                case ".rtf":
                  $content_type="application/rtf";
                  break;
                case ".tgz":
                case ".gtar":
                  $content_type="application/x-gtar";
                  break;
                case ".gz":
                  $content_type="application/x-gzip";
                  break;
                case ".php":
                case ".php3":
                  $content_type="application/x-httpd-php";
                  break;
                case ".js":
                  $content_type="application/x-javascript";
                  break;
                case ".ppd":
                case ".psd":
                  $content_type="application/x-photoshop";
                  break;
                case ".swf":
                case ".swc":
                case ".rf":
                  $content_type="application/x-shockwave-flash";
                  break;
                case ".tar":
                  $content_type="application/x-tar";
                  break;
                case ".zip":
                  $content_type="application/zip";
                  break;
                case ".mid":
                case ".midi":
                case ".kar":
                  $content_type="audio/midi";
                  break;
                case ".mp2":
                case ".mp3":
                case ".mpga":
                  $content_type="audio/mpeg";
                  break;
                case ".ra":
                  $content_type="audio/x-realaudio";
                  break;
                case ".wav":
                  $content_type="audio/wav";
                  break;
                case ".bmp":
                  $content_type="image/bitmap";
                  break;
                case ".gif":
                  $content_type="image/gif";
                  break;
                case ".iff":
                  $content_type="image/iff";
                  break;
                case ".jb2":
                  $content_type="image/jb2";
                  break;
                case ".jpg":
                case ".jpe":
                case ".jpeg":
                  $content_type="image/jpeg";
                  break;
                case ".jpx":
                  $content_type="image/jpx";
                  break;
                case ".png":
                  $content_type="image/png";
                  break;
                case ".tif":
                case ".tiff":
                  $content_type="image/tiff";
                  break;
                case ".wbmp":
                  $content_type="image/vnd.wap.wbmp";
                  break;
                case ".xbm":
                  $content_type="image/xbm";
                  break;
                case ".css":
                  $content_type="text/css";
                  break;
                case ".txt":
                  $content_type="text/plain";
                  break;
                case ".htm":
                case ".html":
                  $content_type="text/html";
                  break;
                case ".xml":
                  $content_type="text/xml";
                  break;
                case ".mpg":
                case ".mpe":
                case ".mpeg":
                  $content_type="video/mpeg";
                  break;
                case ".qt":
                case ".mov":
                  $content_type="video/quicktime";
                  break;
                case ".avi":
                  $content_type="video/x-ms-video";
                  break;
                case ".eml":
                  $content_type="message/rfc822";
                  break;
                default:
                  $content_type="application/octet-stream";
                  break;
              }
              break;
            default:
              return($content_type." is not a supported automatic content type detection method");
          }
          break;
        default:
          return($content_type." is not a supported file content type");
      }
    }
    else
      $content_type="application/octet-stream";
    $definition=array(
      "Content-Type"=>$content_type,
      "NAME"=>$name
    );
    if(IsSet($file["FileName"]))
    {
      if(GetType($length=@filesize($file["FileName"]))!="integer")
      {
        $error="it was not possible to determine the length of the file ".$file["FileName"];
        if(IsSet($php_errormsg)
        && strlen($php_errormsg))
          $error.=": ".$php_errormsg;
        if(!file_exists($file["FileName"]))
          $error="it was not possible to access the file ".$file["FileName"];
        return($error);
      }
      $definition["FILENAME"]=$file["FileName"];
      $definition["Content-Length"]=$length;
    }
    elseif(IsSet($file["Data"]))
      $definition["Content-Length"]=strlen($definition["DATA"]=$file["Data"]);
    else
      return("it was not specified a valid file name");
    return("");
  }

  protected function sendRequest(HTTP\Request $req) {
    if ($this->state == "Disconnected") {
      $this->raiseError("Connection was not yet established");
    } else if ($this->state != "Connected") {
      $this->raiseError("Cannot send request in the current connection state, '{$this->state}'");
    }
    $this->requestMethod = $req->method;
    if (isset($req->userAgent)) $this->userAgent = $req->userAgent;
    if (!isset($req->headers["User-Agent"]) && $this->userAgent != null)
      $req->headers["User-Agent"] = $this->userAgent;
    if ($req->referer) {
      $req->headers["Referer"] = $req->referer;
    }
    if (strlen($this->requestMethod) == 0) {
      $this->raiseError("No request method specified");
    }
    $this->relativeURI = $req->relativeURI;
    if (strlen($this->relativeURI) == 0 || substr($this->relativeURI, 0, 1) != "/") {
      $this->raiseError("Invalid request URI given");
    }
    if (empty($req->headers)) $req->headers = array();
    $bodyLength = 0;
    $requestBody = "";
    $getBody = true;
    if ($this->requestMethod == "POST") {
      if (isset($req->postParams) && !is_array($req->postParams)) {
        throw new InvalidArgumentException(
          "Expected an array for 'postParams' attribute of request");
      }
      $this->info('Posting the following values: ' . asString($req->postParams));

      // TODO: Re-implement/re-enable support for POSTing files...
      /* if (isset($arguments["PostFiles"]) ||
          ($this->force_multipart_form_post && isset($arguments["PostValues"]))) {
        $boundary="--".md5(uniqid(time()));
        $this->request_headers["Content-Type"]="multipart/form-data; boundary=".$boundary.(IsSet($arguments["CharSet"]) ? "; charset=".$arguments["CharSet"] : "");
        $post_parts=array();
        if(IsSet($arguments["PostValues"]))
        {
          $values = $arguments["PostValues"];
          for(Reset($values),$value=0;$value<count($values);Next($values),$value++)
          {
            $input=Key($values);
            $headers="--".$boundary."\r\nContent-Disposition: form-data; name=\"".$input."\"\r\n\r\n";
            $data=$values[$input];
            $post_parts[]=array("HEADERS"=>$headers,"DATA"=>$data);
            $bodyLength+=strlen($headers)+strlen($data)+strlen("\r\n");
          }
        }
        $bodyLength+=strlen("--".$boundary."--\r\n");
        $files=(IsSet($arguments["PostFiles"]) ? $arguments["PostFiles"] : array());
        Reset($files);
        $end=(GetType($input=Key($files))!="string");
        for(;!$end;)
        {
          if (strlen($error=$this->GetFileDefinition($files[$input],$definition))) {
            $this->raiseError($error);
          }
          $headers="--".$boundary."\r\nContent-Disposition: form-data; name=\"".$input."\"; filename=\"".$definition["NAME"]."\"\r\nContent-Type: ".$definition["Content-Type"]."\r\n\r\n";
          $part=count($post_parts);
          $post_parts[$part]=array("HEADERS"=>$headers);
          if(IsSet($definition["FILENAME"]))
          {
            $post_parts[$part]["FILENAME"]=$definition["FILENAME"];
            $data="";
          }
          else
            $data=$definition["DATA"];
          $post_parts[$part]["DATA"]=$data;
          $bodyLength+=strlen($headers)+$definition["Content-Length"]+strlen("\r\n");
          Next($files);
          $end=(GetType($input=Key($files))!="string");
        }
        $getBody = false;
      } else */ if (isset($req->postParams)) {
        foreach ($req->postParams as $k => $value) {
          if (is_array($value)) {
            foreach ($value as $v) {
              $requestBody .= urlencode($k) . "=" . urlencode($v);
            }
          } else {
            $requestBody .= urlencode($k) . "=" . urlencode($value);
          }
          $requestBody .= "&";
        }
        $requestBody = substr($requestBody, 0, -1); # Remove trailing ampersand
        $req->headers["Content-Type"] = "application/x-www-form-urlencoded" .
          (isset($req->charSet) ? ("; charset=" . $req->charSet) : "");
        $getBody = false;
      }
    }

    /* XXX: Re-implement/re-enable support for this...
    if ($getBody && (isset($arguments["Body"]) || isset($arguments["BodyStream"]))) {
      if(IsSet($arguments["Body"]))
        $requestBody=$arguments["Body"];
      else
      {
        $stream=$arguments["BodyStream"];
        $requestBody="";
        for($part=0; $part<count($stream); $part++)
        {
          if(IsSet($stream[$part]["Data"]))
            $requestBody.=$stream[$part]["Data"];
          elseif(IsSet($stream[$part]["File"]))
          {
            if (!($file = @fopen($stream[$part]["File"],"rb"))) {
              $this->raiseError("Could not open upload file " . $stream[$part]["File"]);
            }
            while(!$this->feof($file))
            {
              if (gettype($block = @$this->fread($file,$this->file_buffer_length)) != "string") {
                $this->fclose($file);
                $this->raiseError("Could not read body stream file " . $stream[$part]["File"]);
              }
              $requestBody .= $block;
            }
            $this->fclose($file);
          }
          else {
            $this->raiseError("Invalid file or data body stream element at position " . $part);
          }
        }
      }
      if (!isset($this->request_headers["Content-Type"])) {
        $req->headers["Content-Type"] = "application/octet-stream" .
          (isset($req->carSet) ? ("; charset=" . $req->charSet) : "");
      }
    } */

    /* XXX: Need to re-implement authentication support...
    if(IsSet($arguments["ProxyUser"]))
      $this->proxy_request_user=$arguments["ProxyUser"];
    elseif(IsSet($this->proxy_user))
      $this->proxy_request_user=$this->proxy_user;
    if(IsSet($arguments["ProxyPassword"]))
      $this->proxy_request_password=$arguments["ProxyPassword"];
    elseif(IsSet($this->proxy_password))
      $this->proxy_request_password=$this->proxy_password;
    if(IsSet($arguments["ProxyRealm"]))
      $this->proxy_request_realm=$arguments["ProxyRealm"];
    elseif(IsSet($this->proxy_realm))
      $this->proxy_request_realm=$this->proxy_realm;
    if(IsSet($arguments["ProxyWorkstation"]))
      $this->proxy_request_workstation=$arguments["ProxyWorkstation"];
    elseif(IsSet($this->proxy_workstation))
      $this->proxy_request_workstation=$this->proxy_workstation;
    if(IsSet($arguments["AuthUser"]))
      $this->request_user=$arguments["AuthUser"];
    elseif(IsSet($this->user))
      $this->request_user=$this->user;
    if(IsSet($arguments["AuthPassword"]))
      $this->request_password=$arguments["AuthPassword"];
    elseif(IsSet($this->password))
      $this->request_password=$this->password;
    if(IsSet($arguments["AuthRealm"]))
      $this->request_realm=$arguments["AuthRealm"];
    elseif(IsSet($this->realm))
      $this->request_realm=$this->realm;
    if(IsSet($arguments["AuthWorkstation"]))
      $this->request_workstation=$arguments["AuthWorkstation"];
    elseif(IsSet($this->workstation))
      $this->request_workstation=$this->workstation; */

    if (empty($this->proxyHostName)) {
      $relativeURI = $this->relativeURI;
    } else {
      if (strtolower($this->protocol) == 'http') {
        $defaultPort = 80;
      } else if (strtolower($this->protocol) == 'https') {
        $defaultPort = 443;
      } else {
        throw new InvalidArgumentException("Unsupported protocol '{$this->protocol}'");
      }
      $relativeURI = strtolower($this->protocol) . "://" . $this->hostName .
        (($this->remotePort == 0 || $this->remotePort == $defaultPort) ?
            "" : (":" . $this->remotePort)) . $this->relativeURI;
    }
    $openingRequestLine = $this->requestMethod . " " . $relativeURI . " " .
      "HTTP/" . $this->httpProtocolVersion;
    if ($bodyLength || ($bodyLength = strlen($requestBody))) {
      $req->headers["Content-Length"] = $bodyLength;
    }
    $hostHeaderSet = false;
    $headers = array();
    foreach ($req->headers as $headerName => $value) {
      if (is_array($value)) {
        foreach ($value as $v) $headers []= $headerName . ": " . $v;
      } else {
        $headers []= $headerName . ": " . $value;
      }
      if (strtolower($headerName) == "host") {
        $this->hostForRequest = strtolower($value);
        $hostHeaderSet = true;
      }
    }
    if (!$hostHeaderSet) {
      $headers []= "Host: " . $this->hostName;
      $this->hostForRequest = strtolower($this->hostName);
    }
    if (count($this->cookies)) {
      $cookies = array();
      $this->pickCookies($cookies, 0);
      if (strtolower($this->protocol) == "https") $this->pickCookies($cookies, 1);
      if (count($cookies) > 0) {
        $cookieAssignments = array();
        foreach ($cookies as $name => $c) {
          $cookieAssignments []= $name . "=" . $c["value"];
        }
        $cookieHeader = "Cookie: " . implode('; ', $cookieAssignments);
        $headers []= $cookieHeader;
      }
    }

    $this->debug("Putting following request line: {$openingRequestLine}");
    $this->putLine($openingRequestLine);
    $this->debug("Putting following headers...");
    for ($header = 0; $header < count($headers); $header++) {
      $this->debug("  " . $headers[$header]);
      $this->putLine($headers[$header]);
    }
    $this->putLine("");
    if (strlen($requestBody) > 0) {
      $this->debug("Putting following request body: " . $requestBody);
      $this->putData($requestBody);
    } else if ($bodyLength) {
      for ($part = 0; $part < count($post_parts); $part++) {
        $this->putData($post_parts[$part]["HEADERS"]);
        $this->putData($post_parts[$part]["DATA"]);
        if (isset($post_parts[$part]["FILENAME"])) {
          if (!($file = @fopen($post_parts[$part]["FILENAME"],"rb"))) {
            $this->raiseError("Could not open upload file " . $post_parts[$part]["FILENAME"]);
          }
          while (!$this->feof($file)) {
            if (!is_string($block = @$this->fread($file, $this->file_buffer_length))) {
              $this->raiseError("Could not read upload file");
            }
            $this->putData($block);
          }
          $this->fclose($file);
        }
        $this->putLine("");
      }
      $this->putLine("--" . $boundary . "--");
    }
    $this->flushData();

    $this->state = "RequestSent";
  }

  public function setCookie($name, $value, $expires = "", $path = "/", $domain = "",
                            $secure = false, $verbatim = false) {
    if (strlen(trim($name)) == 0) $this->raiseError("No cookie name was given");
    if (strlen(trim($path)) == 0) $this->raiseError("No path for cookie scope was given");
    if ($path[0] != "/") $this->raiseError("Invalid path given for cookie '$name'");
    if ($domain == "" || !strpos($domain, ".", $domain[0] == "." ? 1 : 0))
      $this->raiseError("Invalid domain given for cookie '$name'");
    $domain = strtolower($domain);
    if (!strcmp($domain[0], ".")) $domain = substr($domain, 1);
    if(!$verbatim) {
      $name = $this->cookieEncode($name, 1);
      $value = $this->cookieEncode($value, 0);
    }
    //$secure=intval($secure);
    $this->cookies[$secure][$domain][$path][$name] =
      array("name" => $name, "value" => $value, "domain" => $domain, "path" => $path,
            "expires" => $expires, "secure" => $secure);
  }

  function readResponseStatusAndHeaders() {
    $headers = array();
    switch ($this->state) {
      case "Disconnected":
        $this->raiseError("Connection was not yet established");
      case "Connected":
        $this->raiseError("Request was not sent");
      case "RequestSent":
        break;
      default:
        $this->raiseError("Cannot get request headers in the current connection " .
                          "state, '{$this->state}'");
    }
    $this->contentLength = $this->read_response = $this->bytesLeftForChunk = 0;
    $this->contentLengthGivenInHeader = $this->lastChunkRead = false;
    $this->chunked = $chunked = 0;
    $this->connection_close = 0;

    $line = $this->readLine();
    $statusLineRegex = "@^http/[0-9]+\\.[0-9]+[ \t]+([0-9]+)[ \t]*(.*)\$@";
    if (!preg_match($statusLineRegex, strtolower($line), $matches)) {
      $this->raiseError("Received an unexpected HTTP response status: $line");
    }
    $statusCode = (int) $matches[1];
    $XXXstatusMessage = $matches[2];

    for ($line = $this->readLine(); $line != ''; $line = $this->readLine()) {
      $parts = array_map('trim', explode(':', $line, 2));
      if (count($parts) != 2) $this->httpProtocolError("Read illegal header line: $line");
      $name = strtolower($parts[0]);
      $value = $parts[1];
      if (isset($headers[$name])) {
        if (!is_array($headers[$name])) {
          $headers[$name] = array($headers[$name]);
        }
        $headers[$name] []= $value;
      } else {
        $headers[$name] = $value;
      }
      switch ($name) {
        case "content-length":
          $this->contentLength=intval($headers[$name]);
          $this->contentLengthGivenInHeader = true;
          break;
        case "transfer-encoding":
          $encoding = $this->tokenize($value, "; \t");
          if (!$this->use_curl && $encoding == "chunked") $chunked = 1;
          break;
        case "set-cookie":
          if($this->support_cookies)
          {
            if(GetType($headers[$name])=="array")
              $cookie_headers=$headers[$name];
            else
              $cookie_headers=array($headers[$name]);
            for($cookie=0;$cookie<count($cookie_headers);$cookie++)
            {
              $cookie_name=trim($this->tokenize($cookie_headers[$cookie],"="));
              $cookie_value=$this->tokenize(";");
              $domain=$this->hostForRequest;
              $path="/";
              $expires="";
              $secure=0;
              while(($name=trim(UrlDecode($this->tokenize("="))))!="")
              {
                $value=UrlDecode($this->tokenize(";"));
                switch($name)
                {
                  case "domain":
                    $domain=$value;
                    break;
                  case "path":
                    $path=$value;
                    break;
                  case "expires":
                    $pattern = '/^((Mon|Monday|Tue|Tuesday|Wed|Wednesday|Thu|Thursday|' .
                      'Fri|Friday|Sat|Saturday|Sun|Sunday), )?([0-9]{2})\\-' .
                      '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\\-([0-9]{2,4}) ' .
                      '([0-9]{2})\\:([0-9]{2})\\:([0-9]{2}) GMT$/';
                    if (preg_match($pattern, $value, $matches)) {
                      $year = intval($matches[5]);
                      if ($year < 1900) $year += ($year < 70 ? 2000 : 1900);
                      $expires = "$year-" . $this->months[$matches[4]] . "-" . $matches[3] .
                        " " . $matches[6] . ":" . $matches[7] . ":" . $matches[8];
                    }
                    break;
                  case "secure":
                    $secure=1;
                    break;
                }
              }
              if(strlen($this->setCookie($cookie_name, $cookie_value, $expires, $path , $domain, $secure, 1)))
                $this->error="";
            }
          }
          break;
        case "connection":
          $this->connection_close=!strcmp(strtolower($value),"close");
          break;
      }
    }
    $this->state = "GotReplyHeaders";
    $this->chunked = $chunked;
    if ($this->contentLengthGivenInHeader) $this->connection_close = 0;
    return array($statusCode, $headers);
  }

  private function redirect($headers) {
    if (!$this->followRedirects) {
      $this->info("Not following 30x redirect because 'followRedirects' flag is off");
    } else {
      if (!isset($headers['location'])) {
        $this->raiseError("Server gave a 30x-redirect response without a location header");
      }
      $givenLocation = is_array($headers['location']) ?
        $headers['location'][0] : $headers['location'];
      if (strlen($givenLocation) == 0) {
        $this->raiseError("No value given in 'Location' header");
      }
      $this->info("Received 30x response with following value for 'Location': $givenLocation");
      $parts = parse_url($givenLocation);
      $location = isset($parts['scheme']) ?
        $givenLocation : $this->fullUrlFromPath($givenLocation);
      throw new HttpClientRedirect($location);
    }
  }

  private function fullUrlFromPath($path) {
    $absPath = null;
    if ($path[0] == '/') {
      $absPath = $path;
    } else {
      $lastSlashPos = strrpos($this->relativeURI, '/');
      if ($lastSlashPos === false) {
        throw new Exception('\$this->relativeURI contained no forward slash!');
      }
      $absPath = ($lastSlashPos > 1 ? substr($this->relativeURI, 0, $lastSlashPos) : '') .
        '/' . $path;
    }
    return $this->protocol . '://' . $this->hostName .
      ($this->remotePort ? ':' . $this->remotePort : '') . $absPath;
  }

/* XXX: Need to re-enable/re-implement authentication...
  function authenticate(&$headers, $proxy, &$proxy_authorization, &$user, &$password,
                        &$realm, &$workstation) {
    if($proxy)
    {
      $authenticate_header="proxy-authenticate";
      $authorization_header="Proxy-Authorization";
      $authenticate_status="407";
      $authentication_mechanism=$this->proxy_authentication_mechanism;
    }
    else
    {
      $authenticate_header="www-authenticate";
      $authorization_header="Authorization";
      $authenticate_status="401";
      $authentication_mechanism=$this->authentication_mechanism;
    }
    if(IsSet($headers[$authenticate_header]))
    {
      if(function_exists("class_exists")
      && !class_exists("sasl_client_class"))
        return($this->raiseError("the SASL client class needs to be loaded to be able to authenticate".($proxy ? " with the proxy server" : "")." and access this site"));
      if(GetType($headers[$authenticate_header])=="array")
        $authenticate=$headers[$authenticate_header];
      else
        $authenticate=array($headers[$authenticate_header]);
      for($response="", $mechanisms=array(),$m=0;$m<count($authenticate);$m++)
      {
        $mechanism=$this->tokenize($authenticate[$m]," ");
        $response=$this->tokenize("");
        if(strlen($authentication_mechanism))
        {
          if(!strcmp($authentication_mechanism,$mechanism))
          {
            $mechanisms[]=$mechanism;
            break;
          }
        }
        else
          $mechanisms[]=$mechanism;
      }
      $sasl=new sasl_client_class;
      if(IsSet($user))
        $sasl->SetCredential("user",$user);
      if(IsSet($password))
        $sasl->SetCredential("password",$password);
      if(IsSet($realm))
        $sasl->SetCredential("realm",$realm);
      if(IsSet($workstation))
        $sasl->SetCredential("workstation",$workstation);
      $sasl->SetCredential("uri",$this->relativeURI);
      $sasl->SetCredential("method",$this->requestMethod);
      $sasl->SetCredential("session",$this->session);
      do
      {
        $status=$sasl->Start($mechanisms,$message,$interactions);
      }
      while($status==SASL_INTERACT);
      switch($status)
      {
        case SASL_CONTINUE:
          break;
        case SASL_NOMECH:
          return($this->raiseError(($proxy ? "proxy " : "")."authentication error: ".(strlen($authentication_mechanism) ? "authentication mechanism ".$authentication_mechanism." may not be used: " : "").$sasl->error));
        default:
          return($this->raiseError("Could not start the SASL ".($proxy ? "proxy " : "")."authentication client: ".$sasl->error));
      }
      for(;;)
      {
        if(strlen($error=$this->ReadReplyBody($body,$this->file_buffer_length)))
          return($error);
        if(strlen($body)==0)
          break;
      }
      $authorization_value=$sasl->mechanism.(IsSet($message) ? " ".($sasl->encode_response ? base64_encode($message) : $message) : "");
      $request_arguments=$this->request_arguments;
      $arguments=$request_arguments;
      $arguments["Headers"][$authorization_header]=$authorization_value;
      if(!$proxy
      && strlen($proxy_authorization))
        $arguments["Headers"]["Proxy-Authorization"]=$proxy_authorization;
      if(strlen($error=$this->Close())
      || strlen($error=$this->Open($arguments)))
        return($this->raiseError($error));
      $authenticated=0;
      if(IsSet($message))
      {
        if(strlen($error=$this->SendRequest($arguments))
        || strlen($error=$this->ReadReplyHeadersResponse($headers)))
          return($this->raiseError($error));
        if(!IsSet($headers[$authenticate_header]))
          $authenticate=array();
        elseif(GetType($headers[$authenticate_header])=="array")
          $authenticate=$headers[$authenticate_header];
        else
          $authenticate=array($headers[$authenticate_header]);
        for($mechanism=0;$mechanism<count($authenticate);$mechanism++)
        {
          if(!strcmp($this->tokenize($authenticate[$mechanism]," "),$sasl->mechanism))
          {
            $response=$this->tokenize("");
            break;
          }
        }
        switch($this->response_status)
        {
          case $authenticate_status:
            break;
          case "301":
          case "302":
          case "303":
          case "307":
            return($this->redirect($headers));
          default:
            if(intval($this->response_status/100)==2)
            {
              if($proxy)
                $proxy_authorization=$authorization_value;
              $authenticated=1;
              break;
            }
            if($proxy
            && !strcmp($this->response_status,"401"))
            {
              $proxy_authorization=$authorization_value;
              $authenticated=1;
              break;
            }
            return($this->raiseError(($proxy ? "proxy " : "")."authentication error: ".$this->response_status." ".$this->response_message));
        }
      }
      for(;!$authenticated;)
      {
        do
        {
          $status=$sasl->Step($response,$message,$interactions);
        }
        while($status==SASL_INTERACT);
        switch($status)
        {
          case SASL_CONTINUE:
            $authorization_value=$sasl->mechanism.(IsSet($message) ? " ".($sasl->encode_response ? base64_encode($message) : $message) : "");
            $arguments=$request_arguments;
            $arguments["Headers"][$authorization_header]=$authorization_value;
            if(!$proxy
            && strlen($proxy_authorization))
              $arguments["Headers"]["Proxy-Authorization"]=$proxy_authorization;
            if(strlen($error=$this->SendRequest($arguments))
            || strlen($error=$this->ReadReplyHeadersResponse($headers)))
              return($this->raiseError($error));
            switch($this->response_status)
            {
              case $authenticate_status:
                if(GetType($headers[$authenticate_header])=="array")
                  $authenticate=$headers[$authenticate_header];
                else
                  $authenticate=array($headers[$authenticate_header]);
                for($response="",$mechanism=0;$mechanism<count($authenticate);$mechanism++)
                {
                  if(!strcmp($this->tokenize($authenticate[$mechanism]," "),$sasl->mechanism))
                  {
                    $response=$this->tokenize("");
                    break;
                  }
                }
                for(;;)
                {
                  if(strlen($error=$this->ReadReplyBody($body,$this->file_buffer_length)))
                    return($error);
                  if(strlen($body)==0)
                    break;
                }
                $this->state="Connected";
                break;
              case "301":
              case "302":
              case "303":
              case "307":
                return($this->redirect($headers));
              default:
                if(intval($this->response_status/100)==2)
                {
                  if($proxy)
                    $proxy_authorization=$authorization_value;
                  $authenticated=1;
                  break;
                }
                if($proxy
                && !strcmp($this->response_status,"401"))
                {
                  $proxy_authorization=$authorization_value;
                  $authenticated=1;
                  break;
                }
                return($this->raiseError(($proxy ? "proxy " : "")."authentication error: ".$this->response_status." ".$this->response_message));
            }
            break;
          default:
            return($this->raiseError("Could not process the SASL ".($proxy ? "proxy " : "")."authentication step: ".$sasl->error));
        }
      }
    }
    return("");
  }
*/

  function readAndHandleResponseHeader() {
    list($statusCode, $headers) = $this->readResponseStatusAndHeaders();
    $proxy_authorization = "";
    // while (!strcmp($this->response_status, "100")) {
    while ($statusCode == 100) {
      $this->state = "RequestSent";
      // $this->readResponseStatusAndHeaders($headers);
      list($statusCode, $headers) = $this->readResponseStatusAndHeaders();
    }
    if (in_array($statusCode, array(301, 302, 303, 307))) {
      $this->redirect($headers);
    } else if ($statusCode == 407) {
      $this->raiseError("HTTP authentication not yet supported");
      /*$this->authenticate($headers, 1, $proxy_authorization, $this->proxy_request_user,
          $this->proxy_request_password, $this->proxy_request_realm,
          $this->proxy_request_workstation)))
        if(strcmp($this->response_status,"401"))
          return("");*/
    } else if ($statusCode == 401) {
      $this->raiseError("HTTP authentication not yet supported");
      /*return($this->authenticate($headers, 0, $proxy_authorization,
          $this->request_user, $this->request_password, $this->request_realm,
          $this->request_workstation));*/
    }
    return array($statusCode, $headers);
  }

  protected function readResponse() {
    $response = new HTTP\Response;
    switch ($this->state) {
      case "Disconnected":
        $this->raiseError("Connection was not yet established");
      case "Connected":
        $this->raiseError("Request was not yet sent");
      case "RequestSent":
        list($response->statusCode, $response->headers) = $this->readAndHandleResponseHeader();
        break;
      case "GotReplyHeaders":
        break;
      default:
        $this->raiseError("Can not get request body in the current connection state");
    }
    $body = "";
    $chunk = "";
    $numBytesRead = 0;
    do {
      $chunkSize = self::defaultChunkSize;
      if ($this->contentLengthGivenInHeader) {
        $chunkSize = min($this->contentLength - $numBytesRead, $chunkSize);
      }
      $chunk = $this->readBytes($chunkSize);
      $numBytesRead += strlen($chunk);
      if ($chunkSize > 0 && !$this->endOfInput() && $chunk == "") {
        $this->raiseError("Could not read the reply body");
      }
      $body .= $chunk;
    } while (strlen($chunk) > 0 && $this->lastChunkRead == false &&
            ($this->contentLength > $numBytesRead || !$this->contentLengthGivenInHeader));
    // return $body;
    $response->content = $body;
    return $response;
  }

  private function cookieEncode($value, $name) {
    return($name ? str_replace("=", "%25", $value) : str_replace(";", "%3B", $value));
  }

  function savePersistentCookies(&$cookies, $domain = '', $secureOnly = 0) {
    $this->saveCookies($cookies, $domain, $secureOnly, 1);
  }

  function getPersistentCookies(&$cookies, $domain='', $secure_only = 0) {
    $this->savePersistentCookies($cookies, $domain, $secure_only);
  }

  function saveCookies(&$cookies, $domain='', $secure_only=0, $persistent_only=0) {
    $now=gmdate("Y-m-d H-i-s");
    $cookies=array();
    for($secure_cookies=0,Reset($this->cookies);$secure_cookies<count($this->cookies);Next($this->cookies),$secure_cookies++)
    {
      $secure=Key($this->cookies);
      if(!$secure_only
      || $secure)
      {
        for($cookie_domain=0,Reset($this->cookies[$secure]);$cookie_domain<count($this->cookies[$secure]);Next($this->cookies[$secure]),$cookie_domain++)
        {
          $domain_pattern=Key($this->cookies[$secure]);
          $match=strlen($domain)-strlen($domain_pattern);
          if(strlen($domain)==0
          || ($match>=0
          && !strcmp($domain_pattern,substr($domain,$match))
          && ($match==0
          || $domain_pattern[0]=="."
          || $domain[$match-1]==".")))
          {
            for(Reset($this->cookies[$secure][$domain_pattern]),$path_part=0;$path_part<count($this->cookies[$secure][$domain_pattern]);Next($this->cookies[$secure][$domain_pattern]),$path_part++)
            {
              $path=Key($this->cookies[$secure][$domain_pattern]);
              for(Reset($this->cookies[$secure][$domain_pattern][$path]),$cookie=0;$cookie<count($this->cookies[$secure][$domain_pattern][$path]);Next($this->cookies[$secure][$domain_pattern][$path]),$cookie++)
              {
                $cookie_name=Key($this->cookies[$secure][$domain_pattern][$path]);
                $expires=$this->cookies[$secure][$domain_pattern][$path][$cookie_name]["expires"];
                if((!$persistent_only
                && strlen($expires)==0)
                || (strlen($expires)
                && strcmp($now,$expires)<0))
                  $cookies[$secure][$domain_pattern][$path][$cookie_name]=$this->cookies[$secure][$domain_pattern][$path][$cookie_name];
              }
            }
          }
        }
      }
    }
  }

  function restoreCookies($cookies, $clear=1) {
    $new_cookies=($clear ? array() : $this->cookies);
    for($secure_cookies=0, Reset($cookies); $secure_cookies<count($cookies); Next($cookies), $secure_cookies++)
    {
      $secure=Key($cookies);
      if(GetType($secure)!="integer")
        return($this->raiseError("invalid cookie secure value type (".serialize($secure).")"));
      for($cookie_domain=0,Reset($cookies[$secure]);$cookie_domain<count($cookies[$secure]);Next($cookies[$secure]),$cookie_domain++)
      {
        $domain_pattern=Key($cookies[$secure]);
        if(GetType($domain_pattern)!="string")
          return($this->raiseError("invalid cookie domain value type (".serialize($domain_pattern).")"));
        for(Reset($cookies[$secure][$domain_pattern]),$path_part=0;$path_part<count($cookies[$secure][$domain_pattern]);Next($cookies[$secure][$domain_pattern]),$path_part++)
        {
          $path=Key($cookies[$secure][$domain_pattern]);
          if(GetType($path)!="string"
          || strcmp(substr($path, 0, 1), "/"))
            return($this->raiseError("invalid cookie path value type (".serialize($path).")"));
          for(Reset($cookies[$secure][$domain_pattern][$path]),$cookie=0;$cookie<count($cookies[$secure][$domain_pattern][$path]);Next($cookies[$secure][$domain_pattern][$path]),$cookie++)
          {
            $cookie_name = key($cookies[$secure][$domain_pattern][$path]);
            $expires = $cookies[$secure][$domain_pattern][$path][$cookie_name]["expires"];
            $value = $cookies[$secure][$domain_pattern][$path][$cookie_name]["value"];
            $expirationPattern = '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/';
            if (gettype($expires) != "string" ||
                (strlen($expires) &&
                !preg_match($expirationPattern, $expires))) {
              $this->raiseError("invalid cookie expiry value type (" . serialize($expires) . ")");
            }
            $new_cookies[$secure][$domain_pattern][$path][$cookie_name]=array(
              "name"=>$cookie_name,
              "value"=>$value,
              "domain"=>$domain_pattern,
              "path"=>$path,
              "expires"=>$expires,
              "secure"=>$secure
            );
          }
        }
      }
    }
    $this->cookies=$new_cookies;
    return("");
  }

  private function tokenize($string, $separator = "") {
    if (!strcmp($separator,"")) {
      $separator = $string;
      $string = $this->next_token;
    }
    for ($character=0; $character<strlen($separator); $character++) {
      if (gettype($position=strpos($string,$separator[$character])) == "integer")
        $found = (isset($found) ? min($found, $position) : $position);
    }
    if (isset($found)) {
      $this->next_token = substr($string, $found + 1);
      return substr($string, 0, $found);
    } else {
      $this->next_token = "";
      return $string;
    }
  }

  private function raiseError($msg) {
    if (empty($msg) || trim($msg) == "") {
      throw new Exception("Empty error message passed to HttpClient->raiseError");
    }
    $this->error = $msg;
    throw new Exception($msg);
  }

  private function dataAccessError($error, $check_connection = 0) {
    if (function_exists("socket_get_status")) {
      $status = $this->socket_get_status($this->connection);
      if ($status["timed_out"]) {
        $error .= ": data access time out";
      }	else if ($status["eof"]) {
        /*if ($check_connection) {
          $error = "";
        } else {
          $error .= ": the server disconnected";
        }*/
        $error .= ": status is 'eof'";
      }
    }
    $this->state = "Disconnected";
    throw new HttpConnectionError($error);
  }

  private function httpProtocolError($msg) {
    $this->closeConnection();
    throw new HttpProtocolError($msg);
  }

  private function closeConnection() {
    $this->fclose($this->connection);
    $this->state = "Disconnected";
  }

  # NOTE: These low-level PHP functions (fsockopen, fread, feof, etc) have been wrapped,
  # below, to allow us to better test this HttpClient class (by sub-classing and stubbing out
  # the methods that would actually require Internet communication).

  protected function gethostbyname($domain) {
    return @gethostbyname($domain);
  }

  protected function fsockopen($hostname, $port, &$errno, &$errstr, $timeout = null) {
    return $timeout ?
      @fsockopen($hostname, $port, $errno, $error, $timeout) :
      @fsockopen($hostname, $port, $errno, $error);
  }

  protected function fputs($conn, $data) {
    return fputs($conn, $data);
  }

  protected function fgets($conn, $length) {
    return @fgets($conn, $length);
  }

  protected function fread($conn, $length) {
    return @fread($this->connection, $length);
  }

  protected function feof($conn) {
    return feof($conn);
  }

  protected function fclose($handle) {
    return fclose($handle);
  }

  protected function socket_get_status($conn) {
    return socket_get_status($conn);
  }
}

/*class HttpResponse extends HTTP\Response {
//  public $url, $statusCode, $headers, $content;
  function __construct($content = null) {
    //$this->statusCode = $statusCode;
    $this->content = $content;
  }
}*/
