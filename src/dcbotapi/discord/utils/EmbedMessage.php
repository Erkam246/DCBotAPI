<?php

namespace dcbotapi\discord\utils;

use dcbotapi\discord\guild\Role;

class EmbedMessage {
    private $embed = ["title" => "hallo", "type" => "rich", "description" => null, "url" => null, "timestamp" => null, "color" => Role::DEFAULT_COLOR, "footer" => null, "image" => null, "thumbnail" => null, "video" => null, "author" => null, "fields" => []];

    public function __construct(){
        //$this->embed["timestamp"] = date("c");
        //$this->embed{"author"} = ["name" => "", "icon_url" => "", "url" => ""];
    }

    public function addField($name, $value, $inline = false){
        $this->embed["fields"][] = ["name" => $name, "value" => $value, "inline" => $inline];
    }

    public function setColor(?int $color){
        $this->embed["color"] = $color;
    }

    public function setTitle(string $title){
        $this->embed["title"] = $title;
    }

    public function setDescription(string $desc){
        $this->embed["description"] = $desc;
    }

    public function setAuthor($name, $iconUrl = "", $url = ""){
        $this->embed{"author"} = ["name" => $name, "icon_url" => $iconUrl, "url" => $url];
    }

    public function toArray(): array{;
        return ["embeds" => $this->embed];
    }
}