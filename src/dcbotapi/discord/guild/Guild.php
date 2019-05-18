<?php

namespace dcbotapi\discord\guild;

class Guild {
    private $data = [], $members = [], $roles = [];

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
    }

    public function getName(){
        return $this->data["name"];
    }

    public function getId(){
        $this->data["id"];
    }

    public function getRoles(): array{
        return $this->roles["roles"];
    }

    public function getRoleById(string $id): ?Role{
        return isset($this->roles[$id]) ? $this->roles[$id] : null;
    }

    public function getMembers(): array{
        return $this->members;
    }

    public function getMemberById(string $id): ?Member{
        return isset($this->members[$id]) ? $this->members[$id] : null;
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