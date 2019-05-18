<?php

namespace dcbotapi\discord\other;

class User {
    public $data = [];

    public function __construct(string $data){
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
}