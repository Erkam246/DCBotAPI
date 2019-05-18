<?php

namespace dcbotapi\discord\other;

use dcbotapi\discord\Manager;
use dcbotapi\discord\utils\EmbedMessage;

use function json_decode;
use function json_encode;
use function strlen;

class User {
    public $data = [];

    public function __construct(array $data){
        $this->data = $data;
    }

    public function getId(): string{
        return $this->data["id"];
    }

    public function isBot(): bool{
        return (isset($this->data["bot"]) && $this->data["bot"]);
    }

    public function getUsername(): string{
        return $this->data["username"];
    }

    public function getAvatar(): string{
        return "https://cdn.discordapp.com/avatars/".$this->getId()."/".$this->data["avatar"];
    }

    public function sendMessage($message): void{
        if(empty($message)) return;
        $data = json_encode(["recipient_id" => $this->getId()]);
        $headers["content-length"] = strlen($data);
        $headers["content-type"] = "application/json";
        Manager::getRequest("/users/@me/channels", function($data) use ($message){
            $data = json_decode($data, true);
            if(!isset($data["id"])) return;
            $id = $data["id"];
            if($message instanceof EmbedMessage){
                $sendMessage["embed"] = $message->toArray();
            }else{
                $sendMessage["content"] = $message;
            }
            $data = json_encode($sendMessage);
            $headers = [];
            $headers["content-length"] = strlen($data);
            $headers["content-type"] = "application/json";;
            Manager::getRequest("/channels/".$id."/messages", null, "POST", $headers)->end($data);
        }, "POST", $headers)->end($data);
    }
}