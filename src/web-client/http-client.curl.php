<?php

namespace MyPHPLibs\WebClient;

require_once dirname(__FILE__) . '/http-client.php';  # HttpClient

use \Exception, \InvalidArgumentException;

# XXX: This HttpClient implementation is not ready for use, but is an attempt at factoring
#      out the curl-based method of HTTP communication into a separate class, rather than
#      having it all tangled up in the base HttpClient class as a series of if-else statements.
class HttpClientUsingCurl extends HttpClient {

  function dataAccessError($error, $check_connection = 0) {
    $this->state = "Disconnected";
    throw new HttpConnectionError($error);
  }

  function open($arguments) {
    if ($this->state != "Disconnected") $this->raiseError("Already connected");
    if(IsSet($arguments["HostName"]))
      $this->host_name=$arguments["HostName"];
    if(IsSet($arguments["HostPort"]))
      $this->remotePort=$arguments["HostPort"];
    if(IsSet($arguments["ProxyHostName"]))
      $this->proxy_host_name=$arguments["ProxyHostName"];
    if(IsSet($arguments["ProxyHostPort"]))
      $this->proxy_remotePort=$arguments["ProxyHostPort"];
    if(IsSet($arguments["SOCKSHostName"]))
      $this->socks_host_name=$arguments["SOCKSHostName"];
    if(IsSet($arguments["SOCKSHostPort"]))
      $this->socks_remotePort=$arguments["SOCKSHostPort"];
    if(IsSet($arguments["Protocol"]))
      $this->protocol=$arguments["Protocol"];
    switch (strtolower($this->protocol)) {
      case "http":
        $defaultPort = 80;
        break;
      case "https":
        $defaultPort = 443;
        break;
      default:
        $this->raiseError("Invalid connection protocol specified");
    }
    if (strlen($this->proxy_host_name) == 0) {
      if(strlen($this->host_name) == 0)
        $this->raiseError("No hostname specified");
      $host_name = $this->host_name;
      $remotePort = ($this->remotePort ? $this->remotePort : $defaultPort);
      $server_type = 'HTTP';
    } else {
      $host_name=$this->proxy_host_name;
      $remotePort=$this->proxy_remotePort;
      $server_type = 'HTTP proxy';
    }
    $ssl = (strtolower($this->protocol)=="https" && strlen($this->proxy_host_name)==0);
    if ($ssl && strlen($this->socks_host_name))
      $this->raiseError('Establishing SSL connections via SOCKS server not yet supported');
    $this->debug("Connecting to " . $this->host_name);
    $error=(($this->connection=curl_init($this->protocol."://".$this->host_name.($remotePort==$defaultPort ? "" : ":".strval($remotePort))."/")) ? "" : "Could not initialize a CURL session");
    if (strlen($error) == 0) {
      if(IsSet($arguments["SSLCertificateFile"]))
        curl_setopt($this->connection,CURLOPT_SSLCERT,$arguments["SSLCertificateFile"]);
      if(IsSet($arguments["SSLCertificatePassword"]))
        curl_setopt($this->connection,CURLOPT_SSLCERTPASSWD,$arguments["SSLCertificatePassword"]);
      if(IsSet($arguments["SSLKeyFile"]))
        curl_setopt($this->connection,CURLOPT_SSLKEY,$arguments["SSLKeyFile"]);
      if(IsSet($arguments["SSLKeyPassword"]))
        curl_setopt($this->connection,CURLOPT_SSLKEYPASSWD,$arguments["SSLKeyPassword"]);
    }
    if (strlen($error)) { $this->raiseError($error); }
    $this->state = "Connected";
    $this->session = md5(uniqid(""));
  }

