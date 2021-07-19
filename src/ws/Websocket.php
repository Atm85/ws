<?php

namespace Atom\ws;

class Websocket {

    public $socket;
    public $onMessage = null;

    public function __construct($socket) {
        $this->socket = $socket;
    }

    public function send($out) :void {
        socket_write($this->socket, chr(129) . chr(strlen($out)) . $out);
    }

    public function on(string $event, callable $callable) :void {

        switch ($event) {
            case "message":
                $this->onMessage = $callable;
                break;
        }
    }
}