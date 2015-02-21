<?php

require_once("../../../autoload.php");

/**
 * This example provides outputs "I'm everywhere" along with current time (ISO 8601 format) on all URLs
 */
use noFlash\CherryHttp\Server;
use noFlash\Shout\Shout;
use kolorafa\CherryProxy\ProxyRequestInterface;
use kolorafa\CherryProxy\ProxyRequestHandlerInterface;
use kolorafa\CherryProxy\ProxyRequest;

class SimpleAnonymousProxyFilter implements ProxyRequestHandlerInterface {

    public function onRequest(ProxyRequestInterface &$request) {
        if ($request->port != 80) {
            $request->setAction(ProxyRequest::ACTION_ALLOW);
        } else {
            $request->setAction(ProxyRequest::ACTION_REJECT);
        }
    }

}

$proxyFilter = new SimpleAnonymousProxyServer();
$logger = new Shout();
$server = new Server();

$parser = new SocksParser();
$listner = new UniversalListner($server, "127.0.0.1", 8080, false, $logger, "StillThinkingAboutName", $parser);
$parser->addFilter($proxyFilter);

$server->addNode($listner);
$server->run();
