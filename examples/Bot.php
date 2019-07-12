<?php

require_once(__DIR__."/../vendor/autoload.php");

use dcbotapi\discord\DiscordClient;
use dcbotapi\discord\event\MessageEvent;
use dcbotapi\discord\utils\EmbedMessage;
use React\EventLoop\Factory;

// (Create a new Application at https://discordapp.com/developers/applications/)
$credit = [
    "ClientID" => "",
    "ClientSecret" => "",
    "Token" => ""
];

$client = new DiscordClient($credit["ClientID"], $credit["ClientSecret"], $credit["Token"]);
$client->log("Discord invite link: https://discordapp.com/oauth2/authorize?client_id=".$credit["ClientID"]."&scope=bot&permissions=0", 4);
$client->log("Starting Bot...");

$loop = Factory::create();

try{
    $client->setLoopInterface($loop);
}catch(Exception $ignore){
}

$event = $client->getEventHandler();

$event->on("event.READY", function() use($client){
    $client->log("Bot is ready.");
});

$event->on("event.MESSAGE_CREATE", function(MessageEvent $event) use($client){
    $author = $event->getAuthor();
    $msg = $event->getMessage();
    $channel = $event->getChannel();
    if($author->isBot()){
        return;
    }
    if($channel->getId() === "537415236232282114"){
        $msg->delete();
        $channel->sendMessage("Hi");
        return;
    }
    $client->log($author->getUsername()." => ".$msg->getContent());
    $guild = $client->getGuildById("537415236232282112");
    if($guild === null){
        return;
    }
    $args = explode(" ", $msg->getContent());
    if(count($args) < 1){
        return;
    }
    $command = strtolower($args[0]);
    if($command === "!say"){
        if(isset($args[1], $args[2])){
            $member = $guild->getMemberById($args[1]);
            unset($args[0], $args[1]);
            if($member !== null){
                $message = implode(" ", $args);
                $member->getUser()->sendMessage($message);
            }
        }
    }elseif($command === "!bc"){
        $embed = new EmbedMessage();
        $embed->setTitle("Sample Embed");
        $embed->setDescription("a descriptio");
        $embed->setColor("16449306");
        foreach($guild->getMembers() as $member){
            if(!$member->isBot()){
                $member->getUser()->sendMessage($embed);
            }
        }
    }
});

$event->on("event.MESSAGE_UPDATE", function(MessageEvent $event) use($client){
    $author = $event->getAuthor();
    $msg = $event->getMessage();
    if(!$author->isBot()){
        $client->log("UPDATE: ".$author->getUsername()." => ".$msg->getContent(), 2);
    }
});

try{
    $client->connect();
}catch(Exception $ignore){
}

$loop->run();