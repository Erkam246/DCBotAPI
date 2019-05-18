<?php

namespace dcbotapi\discord\event;

use dcbotapi\discord\other\Message;
use dcbotapi\discord\other\MessageChannel;
use dcbotapi\discord\other\User;

class MessageEvent {
    private $data = [], $message, $channel;

    public function __construct(array $data, array $channelData){
        $this->channel = new MessageChannel($channelData);
        $this->message = new Message($data, $this->channel);
        $this->data = $data;
    }

    public function getAuthor(): User{
        return $this->getMessage()->getAuthor();
    }

    public function getMessage(): Message{
        return $this->message;
    }

    public function getChannel(): MessageChannel{
        return $this->channel;
    }
}