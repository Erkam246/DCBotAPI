<?php

namespace dcbotapi\discord\other;

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
}