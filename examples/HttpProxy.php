<?php

require_once("../../../autoload.php");
require_once("../src/StreamServerNode.php");
require_once("../src/StreamServerNodeInterface.php");

/**
 * This example provides outputs "I'm everywhere" along with current time (ISO 8601 format) on all URLs
 */
use noFlash\CherryHttp\HttpRequest;
use noFlash\CherryHttp\HttpRequestHandlerInterface;
use noFlash\CherryHttp\Server;
use noFlash\CherryHttp\StreamServerNodeInterface;
use noFlash\Shout\Shout;
use noFlash\CherryHttp\StreamServerNode;

class OutConnection extends StreamServerNode {

    private $client;
    private $request;

    public function __construct($socketAddress, $data, $logger, StreamServerNodeInterface $client, HttpRequest $request) {
        $this->outputBuffer = $data;
        $this->client = $client;
        $this->request = $request;
        $logger->debug("Opening new OUT connection to ".$socketAddress);
        //$opts = stream_context_create(array('socket' =>array('bindto' => $this->binduj.':0')));
        $this->socket = stream_socket_client($socketAddress, $errno, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT/* ,$opts */);
        parent::__construct($this->socket, "", $logger);
    }

    protected function processInputBuffer() {
        
        $this->client->pushData($this->inputBuffer);
        $this->inputBuffer = "";
    }

    public function disconnect($drop = false) {
        $this->client->disconnect();
        parent::disconnect(true);
    }
}

class HttpProxy implements HttpRequestHandlerInterface {

    var $logger;

    public function __construct($logger) {
        $this->logger = $logger;
    }

    public function onRequest(StreamServerNodeInterface $client, HttpRequest $request) {
        $headers = $request->getHeaders();
        $host = $request->getHeader("Host");
        $host = "dlk.pl:80"; //testing purpose

        $headres["connection"][1] = "close"; //supports only single connection mode to backend

        $socketAddress = "tcp://" . $host;
        $data = $request->getMethod() . " " . $request->getUri() . " HTTP/" . $request->getProtocolVersion() . "\r\n";
        $data .= "Host: " . $host . "\r\n";
        foreach ($headers as $key => $header) {
            if (strtolower($key) == "host") {
                continue;
            }
            if (strtolower($key) == "connection") {
                /* backend only support single request */
                $header = "close";
            }
            $data .= $key . ": " . $header . "\r\n";
        }
        $data .= "\r\n";
        
        $outConnection = new OutConnection($socketAddress, $data, $this->logger, $client, $request);
        $this->multiplexer->addNode($outConnection);
    }

    public function getHandledPaths() {
        return array("*");
    }

    /* Server */
    var $multiplexer;

}

$logger = new Shout();
$proxyServer = new HttpProxy($logger);
$server = new Server($logger);
$server->bind("127.0.0.1", 8080);
$server->router->addPathHandler($proxyServer);
$proxyServer->multiplexer = $server;
$proxyServer->logger = $logger;
$server->run();

