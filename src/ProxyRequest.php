<?php

namespace kolorafa\CherryProxy;

/**
 * Description of ProxyRequest
 *
 * @author kolorafa
 */
class ProxyRequest implements ProxyRequestHandlerInterface {
    const ACTION_DEFAULT = 0;
    const ACTION_REJECT = 1;
    const ACTION_ALLOW = 2;

    var $ip;
    var $host;
    var $action = self::ACTION_DEFAULT;
    var $type;
    
    public function __construct($ip, $port, $type) {
        $this->type = $type;
        $this->ip = $ip;
        $this->port = $port;
    }
    
}
