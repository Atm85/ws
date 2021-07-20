# Atom\ws - easy websockets in php

This library is based on the js npm package https://www.npmjs.com/package/ws and was designed to function in a similar way...

## installing
* add the `repository` directive to your composer.json file
* include Atom/ws websockets library in your `require` directive
* run `composer update` to download the library into your vendor directory
* re-generate the `vendor/autoload.php` file if not already with `composer dump-autoload -o`

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/iAtomPlaza/ws"
        }
    ],
    "require": {
        "atom/ws": "dev-master"
    }
}
```
```sh
composer update && composer dump-autoload -o
```

## Setting up the websocket server
**1:** first we need to instantiat the `WebsocketServer` class with the parameters:
* address
* port
```php
$wss = new \Atom\ws\WebsocketServer("127.0.0.1", 8080);
```
**2:** now you can add event listeners and other options to the `WebsocketServer` instance
* see [events](#events) for a list of supported event handlers
```php
$wss->on("connection", function ($ws) {
    echo "Connection established\n";
});
```
**3:** the returned `$ws` variable is a `Websocket` instance and is used to send and recieve data on the socket connection
* `$ws` is the an instance of the client that sent the message
* `$message` is a string and contains the message that was sent by the client
* to send data back to the client, just call `$ws->send(string $message)`
```php
$wss->on("connection", function ($ws) {
    echo "New client connected\n";
    $ws->on("message", function ($ws, $message) {
        echo "Recieved client message:" . $message;

        // send message back to the client...
        $ws->send("some realy cool meesage I want to send back!");
    });
});
```

**4:** now call the listener method to start the websocket server 
* this must also be the last method called as it is blocking
* if you add event listeners and other options, they must be set before this method is called.
```php
$wss->->listen();
```

## Events
| Websocket | WebsocketServer | descriptsion                            |
| --------- | --------------- | --------------------------------------- |
|           | error           | called when an error ocurrs             |
|           | connection      | called when a connection is established |
|           | disconnect      | called when a connection is closed      |
| message   |                 | called when a message is recieved       |

## full code example
```php
<?php

require_once __DIR__ . "/vendor/autoload.php";

use Atom\ws\Websocket;
use Atom\ws\WebsocketServer;

$wss = new WebsocketServer("0.0.0.0", 2222);
$wss->on("connection", function($ws) {
    
    echo "New client connected\n";

    $ws->on("message", function($ws, $message) {
        echo "Message recieved: " . $message . "\n";
    });
});

$wss->on("disconnect", function () {
    echo "Client disconnected\n";
});

$wss->listen();
```