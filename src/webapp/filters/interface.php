<?php

namespace SpareParts\Webapp;

interface Filter {

  /**
   * Process the incoming HTTP request via direct access and/or manipulation of $_GET, $_POST,
   * $_COOKIE, etc.
   */
  public function incoming();

  /**
   * Process the outgoing HTTP response, as passed as the sole parameter.
   * @param HttpResponse $response The outgoing HTTP response to be filtered
   */
  public function outgoing(HttpResponse $response);
}
