<?php

namespace dcbotapi\discord;

use dcbotapi\discord\event\MessageEvent;
use dcbotapi\discord\guild\Guild;
use dcbotapi\discord\other\User;

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
use function strlen;
use function bin2hex;
use function time;
use function array_shift;
use function call_user_func_array;
use function is_array;
use function var_dump;
use function date_default_timezone_set;
use function is_callable;

class DiscordClient {
    /** @var LoopInterface LoopInterface */
    private $loopInterface;
    /** @var EventHandler $eventHandler */
    public $eventHandler;

    public static $clientID = "", $clientSecret = "", $token = "";

    /** @var HTTPClient ?$httpClient */
    public static $httpClient = null;

    /** @var Guild[] $guilds */
    private $guilds = [];
    private $gwInfo = [], $myInfo = [], $shards = [], $slowMessageQueue = [], $personChannels = [];

    private $connectTime = 0, $lasthb = 0;

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
        date_default_timezone_set("Europe/Berlin");

        new Manager($this);
        $this->eventHandler = Manager::getEventHandler();
        
        self::$clientID = $clientID;
        self::$clientSecret = $clientSecret;
        self::$token = $token;
    }

    public function log($message){
        echo "Output > ".$message."\n";
    }

    //TODO replace characters
    public function ColorLog($message, int $color){
        $colors = [1 => "0;34", 2 => "0;31", 3 => "1;32", 4 => "1;33"];
        if(isset($colors[$color])){
            echo "\033[".$colors[$color]."m".$message."\033[0m \n";
        }else{
            $this->log($message);
        }
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
     * @throws Exception
     */
    public function setLoopInterface(LoopInterface $loopInterface): void{
        if(self::$httpClient !== null){
            throw new Exception("Already connected.");
        }

        $this->loopInterface = $loopInterface;
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
            throw new Exception("Already connected.");
        }

        $this->reset();
        $this->connectTime = time();

        $startLoop = false;
        if($this->loopInterface === null){
            $startLoop = true;
            $this->loopInterface = EventLoopFactory::create();
        }

        self::$httpClient = new HTTPClient($this->loopInterface);

        Manager::getRequest("/users/@me", function($data){
            $this->myInfo = json_decode($data, true);
        })->end();

        Manager::getRequest("/gateway/bot", function($data){
            $this->gwInfo = json_decode($data, true);
            if(isset($this->gwInfo["shards"])){
                $this->connectToGateway($this->gwInfo["shards"]);
            }else{
                $this->log("Unknown response from API");
                exit();
            }
        })->end();

        $slowMessageTimerID = bin2hex(random_bytes(16));
        $this->slowMessageTimerID = $slowMessageTimerID;
        $this->getLoopInterface()->addPeriodicTimer(6, function($timer) use ($slowMessageTimerID){
            if($slowMessageTimerID !== $this->slowMessageTimerID){
                $this->getLoopInterface()->cancelTimer($timer);
            }

            if(!empty($this->slowMessageQueue)){
                $message = array_shift($this->slowMessageQueue);
                if(is_array($message)){
                    call_user_func_array([$this, "sendShardMessage"], $message);
                }elseif(is_callable($message)){
                    call_user_func_array($message, []);
                }
            }
        });

        $cleanupTimerID = bin2hex(random_bytes(16));
        $this->cleanupTimerID = $cleanupTimerID;
        $this->getLoopInterface()->addPeriodicTimer(60, function($timer) use ($cleanupTimerID){
            if($cleanupTimerID !== $this->cleanupTimerID){
                $this->getLoopInterface()->cancelTimer($timer);
            }
            $this->doCleanup();
        });

        if($startLoop){
            $this->getLoopInterface()->run();
        }

        $this->lasthb = time();
    }

    public function getMyInfo(): array{
        return $this->myInfo;
    }

    public function disconnect(){
        $this->disconnecting = true;

        foreach($this->shards as &$item){
            $item["conn"]->close();
            unset($item["heartbeat_interval"]);
        }
        $this->reset();
    }

    public function doCleanup(){
        foreach($this->personChannels as $id => $data){
            if($data["time"] < (time() - 300)){
                Manager::getRequest("/channels/".$data["id"], null, "DELETE")->end();
            }
        }
    }

    private function connectToGateway($shards = 1){
        foreach($this->shards as &$item){
            $item["conn"]->close();
            unset($item["heartbeat_interval"]);
        }

        $this->shards = [];

        for($shard = 0; $shard < $shards; $shard++){
            $this->connectShard($shard);
        }
    }

    private function connectShard(int $shard){
        $connector = new RatchetConnector($this->getLoopInterface());

        $connector($this->gwInfo["url"]."/?v=6&encoding=json")->then(function(WebSocket $conn) use ($shard){
            $this->shards[$shard] = ["conn" => $conn, "seq" => null, "ready" => false];

            $conn->on("message", function(RatchetMessageInterface $msg) use ($shard, $conn){
                $this->doEmit("shard.message", [$shard, $msg]);
            });

            $conn->on("close", function($code = null, $reason = null) use ($shard){
                $this->doEmit("shard.closed", [$shard, $code, $reason]);
            });

        }, function(Exception $e) use ($shard){
            $this->getLoopInterface()->addTimer(30, function() use ($shard){
                $this->connectShard($shard);
            });
        });
    }

    public function sendShardMessage(int $shard, int $opcode, $data, $sequence = null, $eventName = null){
        $sendData = ["op" => $opcode, "d" => $data];
        if($opcode === 0){
            $sendData["s"] = $sequence;
            $sendData["t"] = $eventName;
        }

        $this->shards[$shard]["conn"]->send(json_encode($sendData));
    }

    public function gotMessage(int $shard, RatchetMessageInterface $msg){
        $this->shards[$shard]["sentHB"] = false;

        $data = json_decode($msg->getPayload(), true);
        $opcode = $data["op"];

        if(empty(Manager::getEventHandler()->listeners("opcode.".$opcode))){
            $this->log("Got Unknown Message on shard ".$shard." - ".$opcode);
        }

        switch($opcode){
            case 0:
                $this->doEmit("opcode.".$opcode, [$shard, $data]);
                break;
            default:
                $this->doEmit("opcode.".$opcode, [$shard, $opcode, $data]);
                break;
        }
    }

    public function gotInvalidSession(int $shard, int $opcode, array $data): void{
        $this->sendIdentify($shard);
    }

    public function gotReconnectRequest(int $shard, int $opcode, array $data): void{
        $this->sendIdentify($shard);
    }

    public function gotHello(int $shard, int $opcode, array $data){
        $this->shards[$shard]["heartbeat_interval"] = $data["d"]["heartbeat_interval"];
        $this->scheduleHeartbeat($shard);
        $this->sendIdentify($shard);
    }

    private function sendIdentify(int $shard){
        $identify["token"] = self::$token;
        $identify["properties"] = ["os" => PHP_OS, "browser" => "erkamkahriman/dcbotapi", "library" => "erkamkahriman/dcbotapi"];
        $identify["compress"] = false;
        $identify["shard"] = [$shard, $this->gwInfo["shards"]];
        if(isset($this->myInfo["Game"])){
            $identify["presence"] = ["game" => ["name" => $this->myInfo["Game"]["Name"], "type" => $this->myInfo["Game"]["Type"]]];
        }
        $this->slowMessageQueue[] = [$shard, 2, $identify];
    }

    private function scheduleHeartbeat(int $shard){
        if(!isset($this->shards[$shard]["heartbeat_interval"])){
            return;
        }

        $delay = $this->shards[$shard]["heartbeat_interval"];

        $timerID = bin2hex(random_bytes(16));
        $this->shards[$shard]["timerID"] = $timerID;

        $this->getLoopInterface()->addTimer($delay / 1000, function() use ($shard, $timerID){
            if(!isset($this->shards[$shard]["timerID"])){
                return;
            }
            if($this->shards[$shard]["timerID"] != $timerID){
                return;
            }
            if($this->shards[$shard]["sentHB"]){
                $this->shards[$shard]["conn"]->close();
            }else{
                $this->shards[$shard]["sentHB"] = true;
                $this->sendShardMessage($shard, 1, $this->shards[$shard]["seq"]);
                $this->scheduleHeartbeat($shard);
            }
        });
    }

    public function gotHeartBeat(int $shard, int $opcode, array $data){
    }

    public function gotHeartBeatAck(int $shard, int $opcode, array $data){
    }

    public function gotDispatch(int $shard, array $data){
        $this->shards[$shard]["seq"] = $data["s"];
        $event = $data["t"];
        $eventData = $data["d"];

        $eventName = "event.".$event;

        if(empty(Manager::getEventHandler()->listeners($eventName))){
            var_dump("Event not found: ".$event);
        }
        $eventHandler = Manager::getEventHandler();
        switch($eventName){
            case "event.MESSAGE_CREATE":
            case "event.MESSAGE_UPDATE":
                if(!isset($eventData["author"])) return;
                $channelId = $eventData["channel_id"];
                Manager::getRequest("/channels/".$channelId, function($channelData) use($eventData, $eventName, $eventHandler){
                    $messageEvent = new MessageEvent($eventData, json_decode($channelData, true));
                    $eventHandler->emit($eventName, [$messageEvent]);
                })->end();
                break;
            case "event.READY":
                $eventHandler->emit($eventName, [$shard, $data["d"]["guilds"]]);
                break;
            case "event.GUILD_CREATE":
            case "event.GUILD_UPDATE":
                $eventHandler->emit($eventName, [$data["d"]]);
                break;
            case "event.GUILD_MEMBER_ADD":
            case "event.GUILD_MEMBER_REMOVE":
                $eventHandler->emit($eventName, [$data["d"]]);
                break;
            default:
                $eventHandler->emit($eventName, [$shard, $event, $eventData]);
                break;
        }
    }

    public function noop(int $shard, string $event, array $data){
        switch($event){
            case "":
                $this->log($event);
                var_dump($data);
        }
    }

    public function handleReadyEvent(int $shard, array $guilds = []){
        $this->shards[$shard]["ready"] = true;
        foreach($guilds as $guild){
            $id = $guild["id"];
            Manager::getRequest("/guilds/".$id, function($data) use ($id){
                if(!isset($this->guilds[$id])){
                    $this->guilds[$id] = new Guild(json_decode($data, true));
                }
            })->end();
        }
    }

    public function handleGuildData(array $guildData): void{
        $this->guilds[$guildData["id"]] = new Guild($guildData);
    }

    public function handleGuildMemberAdd(array $data): void{
        $this->getGuildById($data["guild_id"])->addMember($data);
    }

    public function handleGuildMemberRemove($data){
        $this->getGuildById($data["guild_id"])->removeMember($data["user"]["id"]);
    }

    public function handleGuildDelete(string $id): void{
        if(isset($this->guilds[$id])){
            unset($this->guilds[$id]);
        }
    }

    public function handleChannelCreate(DiscordClient $client, int $shard, string $event, array $data){
        if($data["type"] == "0" && isset($data["guild_id"]) && isset($this->guilds[$data["guild_id"]])){
            $guildID = $data["guild_id"];
            $chanID = $data["id"];

            if(isset($this->guilds[$guildID]["channels"][$chanID])){
                return;
            }

            $this->guilds[$guildID]["channels"][$chanID] = [];
            $this->guilds[$guildID]["channels"][$chanID]["name"] = $data["name"];

            $this->doEmit("DiscordClient.message", ["Found new channel for server ".$this->guilds[$guildID]["name"]." (".$guildID.") on shard ".$shard.": ".$data["name"]." (".$chanID.")"]);
        }elseif($data["type"] == "1"){
            $person = $data["recipients"][0];
            if(isset($this->personChannels[$person["id"]])){
                return;
            }

            $this->personChannels[$person["id"]] = ["id" => $data["id"], "time" => time()];
            $this->doEmit("DiscordClient.message", ["Found new channel for person ".$person["username"]."#".$person["discriminator"]." (".$person["id"].") on shard ".$shard.": ".$data["id"]]);
        }
    }

    public function handleChannelDelete(DiscordClient $client, int $shard, string $event, array $data){
        if($data["type"] == "0" && isset($data["guild_id"]) && isset($this->guilds[$data["guild_id"]])){
            $guildID = $data["guild_id"];
            $chanID = $data["id"];
            unset($this->guilds[$guildID]["channels"][$chanID]);
        }elseif($data["type"] == "1"){
            $person = $data["recipients"][0];
            unset($this->personChannels[$person["id"]]);
        }
    }

    public function shardClosed(int $shard, $code = null, $reason = null){
        $this->log("Shard ".$shard." closed (".$code." - ".$reason.")");
        if(!$this->disconnecting){
            if($code == 4003 || $code == 4004){
                $this->ColorLog("Authentication error - not attempting to reconnect.", 2);
            }else{
                $this->getLoopInterface()->addTimer(3, function() use ($shard){
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
            if(!$shard["ready"]){
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

    public function validUser(): bool{
        return true;
    }

    public function getUserById(): User{
        return new User([]);
    }

    public function getChannelMessages(string $server, string $channel, callable $function){
        if(!$this->validChannel($server, $channel)){
            return;
        }
        Manager::getRequest("/channels/".$channel."/messages", $function)->end();
    }

    public function sendChannelMessage(string $server, string $channel, string $message){
        if(!$this->validChannel($server, $channel)){
            return;
        }

        $sendMessage = [];
        $sendMessage["content"] = $message;

        $data = json_encode($sendMessage);
        $headers = [];
        $headers["content-length"] = strlen($data);
        $headers["content-type"] = "application/json";

        Manager::getRequest("/channels/".$channel."/messages", null, "POST", $headers)->end($data);
    }

    public function setUsername(string $username): void{
        Manager::getRequest("/users/@me", null, "PATCH")->end(json_encode(["username" => $username]));
        $this->myInfo["username"] = $username;
    }

    public function getUsername(): string{
        return $this->myInfo["username"];
    }

    public function setGame(string $game, int $type = 0){
        $this->myInfo["Game"]["Name"] = $game;
        $this->myInfo["Game"]["Type"] = $type <= 2 ? $type : 0;
    }

    public function getGuildById(string $id): ?Guild{
        return isset($this->guilds[$id]) ? $this->guilds[$id] : null;
    }

    public function getGuilds(): array{
        return $this->guilds;
    }
}
