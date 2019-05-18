<?php

namespace dcbotapi\discord;

use Exception;
use React\HttpClient\Request;
use React\HttpClient\Response;

use function is_callable;

class Manager {
    private static $eventHandler;

    public function __construct(DiscordClient $client){
        self::$eventHandler = new EventHandler($client);
    }

    public static function getRequest(string $endpoint, ?callable $gotResponse = null, string $type = "GET", array $headers = []): Request{
        $headers["User-Agent"] = "ErkamKahriman/DCBotAPI (https://github.com/ErkamKahriman/DCBotAPI, 1.0)";
        $headers["Authorization"] = "Bot ".DiscordClient::$token;

        $url = "https://discordapp.com/api/v6".$endpoint;

        $request = DiscordClient::$httpClient->request($type, $url, $headers);

        if(is_callable($gotResponse)){
            $request->on('response', function(Response $response) use ($gotResponse){
                $headers = $response->getHeaders();
                $data = "";

                $response->on('data', function($chunk) use (&$data){
                    $data .= $chunk;
                });

                $response->on('end', function() use ($headers, &$data, $gotResponse){
                    $gotResponse($data, $headers);
                });
            });
        }

        $request->on('error', function(Exception $e){
            $this->showThrowable($e);
        });

        return $request;
    }

    public static function getEventHandler(): EventHandler{
        return self::$eventHandler;
    }
}