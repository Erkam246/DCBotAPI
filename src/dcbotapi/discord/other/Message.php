<?php

namespace dcbotapi\discord\other;

use dcbotapi\discord\Manager;
use dcbotapi\discord\utils\EmbedMessage;

use function json_encode;
use function strlen;

class Message {
    private $data = [], $author, $channel;

    public const MAX_LENGHT = 2000;

    public function __construct(array $data, MessageChannel $channel){
        $this->data = $data;
        $this->author = new User($data["author"]);
        $this->channel = $channel;
    }

    public function getContent(): string{
        return $this->data["content"];
    }

    public function getAuthor(): User{
        return $this->author;
    }

    public function getId(): string{
        return $this->data["id"];
    }

    public function getChannel(): MessageChannel{
        return $this->channel;
    }

    public function delete(?callable $response = null): void{
        Manager::getRequest("channels/{$this->getChannel()->getId()}/messages/".$this->getId(), $response, "DELETE")->end();
    }

    public function edit($message): void{
        if(empty($message)) return;
        if(strlen($message) >= self::MAX_LENGHT) return;
        $headers["content-length"] = strlen($message);
        $headers["content-type"] = "application/json";
        if($message instanceof EmbedMessage){
            $sending["embed"] = $message->toArray();
        }else{
            $sending["content"] = $message;
        }
        $msg = json_encode($sending);
        Manager::getRequest("channels/{$this->getChannel()->getId()}/messages/".$this->getId(), null, "PATCH", $headers)->end($msg);
    }

    public function deleteReactions(?callable $response = null): void{
        Manager::getRequest("channels/{$this->getChannel()->getId()}/messages/".$this->getId()."/reactions", $response, "DELETE")->end();
    }
}