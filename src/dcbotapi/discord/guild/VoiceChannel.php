<?php

namespace dcbotapi\discord\guild;

class VoiceChannel {
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
}