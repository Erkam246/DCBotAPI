<?php

namespace dcbotapi\discord\event;

use dcbotapi\discord\Member;

class MemberEvent {
    private $data = [];

    public function __construct(array $data){
        $this->data = $data;
    }

    public function getMember(): Member{
        return new Member($this->data["member"]);
    }

    public function getMessage(){
        return $this->data["content"];
    }
}