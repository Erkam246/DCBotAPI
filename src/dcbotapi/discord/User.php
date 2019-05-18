<?php

namespace dcbotapi\discord;

use function json_decode;

class User {
    public $id = "", $username = null;

    public function __construct(string $id){
        $this->id = $id;
        Manager::getRequest("/users/".$id, function($data){
            $data = json_decode($data, true);
            $this->username = $data["username"];
        });
    }

    public function getUsername(): string{
        return $this->username;
    }
}