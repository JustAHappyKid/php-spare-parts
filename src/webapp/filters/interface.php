<?php

namespace SpareParts\Webapp;

interface Filter {

  /**
   * Process the incoming HTTP request via direct access and/or manipulation of $_GET, $_POST,
   * $_COOKIE, etc. Under ordinary circumstances, this method should return null; however, if
   * processing of the request should be halted, then an appropriate HttpResponse object should
   * be returned.
   * @return null|HttpResponse
   */
  public function incoming();

  /**
   * Process the outgoing HTTP response, as passed as the sole parameter.
   * @param HttpResponse $response The outgoing HTTP response to be filtered
   */
  public function outgoing(HttpResponse $response);
}