  protected function sendRequest(HttpRequest $req) {
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
    $body_length=0;
    $this->request_body="";
    $getBody = true;
    if ($this->requestMethod == "POST") {
      if (isset($req->postParams) && !is_array($req->postParams)) {
        throw new InvalidArgumentException(
          "Expected an array for 'postParams' attribute of request");
      }
      $this->info('Posting the following values...');
      foreach ($req->postParams as $k => $v) {
        $this->info("  $k: " . asString($v));
      }
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
            $body_length+=strlen($headers)+strlen($data)+strlen("\r\n");
          }
        }
        $body_length+=strlen("--".$boundary."--\r\n");
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
          $body_length+=strlen($headers)+$definition["Content-Length"]+strlen("\r\n");
          Next($files);
          $end=(GetType($input=Key($files))!="string");
        }
        $getBody = false;
      } else */ if (isset($req->postParams)) {
        foreach ($req->postParams as $k => $value) {
          if (is_array($value)) {
            foreach ($value as $v) {
              $this->request_body .= urlencode($k) . "=" . urlencode($v);
            }
          } else {
            $this->request_body .= urlencode($k) . "=" . urlencode($value);
          }
          $this->request_body .= "&";
        }
        $this->request_body = substr($this->request_body, 0, -1); # Remove trailing ampersand
        $req->headers["Content-Type"] = "application/x-www-form-urlencoded" .
          (isset($req->charSet) ? ("; charset=" . $req->charSet) : "");
        $getBody = false;
      }
    }

    /* XXX: Re-implement/re-enable support for this...
    if ($getBody && (isset($arguments["Body"]) || isset($arguments["BodyStream"]))) {
      if(IsSet($arguments["Body"]))
        $this->request_body=$arguments["Body"];
      else
      {
        $stream=$arguments["BodyStream"];
        $this->request_body="";
        for($part=0; $part<count($stream); $part++)
        {
          if(IsSet($stream[$part]["Data"]))
            $this->request_body.=$stream[$part]["Data"];
          elseif(IsSet($stream[$part]["File"]))
          {
            if (!($file = @fopen($stream[$part]["File"],"rb"))) {
              $this->raiseError("Could not open upload file " . $stream[$part]["File"]);
            }
            while(!$this->feof($file))
            {
              if (gettype($block = @$this->fread($file,$this->file_buffer_length)) != "string") {
                fclose($file);
                $this->raiseError("Could not read body stream file " . $stream[$part]["File"]);
              }
              $this->request_body .= $block;
            }
            fclose($file);
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

    if ($this->proxy_host_name) {
      $relativeURI = $this->relativeURI;
    } else {
      if (strtolower($this->protocol) == 'http') {
        $defaultPort = 80;
      } else if (strtolower($this->protocol) == 'https') {
        $defaultPort = 443;
      }
      $relativeURI = strtolower($this->protocol) . "://" . $this->hostName .
        (($this->remotePort == 0 || $this->remotePort == $defaultPort) ?
            "" : (":" . $this->remotePort)) . $this->relativeURI;
    }

    $version = (is_array($v = curl_version()) ?
      (isset($v["version"]) ? $v["version"] : "0.0.0") :
      (ereg("^libcurl/([0-9]+\\.[0-9]+\\.[0-9]+)",$v,$m) ? $m[1] : "0.0.0"));
    $curl_version = 100000 * intval($this->tokenize($version,".")) +
                    1000 * intval($this->tokenize(".")) + intval($this->tokenize(""));
    $protocolVersion = ($curl_version < 713002 ? "1.0" : $this->httpProtocolVersion);

    $openingRequestLine = $this->requestMethod . " " . $relativeURI . " HTTP/" . $protocolVersion;
    if ($body_length || ($body_length = strlen($this->request_body))) {
      $req->headers["Content-Length"] = $body_length;
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
    
    if($body_length
    && strlen($this->request_body)==0)
    {
      for($request_body="",$success=1,$part=0;$part<count($post_parts);$part++)
      {
        $request_body.=$post_parts[$part]["HEADERS"].$post_parts[$part]["DATA"];
        if(IsSet($post_parts[$part]["FILENAME"]))
        {
          if (!($file=@fopen($post_parts[$part]["FILENAME"],"rb"))) {
            $this->raiseError("Could not open upload file " . $post_parts[$part]["FILENAME"]);
          }
          while(!$this->feof($file))
          {
            if (GetType($block=@$this->fread($file,$this->file_buffer_length))!="string") {
              $this->raiseError("Could not read upload file");
            }
            $request_body.=$block;
          }
          fclose($file);
          if(!$success)
            break;
        }
        $request_body.="\r\n";
      }
      $request_body.="--".$boundary."--\r\n";
    }
    else
      $request_body=$this->request_body;
    curl_setopt($this->connection,CURLOPT_HEADER,1);
    curl_setopt($this->connection,CURLOPT_RETURNTRANSFER,1);
    if($this->timeout)
      curl_setopt($this->connection,CURLOPT_TIMEOUT,$this->timeout);
    curl_setopt($this->connection,CURLOPT_SSL_VERIFYPEER,0);
    curl_setopt($this->connection,CURLOPT_SSL_VERIFYHOST,0);
    $request = $openingRequestLine . "\r\n" . implode("\r\n", $headers) .
      "\r\n\r\n" . $request_body;
    curl_setopt($this->connection,CURLOPT_CUSTOMREQUEST,$request);
    $this->debug("C " . $request);
    if(!($success=(strlen($this->response=curl_exec($this->connection))!=0)))
    {
      $error = curl_error($this->connection);
      $this->raiseError("Could not execute the request".(strlen($error) ? ": ".$error : ""));
    }

    $this->state = "RequestSent";
  }

  function readLine() {
    $eol = strpos($this->response, "\n", $this->read_response);
    $data = $eol ?
      substr($this->response, $this->read_response, $eol+1-$this->read_response) : "";
    $this->read_response += strlen($data);
  }

  function readBytes($length) {
    $bytes = substr($this->response, $this->read_response,
                    min($length, strlen($this->response) - $this->read_response));
    $this->read_response += strlen($bytes);
    if (strlen($bytes) > 0) $this->debug("Read bytes: " . $bytes);
    return $bytes;
  }

  function endOfInput() {
    return $this->read_response >= strlen($this->response);
  }

  function disconnect() {
    $this->debug("Disconnected from " . $this->host_name);
    curl_close($this->connection);
    $this->response = "";
    $this->state = "Disconnected";
  }
}
