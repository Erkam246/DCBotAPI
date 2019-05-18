<?php

namespace dcbotapi\discord;

use dcbotapi\discord\event\MessageEvent;

use React\HttpClient\Client as HTTPClient;
use React\EventLoop\Factory as EventLoopFactory;
use React\EventLoop\LoopInterface;

use Ratchet\Client\Connector as RatchetConnector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface as RatchetMessageInterface;

use Exception;

use function random_bytes;
use function json_decode;
use function json_encode;
use function str_replace;
use function strlen;
use function bin2hex;
use function time;
use function array_shift;
use function call_user_func_array;
use function is_array;
use function var_dump;
use function in_array;

class DiscordClient {
    /** @var LoopInterface LoopInterface */
    private $loopInterface;
    /** @var EventHandler $eventHandler */
    public $eventHandler;

    public static $clientID = "";
    public static $clientSecret = "";
    public static $token = "";
    /** @var HTTPClient ?$httpClient */
    public static $httpClient = null;

    private $gwInfo = [];
    private $myInfo = [];
    private $shards = [];
    private $slowMessageQueue = [];
    private $guilds = [];
    private $personChannels = [];

    private $connectTime = 0;

    private $disconnecting = false;

    public $cleanupTimerID = "", $slowMessageTimerID = "";

