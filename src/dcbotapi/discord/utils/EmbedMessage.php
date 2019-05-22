<?php

namespace dcbotapi\discord\utils;

class EmbedMessage {
    private $embed = [];

    public const MAX_TITLE = 256, MAX_DESC = 2048, MAX_FIELDS = 25, MAX_LEN = 6000;

    public function __construct(){
        $this->embed["timestamp"] = date("c");
    }

    public function addField($name, $value, $inline = false){
        $this->embed["fields"][] = ["name" => $name, "value" => $value, "inline" => $inline];
    }

    public function setColor(int $color){
        $this->embed["color"] = $color;
    }

    public function setTitle(string $title){
        $this->embed["title"] = $title;
    }

    public function setUrl(string $url){
        $this->embed["url"] = $url;
    }

    public function setDescription(string $desc){
        $this->embed["description"] = $desc;
    }

    public function setAuthor($name, $iconUrl = "", $url = ""){
        $this->embed{"author"} = ["name" => $name, "icon_url" => $iconUrl, "url" => $url];
    }

    public function toArray(): array{
        return $this->embed;
    }
}