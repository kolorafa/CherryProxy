<?php
/*
 * @author Grzegorz Zdanowski <grzegorz129@gmail.com>
 *
 * @project cherryhttp
 * @package 
 */

namespace kolorafa\CherryProxy;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use noFlash\CherryHttp;

/**
 * Default HTTP listener (server) node representation.
 *
 * @package noFlash\CherryHttp
 */
class HttpListenerNode extends StreamServerNode
{
    /** @var array Listener doesn't have any events [yet] */
    public $subscribedEvents = array();

    /** @var Server Server object to which this listener belongs */
    protected $server;

    /** @var Class to create that will represents new client handler */
    protected $clientClassName;
    
    /** @var Extra object to pass to every new connection */
    protected $extraData;
    
    /**
     * Binds to given IP and port.
     * Note: SSL support is not implemented right now.
     *
     * @param Server $server
     * @param string $ip IP address to listen on
     * @param integer $port Port to listen on. If you pass 0 port random free port will be assigned by OS
     * @param bool $ssl
     * @param LoggerInterface $logger
     *
     * @throws ServerException
     */
    public function __construct(
        Server &$server,
        $ip = "0.0.0.0",
        $port = 8080,
        $ssl = false,
        LoggerInterface &$logger = null,
        $clientClassName = "HttpRequest",
        $extraData = null    
    ) {
        $this->server = &$server;
        $this->clientClassName = $clientClassName;
        $this->extraData = $extraData;
        
        if ($logger === null) {
            $this->logger = new NullLogger();

        } else {
            $this->logger = &$logger;
        }

        if ($ssl) {
            throw new ServerException("SSL support is not implemented");
        }

        /** @noinspection PhpUsageOfSilenceOperatorInspection This function raises PHP E_WARNING */
        $this->socket = @stream_socket_server("tcp://$ip:$port", $errNo, $errStr);
        if (!$this->socket) {
            throw new ServerException("Failed to launch server at tcp://$ip:$port [SSL: " . (int)$ssl . "] - $errStr (e: $errNo)");
        }
        stream_set_blocking($this->socket, 0);

        parent::__construct($this->socket, null, $logger);

        $this->logger->info("Started server at tcp://" . $this->getPeerName() . " [SSL: " . (int)$ssl . "]");
    }

    /**
     * Listener socket is read-only, so method always returns false
     *
     * @return false
     */
    public function isWriteReady()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function onReadReady()
    {
        if (!$this->socket) {
            throw new ServerException("Server socked has gone away (external problem?)");
        }

        $clientSocket = stream_socket_accept($this->socket, null, $peerName);
        if ($clientSocket === false) {
            throw new ServerException("Failed to accept client (didn't you run out of FDs?)");
        }
        stream_set_blocking($clientSocket, 0);

        if($this->extraData != null){
            $this->server->addNode(new $this->clientClassName($clientSocket, $peerName, $this->logger, $this->extraData));
        }else{
            /*
             * CherryHttp compatibility
             */
            $this->server->addNode(new $this->clientClassName($clientSocket, $peerName, $this->logger));
        }
    }

    /**
     * Dummy method - server cannot be write-ready
     *
     * @throws RuntimeException Raised on every method call
     */
    public function onWriteReady()
    {
        throw new RuntimeException("onWriteReady() called on listener (?!)");
    }

    /**
     * Closes listener.
     *
     * @param bool $drop Argument is not used by listener
     *
     * @throws NodeDisconnectException
     */
    public function disconnect($drop = false)
    {
        @fclose($this->socket);
        $this->logger->info("Listener $this closed");

        throw new NodeDisconnectException($this);
    }

    /**
     * Since listeners doesn't parse any data method always returns false
     *
     * @return false
     */
    protected function processInputBuffer()
    {
        return false;
    }
}