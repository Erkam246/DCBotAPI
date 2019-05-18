<?php

namespace dcbotapi\discord\event;

use dcbotapi\discord\Author;

class MessageEvent {
    private $data = [];

    public function __construct(array $data){
        $this->data = $data;
    }

    public function getAuthor(): Author{
        return new Author($this->data["author"]);
    }

    public function getMessage(){
        return $this->data["content"];
    }
}