    /**
     * Create a new DiscordClient.
     *
     * @param $clientID
     * @param $clientSecret
     * @param $token
     */
    public function __construct($clientID, $clientSecret, $token){
        new Manager();
        $this->eventHandler = Manager::getEventHandler();

        self::$clientID = $clientID;
        self::$clientSecret = $clientSecret;
        self::$token = $token;

        // Connection Handling
        $this->eventHandler->on('shard.closed', [$this, 'shardClosed']);
        $this->eventHandler->on('shard.message', [$this, 'gotMessage']);

        // OPCODE handling
        $this->eventHandler->on('opcode.0', [$this, 'gotDispatch']);
        $this->eventHandler->on('opcode.1', [$this, 'gotHeartBeat']);
        // ...
        $this->eventHandler->on('opcode.7', [$this, 'gotReconnectRequest']);
        // ...
        $this->eventHandler->on('opcode.9', [$this, 'gotInvalidSession']);
        $this->eventHandler->on('opcode.10', [$this, 'gotHello']);
        $this->eventHandler->on('opcode.11', [$this, 'gotHeartBeatAck']);

        // General Server Events
        $this->eventHandler->on('event.READY', [$this, 'handleReadyEvent']);

        // Guild Events
        $this->eventHandler->on('event.GUILD_CREATE', [$this, 'handleGuildCreate']);
        $this->eventHandler->on('event.GUILD_UPDATE', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_DELETE', [$this, 'handleGuildDelete']);
        $this->eventHandler->on('event.GUILD_ROLE_CREATE', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_ROLE_UPDATE', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_ROLE_DELETE', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_MEMBER_ADD', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_MEMBERS_CHUNK', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_MEMBER_UPDATE', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_MEMBER_REMOVE', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_BAN_ADD', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_BAN_REMOVE', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_EMOJIS_UPDATE', [$this, 'noop']);
        $this->eventHandler->on('event.GUILD_INTEGRATIONS_UPDATE', [$this, 'noop']);

        // Channel Events
        $this->eventHandler->on('event.CHANNEL_CREATE', [$this, 'noop']);
        $this->eventHandler->on('event.CHANNEL_UPDATE', [$this, 'noop']);
        $this->eventHandler->on('event.CHANNEL_DELETE', [$this, 'handleChannelDelete']);
        $this->eventHandler->on('event.CHANNEL_PINS_UPDATE', [$this, 'noop']);

        // Message Events
        $this->eventHandler->on('event.MESSAGE_CREATE', function(MessageEvent $event){});
        $this->eventHandler->on('event.MESSAGE_UPDATE', function(MessageEvent $event){});
        $this->eventHandler->on('event.MESSAGE_DELETE', [$this, 'noop']);
        $this->eventHandler->on('event.MESSAGE_DELETE_BULK', [$this, 'noop']);

        $this->eventHandler->on('event.MESSAGE_REACTION_ADD', [$this, 'noop']);
        $this->eventHandler->on('event.MESSAGE_REACTION_REMOVE', [$this, 'noop']);
        $this->eventHandler->on('event.MESSAGE_REACTION_REMOVE_ALL', [$this, 'noop']);

        // Other events.
        $this->eventHandler->on('event.TYPING_START', [$this, 'noop']);
        $this->eventHandler->on('event.PRESENCE_UPDATE', [$this, 'noop']);
        $this->eventHandler->on('event.USER_UPDATE', [$this, 'noop']);
        $this->eventHandler->on('event.VOICE_STATE_UPDATE', [$this, 'noop']);
        $this->eventHandler->on('event.VOICE_SERVER_UPDATE', [$this, 'noop']);
        $this->eventHandler->on('event.WEBHOOKS_UPDATE', [$this, 'noop']);
        $this->eventHandler->on('event.MESSAGE_ACK', [$this, 'noop']);
    }

    public function log($message){
        echo $message."\n";
    }

    public function ColorLog($message, int $color){
        $colors = [1 => "0;34", 2 => "0;31"];
        if(isset($colors[$color]))
            echo "\033[".$colors[$color]."m".$message."\033[0m \n";
        else
            echo $message."\n";
    }

    private function reset(){
        $this->disconnecting = false;
        self::$httpClient = null;
        $this->gwInfo = [];
        $this->myInfo = [];
        $this->shards = [];
        $this->guilds = [];
        $this->personChannels = [];
        $this->connectTime = 0;
        $this->slowMessageQueue = [];
        $this->slowMessageTimerID = "";
        $this->cleanupTimerID = "";
    }

    private function doEmit(string $event, array $params = []){
        Manager::getEventHandler()->emit($event, $params);
    }

    /**
     * Set the message loop to use.
     *
     * @param LoopInterface $loopInterface
     * @return self
     * @throws Exception
     */
    public function setLoopInterface(LoopInterface $loopInterface){
        if(self::$httpClient !== null){
            throw new Exception('Already connected.');
        }

        $this->loopInterface = $loopInterface;

        return $this;
    }

    /**
     * Get the LoopInterface being used by this client.
     *
     * @return LoopInterface our loopInterface
     */
    public function getLoopInterface(): LoopInterface{
        return $this->loopInterface;
    }

    /**
     * Connect to Discord.
     *
     * @throws Exception
     */
    public function connect(){
        if(self::$httpClient !== null){
            throw new Exception('Already connected.');
        }

        $this->reset();
        $this->connectTime = time();

        $startLoop = false;
        if($this->loopInterface == null){
            $startLoop = true;
            $this->loopInterface = EventLoopFactory::create();
        }

        self::$httpClient = new HTTPClient($this->loopInterface);

        Manager::getRequest('/users/@me', function($data){
            $this->myInfo = json_decode($data, true);
        })->end();

        Manager::getRequest('/gateway/bot', function($data){
            $this->gwInfo = json_decode($data, true);
            if(isset($this->gwInfo['shards'])){
                $this->connectToGateway($this->gwInfo['shards']);
            }else{
                $this->doEmit('DiscordClient.message', ['Unknown response from API', $data]);
            }
        })->end();

        $slowMessageTimerID = bin2hex(random_bytes(16));
        $this->slowMessageTimerID = $slowMessageTimerID;
        $this->getLoopInterface()->addPeriodicTimer(6, function($timer) use ($slowMessageTimerID){
            if($slowMessageTimerID != $this->slowMessageTimerID){
                $this->getLoopInterface()->cancelTimer($timer);
            }

            if(!empty($this->slowMessageQueue)){
                $message = array_shift($this->slowMessageQueue);
                if(is_array($message)){
                    call_user_func_array([$this, 'sendShardMessage'], $message);
                }else if(is_callable($message)){
                    call_user_func_array($message, []);
                }
            }
        });

        $cleanupTimerID = bin2hex(random_bytes(16));
        $this->cleanupTimerID = $cleanupTimerID;
        $this->getLoopInterface()->addPeriodicTimer(60, function($timer) use ($cleanupTimerID){
            if($cleanupTimerID != $this->cleanupTimerID){
                $this->getLoopInterface()->cancelTimer($timer);
            }
            $this->doCleanup();
        });

        if($startLoop){
            $this->getLoopInterface()->run();
        }
    }

    public function getMyInfo(): array{
        return $this->myInfo;
    }

    public function disconnect(){
        $this->disconnecting = true;

        foreach($this->shards as &$item){
            $item['conn']->close();
            unset($item['heartbeat_interval']);
        }

        $this->reset();
    }

    public function doCleanup(){
        foreach($this->personChannels as $id => $data){
            if($data['time'] < (time() - 300)){
                Manager::getRequest('/channels/'.$data['id'], null, "DELETE")->end();
            }
        }
    }

    private function connectToGateway($shards = 1){
        foreach($this->shards as &$item){
            $item['conn']->close();
            unset($item['heartbeat_interval']);
        }

        $this->shards = [];

        for($shard = 0; $shard < $shards; $shard++){
            $this->connectShard($shard);
        }
    }

    private function connectShard(int $shard){
        $connector = new RatchetConnector($this->getLoopInterface());

        $this->doEmit('DiscordClient.debugMessage', ['Connecting shard: '.$shard]);

        $connector($this->gwInfo['url'].'/?v=6&encoding=json')->then(function(WebSocket $conn) use ($shard){
            $this->doEmit('DiscordClient.debugMessage', ['Connected shard: '.$shard]);

            $this->shards[$shard] = ['conn' => $conn, 'seq' => null, 'ready' => false];
            $this->doEmit('shard.connected', [$shard]);

            $conn->on('message', function(RatchetMessageInterface $msg) use ($shard, $conn){
                $this->doEmit('shard.message', [$shard, $msg]);
            });

            $conn->on('close', function($code = null, $reason = null) use ($shard){
                $this->doEmit('shard.closed', [$shard, $code, $reason]);
            });

        }, function(Exception $e) use ($shard){
            $this->doEmit('shard.connect.error', [$shard]);

            $this->doEmit('DiscordClient.debugMessage', ['Could not connect shard '.$shard.': '.$e->getMessage()]);

            $this->getLoopInterface()->addTimer(30, function() use ($shard){
                $this->doEmit('DiscordClient.debugMessage', ['Trying again to connect shard: '.$shard]);
                $this->connectShard($shard);
            });
        });
    }

    public function sendShardMessage(int $shard, int $opcode, $data, $sequence = null, $eventName = null){
        $sendData = ['op' => $opcode, 'd' => $data];
        if($opcode == 0){
            $sendData['s'] = $sequence;
            $sendData['t'] = $eventName;
        }

        $this->shards[$shard]['conn']->send(json_encode($sendData));
    }

    public function gotMessage(int $shard, RatchetMessageInterface $msg){
        $this->shards[$shard]['sentHB'] = false;

        $data = json_decode($msg->getPayload(), true);
        $opcode = $data['op'];

        if(empty(Manager::getEventHandler()->listeners('opcode.'.$opcode))){
            $this->log('Got Unknown Message on shard '.$shard." - ".$msg->getPayload());
        }

        $this->doEmit('opcode.'.$opcode, [$shard, $opcode, $data]);
    }

    public function gotInvalidSession(int $shard, int $opcode, array $data){
        $this->sendIdentify($shard);
    }

    public function gotReconnectRequest(int $shard, int $opcode, array $data){
        // Requeue an identify message, and try again.
        $this->sendIdentify($shard);
    }

    public function gotHello(int $shard, int $opcode, array $data){
        $this->shards[$shard]['heartbeat_interval'] = $data['d']['heartbeat_interval'];
        $this->scheduleHeartbeat($shard);
        $this->sendIdentify($shard);
    }

    private function sendIdentify(int $shard){
        $identify = [];
        $identify["token"] = self::$token;
        $identify["properties"] = ["os" => PHP_OS, 'browser' => 'erkamkahriman/dcbotapi', 'library' => 'erkamkahriman/dcbotapi'];
        $identify["compress"] = false;
        $identify["shard"] = [$shard, $this->gwInfo["shards"]];
        $this->slowMessageQueue[] = [$shard, 2, $identify];
    }

    private function scheduleHeartbeat(int $shard){
        if(!isset($this->shards[$shard]['heartbeat_interval'])){
            return;
        }

        $delay = $this->shards[$shard]['heartbeat_interval'];

        $timerID = bin2hex(random_bytes(16));
        $this->shards[$shard]['timerID'] = $timerID;

        $this->getLoopInterface()->addTimer($delay / 1000, function() use ($shard, $timerID){
            if(!isset($this->shards[$shard]['timerID'])){
                return;
            }
            if($this->shards[$shard]['timerID'] != $timerID){
                return;
            }

            if($this->shards[$shard]['sentHB']){
                $this->doEmit('DiscordClient.debugMessage', ['Shard connection appears to be dead: '.$shard]);

                $this->shards[$shard]['conn']->close();
            }else{
                $this->doEmit('DiscordClient.debugMessage', ['Sending heartbeat for shard: '.$shard]);
                $this->shards[$shard]['sentHB'] = true;

                $this->sendShardMessage($shard, 1, $this->shards[$shard]['seq']);
                $this->scheduleHeartbeat($shard);
            }
        });
    }

    public function gotHeartBeat(int $shard, int $opcode, array $data){
        $this->doEmit('DiscordClient.debugMessage', ['Got HB on shard '.$shard, $data]);
    }

    public function gotHeartBeatAck(int $shard, int $opcode, array $data){
        $this->doEmit('DiscordClient.debugMessage', ['Got HB Ack on shard '.$shard, $data]);
    }

    public function gotDispatch(int $shard, int $opcode, array $data){
        $this->shards[$shard]["seq"] = $data["s"];
        $event = $data["t"];
        $eventData = $data["d"];

        if(empty(Manager::getEventHandler()->listeners("event.".$event))){
            var_dump("Event not found: ".$event);
        }
        $messageevents = ["event.MESSAGE_CREATE", "event.MESSAGE_UPDATE"];
        if(in_array("event.".$event, $messageevents)){
            $messageEvent = new MessageEvent($eventData);
            $this->doEmit("event.".$event, [$messageEvent]);
        }elseif("event.".$event === "event.READY"){
            $this->doEmit("event.".$event, [$shard]);
        }else{
            $this->doEmit("event.".$event, [$this, $shard, $event, $eventData]);
        }
    }

    public function noop(DiscordClient $client, int $shard, string $event, array $data){
    }

    public function handleReadyEvent(int $shard){
        $this->shards[$shard]["ready"] = true;
    }

    public function handleMessageEvent(DiscordClient $client, Author $author, $message){

    }

    public function handleGuildCreate(DiscordClient $client, int $shard, string $event, array $data){
        $guildData = [];
        $guildData["name"] = $data['name'];
        $guildData["shard"] = $shard;
        $guildData["channels"] = [];

        $this->doEmit('DiscordClient.message', ['Found new server on shard '.$shard.': '.$guildData['name'].' ('.$data['id'].')']);

        foreach($data['channels'] as $channel){
            if($channel["type"] === "0"){
                $guildData['channels'][$channel['id']] = [];
                $guildData['channels'][$channel['id']]['name'] = $channel['name'];

                $this->doEmit('DiscordClient.message', ["\t".'Channel: '.$channel['name'].' ('.$channel['id'].')']);
            }
        }

        $this->guilds[$data['id']] = $guildData;
    }

    public function handleGuildDelete(DiscordClient $client, int $shard, string $event, array $data){
        if(isset($this->guilds[$data['id']])){
            $this->doEmit('DiscordClient.message', ['Removed server on shard '.$shard, ': '.$this->guilds[$data['id']]['name'].' ('.$data['id'].')']);

            unset($this->guilds[$data['id']]);
        }
    }

    public function handleChannelCreate(DiscordClient $client, int $shard, string $event, array $data){
        if($data['type'] == '0' && isset($data['guild_id']) && isset($this->guilds[$data['guild_id']])){
            $guildID = $data['guild_id'];
            $chanID = $data['id'];

            if(isset($this->guilds[$guildID]['channels'][$chanID])){
                return;
            }

            $this->guilds[$guildID]['channels'][$chanID] = [];
            $this->guilds[$guildID]['channels'][$chanID]['name'] = $data['name'];

            $this->doEmit('DiscordClient.message', ['Found new channel for server '.$this->guilds[$guildID]['name'].' ('.$guildID.') on shard '.$shard.': '.$data['name'].' ('.$chanID.')']);
        }else if($data['type'] == '1'){
            $person = $data['recipients'][0];
            if(isset($this->personChannels[$person['id']])){
                return;
            }

            $this->personChannels[$person['id']] = ['id' => $data['id'], 'time' => time()];
            $this->doEmit('DiscordClient.message', ['Found new channel for person '.$person['username'].'#'.$person['discriminator'].' ('.$person['id'].') on shard '.$shard.': '.$data['id']]);
        }else{
            $this->doEmit('DiscordClient.message', ['Found new channel on shard '.$shard, $data]);
        }
    }

    public function handleChannelDelete(DiscordClient $client, int $shard, string $event, array $data){
        if($data['type'] == '0' && isset($data['guild_id']) && isset($this->guilds[$data['guild_id']])){
            $guildID = $data['guild_id'];
            $chanID = $data['id'];
            unset($this->guilds[$guildID]['channels'][$chanID]);

            $this->doEmit('DiscordClient.message', ['Removed channel on server '.$this->guilds[$guildID]['name'].' ('.$guildID.') on shard '.$shard.': '.$data['name'].' ('.$chanID.')']);
        }elseif($data['type'] == '1'){
            $person = $data['recipients'][0];
            unset($this->personChannels[$person['id']]);
            $this->doEmit('DiscordClient.message', ['Removed channel for person '.$person['username'].'#'.$person['discriminator'].' ('.$person['id'].') on shard '.$shard.': '.$data['id']]);
        }else{
            $this->doEmit('DiscordClient.message', ['Removed channel on shard '.$shard, $data]);
        }
    }

    public function shardClosed(int $shard, $code = null, $reason = null){
        $this->doEmit('DiscordClient.debugMessage', ['Shard '.$shard.' closed ('.$code.' - '.$reason.')']);

        if(!$this->disconnecting){
            $reconnectTime = 5;

            if($code == 4003 || $code == 4004){
                $this->doEmit('DiscordClient.message', ['Error Connecting - authentication error - not attempting to reconnect.']);
            }else{
                $this->getLoopInterface()->addTimer($reconnectTime, function() use ($shard){
                    $this->doEmit('DiscordClient.debugMessage', ['Reconnecting shard: '.$shard]);
                    $this->connectShard($shard);
                });
            }
        }
    }

    public function isReady(): bool{
        if(empty($this->shards)){
            return false;
        }

        foreach($this->shards as $shard){
            if(!$shard['ready']){
                return false;
            }
        }

        return true;
    }

    public function validServer(string $target): bool{
        return isset($this->guilds[$target]);
    }

    public function validChannel(string $server, string $target): bool{
        return isset($this->guilds[$server]["channels"][$target]);
    }

    public function validUser(string $id): bool{
        return true;
    }

    public function getUser(string $id): User{
        return new User($id);
    }

    public function getChannelMessages(string $server, string $channel, callable $function){
        if(!$this->validChannel($server, $channel)){
            return;
        }
        Manager::getRequest('/channels/'.$channel.'/messages', $function)->end();
    }

    public function sendChannelMessage(string $server, string $channel, string $message){
        if(!$this->validChannel($server, $channel)){
            return;
        }

        $sendMessage = [];
        $sendMessage['content'] = $message;

        $data = json_encode($sendMessage);
        $headers = [];
        $headers['content-length'] = strlen($data);
        $headers['content-type'] = 'application/json';

        Manager::getRequest('/channels/'.$channel.'/messages', null, "POST", $headers)->end($data);
    }

    public function sendPersonMessage(string $person, string $message){
        // if (!$this->validPerson($person)) { return; }

        $data = json_encode(['recipient_id' => $person]);
        $headers = [];
        $headers['content-length'] = strlen($data);
        $headers['content-type'] = 'application/json';

        Manager::getRequest('/users/@me/channels', function($data) use ($message){
            $data = json_decode($data, true);
            $this->sendPersonChannelMessage($data['id'], $message);
        }, 'POST', $headers)->end($data);
    }

    public function sendPersonChannelMessage(string $personChannel, string $message){
        $sendMessage = [];
        $sendMessage['content'] = $message;

        $data = json_encode($sendMessage);
        $headers = [];
        $headers['content-length'] = strlen($data);
        $headers['content-type'] = 'application/json';

        Manager::getRequest('/channels/'.$personChannel.'/messages', null, "POST", $headers)->end($data);
    }
}
