<?php

namespace dcbotapi\discord\other;

use dcbotapi\discord\Manager;

use function var_dump;

class Message {
    private $data = [], $author, $channel;

    public function __construct(array $data, MessageChannel $channel){
        $this->data = $data;
        //var_dump($data);
        $this->author = new Author($data["author"] ?? var_dump($data));
        $this->channel = $channel;
    }

    public function getContent(){
        return $this->data["content"];
    }

    public function getAuthor(): Author{
        return $this->author;
    }

    public function getId(): string{
        return $this->data["id"];
    }

    public function getChannel(): MessageChannel{
        return $this->channel;
    }

    public function delete(): void{
        Manager::getRequest("/channels/{$this->getChannel()->getId()}/messages/".$this->getId())->end();
    }
}