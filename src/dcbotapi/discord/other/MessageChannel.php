<?php

namespace dcbotapi\discord\other;

use dcbotapi\discord\Manager;
use dcbotapi\discord\utils\EmbedMessage;

use function json_encode;
use function strlen;

class MessageChannel {
    private $data = [];

    public function __construct(array $data){
        $this->data = $data;
    }

    public function getId(): string{
        return $this->data["id"];
    }

    public function getType(): int{
        return $this->data["type"];
    }

    public function getName(): string{
        return $this->data["name"];
    }

    public function sendMessage($message): void{
        if(empty($message)) return;
        $headers["content-length"] = strlen($message);
        $headers["content-type"] = "application/json";
        if($message instanceof EmbedMessage){
            $sending["embed"] = $message->toArray();
        }else{
            $sending["content"] = $message;
        }
        $msg = json_encode($sending);
        $headers["content-length"] = strlen($msg);
        $headers["content-type"] = "application/json";;
        Manager::getRequest("channels/".$this->getId()."/messages", null, "POST", $headers)->end($msg);
    }
}