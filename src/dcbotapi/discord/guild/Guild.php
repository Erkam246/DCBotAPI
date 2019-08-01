<?php

namespace dcbotapi\discord\guild;

use dcbotapi\discord\Manager;
use dcbotapi\discord\other\MessageChannel;

use function json_encode;

class Guild {
    private $data = [];
    /** @var Member[] $members */
    private $members = [];
    /** @var Role[] $roles */
    private $roles = [];
    /** @var MessageChannel[] $textchannels */
    private $textchannels = [];
    /** @var VoiceChannel $voicechannels */
    private $voicechannels = [];

    public function __construct(array $data){
        $this->data = $data;
        if(isset($data["members"])){
            foreach($data["members"] as $index => $member){
                $this->members[$member["user"]["id"]] = new Member($member);
            }
        }
        if(isset($data["roles"])){
            foreach($data["roles"] as $index => $role){
                $this->roles[$role["id"]] = new Role($role);
            }
        }
        if(isset($data["channels"])){
            foreach($data["channels"] as $index => $channel){
                if($channel["type"] === 0){
                    $this->textchannels[$channel["id"]] = new MessageChannel($channel);
                }elseif($channel["type"] === 2){
                    $this->voicechannels[$channel["id"]] = new VoiceChannel($channel);
                }
            }
        }
    }

    public function getName(){
        return $this->data["name"];
    }

    public function setName(string $name){
        Manager::getRequest("guilds/".$this->getId(), null, "PATCH")->end(json_encode(["name" => $name]));
    }

    public function getId(){
        $this->data["id"];
    }

    public function getRoles(): array{
        return $this->roles;
    }

    public function getRoleById(string $id): ?Role{
        return isset($this->roles[$id]) ? $this->roles[$id] : null;
    }

    public function getMembers(){
        return $this->members;
    }

    public function getMemberById(string $id): ?Member{
        return isset($this->members[$id]) ? $this->members[$id] : null;
    }

    public function getTextChannels(){
        return $this->textchannels;
    }

    public function getTextChannelById(string $id): ?MessageChannel{
        return isset($this->textchannels[$id]) ? $this->textchannels[$id] : null;
    }

    /**
     * @param $data
     * @internal
     */
    public function addTextChannel(array $data){
        $this->textchannels[$data["id"]] = new MessageChannel($data);
    }

    public function createTextChannel(string $name){
        Manager::getRequest("guilds/".$this->getId()."/channels", null, "POST")->end(json_encode(["name" => $name]));
    }

    public function getVoiceChannels(){
        return $this->voicechannels;
    }

    public function getVoiceChannelById(string $id): VoiceChannel{
        return isset($this->voicechannels[$id]) ? $this->voicechannels[$id] : null;
    }

    public function addMember(array $data){
        $id = $data["user"]["id"];
        if(!isset($this->members[$id])){
            $this->members[$id] = new Member($data);
        }
    }

    public function removeMember(string $id){
        if(isset($this->members[$id])){
            unset($this->members[$id]);
        }
    }
}