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

    public function __construct($socketAddress, $data, $logger, StreamServerNodeInterface $client, $streamOpts = null) {
        $this->outputBuffer = $data;
        $this->client = $client;
        $logger->debug("Opening new OUT connection to " . $socketAddress);
        $logger->debug("With data: " . $data);
        //$opts = stream_context_create(array('socket' =>array('bindto' => $this->binduj.':0')));
        if ($streamOpts === null) {
            $this->socket = stream_socket_client($socketAddress, $errno, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
        } else {
            $opts = stream_context_create($streamOpts);
            $this->socket = stream_socket_client($socketAddress, $errno, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT, $opts);
        }
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
        var_dump($request);
        $headers = $request->getHeaders();
        $host = $request->getHeader("Host");
        $uri = $request->getUri();
        $opts = null;
        $prot = "tcp";
        if (strpos($uri, "https://") === 0) {
            /* ssl not supported */
            $prot = "tls";
            $opts = array(
                "ssl" => array(
                    "allow_self_signed" => true,
                    "verify_peer" => false,
                    'ciphers'=>'RC4-SHA'
                ),
            );
            $pos1 = strpos($uri, "/", 8);
            $host = substr($uri, 8, $pos1 - 8);
            $uri = substr($uri, $pos1);
            
            /*
             * work in progress, so disconnect
             */
            $client->disconnect();
            return;
        }

        if (strpos($uri, "http://") === 0) {
//            $prot = "tls";
//            $opts = array(
//                "ssl" => array(
//                    "allow_self_signed" => true,
//                    "verify_peer" => false,
//                    "ciphers"=>'SEED-SHA'
//                ),
//                "tls" => array(
//                    "allow_self_signed" => true,
//                    "verify_peer" => false,
//                    "ciphers"=>'SEED-SHA'
//                ),
//            );
            $pos1 = strpos($uri, "/", 7);
            $host = substr($uri, 7, $pos1 - 7);
            $uri = substr($uri, $pos1);
        }

        if (strpos($host, ":") === false) {
            //$host .= ":80";
            $socketAddress = $prot . "://" . $host . ":80";
        } else {
            $socketAddress = $prot . "://" . $host;
        }

        //$host = "dlk.pl:80"; //testing purpose

        $headres["connection"][1] = "close"; //supports only single connection mode to backend

        $data = $request->getMethod() . " " . $uri . " HTTP/" . $request->getProtocolVersion() . "\r\n";
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

        $outConnection = new OutConnection($socketAddress, $data, $this->logger, $client, $opts);
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

