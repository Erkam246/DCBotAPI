<?php

namespace dcbotapi\discord\other;

use dcbotapi\discord\guild\Guild;
use dcbotapi\discord\Manager;
use dcbotapi\discord\utils\EmbedMessage;

use function json_encode;
use function strlen;

class Message {
    private $data = [], $author, $channel;

    public function __construct(array $data, MessageChannel $channel){
        $this->data = $data;
        $this->author = new User($data["author"]);
        $this->channel = $channel;
    }

    public function getContent(){
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

    public function getGuild(): ?Guild{
        return null;
    }

    public function delete(): void{
        Manager::getRequest("/channels/{$this->getChannel()->getId()}/messages/".$this->getId(), null, "DELETE")->end();
    }

    public function edit($message): void{
        if(empty($message)) return;
        $headers["content-length"] = strlen($message);
        $headers["content-type"] = "application/json";
        if($message instanceof EmbedMessage){
            $sending["embed"] = $message->toArray();
        }else{
            $sending["content"] = $message;
        }
        $msg = json_encode($sending);
        Manager::getRequest("/channels/{$this->getChannel()->getId()}/messages/".$this->getId(), null, "PATCH", $headers)->end($msg);
    }
}