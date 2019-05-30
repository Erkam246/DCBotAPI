<?php

namespace dcbotapi\discord;

use dcbotapi\discord\event\MessageEvent;
use Evenement\EventEmitter;

class EventHandler extends EventEmitter {
    
    public function __construct(DiscordClient $client){
        // Connection Handling
        $this->on("shard.closed", [$client, "shardClosed"]);
        $this->on("shard.message", [$client, "gotMessage"]);
        // OPCode handling
        $this->on("opcode.0", [$client, "gotDispatch"]);
        $this->on("opcode.1", [$client, "gotHeartbeat"]);
        $this->on("opcode.7", [$client, "gotReconnectRequest"]);
        $this->on("opcode.9", [$client, "gotInvalidSession"]);
        $this->on("opcode.10", [$client, "gotHello"]);
        $this->on("opcode.11", [$client, "gotHeartBeatAck"]);
        // General Server Events
        $this->on("event.READY", [$client, "handleReadyEvent"]);
        // Channel Events
        $this->on("event.CHANNEL_CREATE", [$client, "handleChannelCreate"]);
        $this->on("event.CHANNEL_UPDATE", [$client, "noop"]);
        $this->on("event.CHANNEL_DELETE", [$client, "handleChannelDelete"]);
        $this->on("event.CHANNEL_PINS_UPDATE", [$client, "noop"]);
        // Guild Events
        $this->on("event.GUILD_CREATE", [$client, "handleGuildData"]);
        $this->on("event.GUILD_UPDATE", [$client, "handleGuildData"]);
        $this->on("event.GUILD_DELETE", [$client, "handleGuildDelete"]);
        $this->on("event.GUILD_ROLE_CREATE", [$client, "noop"]);
        $this->on("event.GUILD_ROLE_UPDATE", [$client, "noop"]);
        $this->on("event.GUILD_ROLE_DELETE", [$client, "noop"]);
        $this->on("event.GUILD_MEMBER_ADD", [$client, "handleGuildMemberAdd"]);
        $this->on("event.GUILD_MEMBERS_CHUNK", [$client, "noop"]);
        $this->on("event.GUILD_MEMBER_UPDATE", [$client, "noop"]);
        $this->on("event.GUILD_MEMBER_REMOVE", [$client, "handleGuildMemberRemove"]);
        $this->on("event.GUILD_BAN_ADD", [$client, "noop"]);
        $this->on("event.GUILD_BAN_REMOVE", [$client, "noop"]);
        $this->on("event.GUILD_EMOJIS_UPDATE", [$client, "noop"]);
        $this->on("event.GUILD_INTEGRATIONS_UPDATE", [$client, "noop"]);
        // Message Events
        $this->on("event.MESSAGE_CREATE", function(MessageEvent $event){});
        $this->on("event.MESSAGE_UPDATE", function(MessageEvent $event){});
        $this->on("event.MESSAGE_DELETE", [$client, "noop"]);
        $this->on("event.MESSAGE_DELETE_BULK", [$client, "noop"]);
        $this->on("event.MESSAGE_REACTION_ADD", [$client, "noop"]);
        $this->on("event.MESSAGE_REACTION_REMOVE", [$client, "noop"]);
        $this->on("event.MESSAGE_REACTION_REMOVE_ALL", [$client, "noop"]);
        // Other events
        $this->on("event.PRESENCE_UPDATE", [$client, "noop"]);
        $this->on("event.TYPING_START", [$client, 'noop']);
        $this->on("event.USER_UPDATE", [$client, "noop"]);
        $this->on("event.VOICE_STATE_UPDATE", [$client, "noop"]);
        $this->on("event.VOICE_SERVER_UPDATE", [$client, "noop"]);
        $this->on("event.WEBHOOKS_UPDATE", [$client, "noop"]);
        $this->on("event.PRESENCES_REPLACE", [$client, 'noop']);
    }
}