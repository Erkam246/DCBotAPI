<?php

namespace dcbotapi\discord;

class Author {
    private $data = [];

    public function __construct(array $data){
        $this->data = $data;
    }

    public function getId(): string{
        return $this->data["id"];
    }

    public function getUsername(): string{
        return $this->data["username"];
    }

    public function isBot(): bool{
        return (isset($this->data["bot"]) && $this->data["bot"]);
    }

    public function getAvatar(): string{
        return $this->data["avatar"];
    }
}