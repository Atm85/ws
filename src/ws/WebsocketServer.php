<?php

namespace Atom\ws;

class WebsocketServer {

    private string $address;
    private int $port;

    protected $clients = [];
    protected $server = null;

    // event handlers.
    public $onConnect = null;
    public $onDisconnect = null;
    public $onError = null;

    public function __construct(string $address, int $port) {

        $this->address = $address;
        $this->port = $port;

        // Create WebSocket.
        $this->server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->server, $address, $port);
    }

    // listen for websocket connections.
    public function listen() :void {

        echo "Starting websocket server on... " . $this->address . ":" . $this->port;
        
        socket_listen($this->server);
        socket_set_nonblock($this->server);
        $this->clients[] = new WebSocket($this->server);

        do {

            $read = array_map(function($ws) {return $ws->socket;}, $this->clients);
            $write = null; 
            $except = null;

            $ready = socket_select($read, $write, $except, 0);
            if ($ready === false) {
                if ($this->onError !== null) {
                    $callback = $this->onError;
                    $callback("[".socket_last_error()."]"." ".socket_strerror(socket_last_error()));
                }

                return;
            }

            if ($ready < 1) continue;

            // check if there is a client trying to connect.
            if (in_array($this->server, $read)) {
                if (($client = socket_accept($this->server)) !== false) {

                    // send websocket handshake headers.
                    if (!$this->sendHandshakeHeaders($client)) {
                        continue;
                    }

                    // add the new client to the $clients array.
                    $ws = new WebSocket($client);
                    $this->clients[] = $ws;

                    // call "connection" event handler for each new client.
                    if ($this->onConnect !== null) {
                        $callback = $this->onConnect;
                        $callback($ws);
                    }

                    // remove the listening socket from the clients-with-data array.
                    $key = array_search($this->server, $read);
                    unset($read[$key]);
                }
            }

            foreach ($read as $key => $client) {

                $buffer = "";
                $bytes = @socket_recv($client, $buffer, 2048, 0);

                // check if the client is disconnected.
                if ($bytes === false) {

                    // remove client from $clients array
                    // and call disconnect event handler.
                    unset($this->clients[$key]);
                    if ($this->onDisconnect !== null) {
                        $callback = $this->onDisconnect;
                        $callback();
                    }

                    continue;
                }

                $ws = $this->clients[$key];
                $callback = $ws->onMessage;
                if ($callback !== null) {
                    $callback($ws, $this->unmask($buffer));
                }
            }
        } while (true);
        socket_close($this->server);
    }

    public function on(string $event, callable $callable) :void {

        switch ($event) {
            case "connection":
                $this->onConnect = $callable;
                break;
            case "disconnect":
                $this->onDisconnect = $callable;
                break;
            case "error":
                $this->onError = $callable;
                break;
        }
    }

    private function unmask($payload) :string {
        $length = ord($payload[1]) & 127;
        if ($length == 126) {
            $masks = substr($payload, 4, 4);
            $data = substr($payload, 8);
            $firstcode = substr($payload, 1, 1);
        } elseif ($length == 127) {
            $masks = substr($payload, 10, 4);
            $data = substr($payload, 14);
            $firstcode = substr($payload, 1, 1);
        } else {
            $masks = substr($payload, 2, 4);
            $data = substr($payload, 6);
            $firstcode = substr($payload, 1, 1);
        }

        $text = "";
        for ($i = 0; $i < strlen($data); ++$i) { 
            $text .= $data[$i] ^ $masks[$i%4];
        }

        return $text;
    }

    private function sendHandshakeHeaders($socket) :bool {

        do {

            if (($request = socket_read($socket, 5000)) === false) {
                continue;
            }

            socket_set_nonblock($socket);
            preg_match('#Sec-WebSocket-Key: (.*)\r\n#', $request, $matches);
            $key = base64_encode(pack(
                'H*',
                sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
            ));
            $headers = "HTTP/1.1 101 Switching Protocols\r\n";
            $headers .= "Upgrade: websocket\r\n";
            $headers .= "Connection: Upgrade\r\n";
            $headers .= "Sec-WebSocket-Version: 13\r\n";
            $headers .= "Sec-WebSocket-Accept: $key\r\n\r\n";
            
            if (socket_write($socket, $headers, strlen($headers)) === false) {
                if ($this->onError !== null) {
                    $callback = $this->onError;
                    $callback("[".socket_last_error()."]"." ".socket_strerror(socket_last_error()));
                }
                return false;
            }
            return true;
        } while (true);
    }
}