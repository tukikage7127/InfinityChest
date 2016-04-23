<?php

namespace IC;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\block\Block;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\network\protocol\AddItemEntityPacket;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\scheduler\Task;

class IC extends PluginBase implements Listener {

    function onEnable ()
    {
        $plugin = "InfinityChest_PE";
        $this->getLogger()->info("§a".$plugin."を読み込みこんだめう！ §9By tukikage7127");
        $this->getLogger()->info("§c".$plugin."を二次配布するのは禁止するズラ");
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
                
        if(!file_exists($this->getDataFolder())) {//configを入れるフォルダが有るかチェック
            mkdir($this->getDataFolder(), 0744, true);//なければフォルダを作成
        }
        $this->C = new Config($this->getDataFolder() . "Config.yml", Config::YAML);
        $this->L = new Config($this->getDataFolder() . "Locks.yml", Config::YAML);
    }

    function onDisable()
    {
    	$this->C->save();
    	$this->L->save();
    }

    function onCommand(CommandSender $sender, Command $command, $label, array $args)
	{
		if ($command->getName() === "ic") {
			if (!$sender instanceof Player) {
				$sender->sendMessage("> コンソールからは実行できません");
				return false;
			}
			$name = $sender->getName();
			if (!isset($args[0])) {
				$sender->sendMessage("使用法: /ic <lock/unlock/public>");
			}else{
				switch ($args[0]) {
					case "lock":
						$this->data[$name] = ["lock" => true,"unlock" => false,"public" => false];
						Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this, "setData"], [$name,"lock",false]),60);
						$sender->sendMessage("[IC] §bロックしたい無限チェストをタップしてください");
					break;

					case "unlock":
						$this->data[$name] = ["lock" => false,"unlock" => true,"public" => false];
						Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this, "setData"], [$name,"unlock",false]),60);
						$sender->sendMessage("[IC] §bアンロックしたい無限チェストをタップしてください");
					break;

					case "public":
						$this->data[$name] = ["lock" => false,"unlock" => false,"public" => true];
						Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this, "setData"], [$name,"public",false]),60);
						$sender->sendMessage("[IC] §b公開モードにしたい無限チェストをタップしてください");
					break;
					
					default:
						$sender->sendMessage("[IC] §cそんなコマンドはないよ！");
					break;
				}
			}
		}
	}
        
    function onJoin (PlayerJoinEvent $event)
    {
    	$name = $event->getPlayer()->getName();
    	$this->data[$name] = ["lock" => false,"unlock" => false,"public" => false];
    	$c = $this->C->getAll();
        $this->addData();
	}

	function onPlace (BlockPlaceEvent $event)
	{
		$block = $event->getBlock();
		$player = $event->getPlayer();
		if ($block->getId() === 52) $player->sendMessage("[IC] §b入れたいアイテムを手にもってブロックをタップしてください");
	}

	function onBreak (BlockBreakEvent $event)
	{
		$c = $this->C->getAll();
		$l = $this->L->getAll();
		$block = $event->getBlock();
		$x = $block->x;
        $y = $block->y;
        $z = $block->z;
		$player = $event->getPlayer();
		$level = $player->level;
		$v = $x.":".$y.":".$z.":".$level->getName();
		if ($block->getId() === 52) {
			if (isset($c[$v])) {
				if (isset($l[$v])) {
					if ($l[$v]["lock"]) {
						if ($l[$v]["name"] != $player->getName()) {
							$player->sendMessage("[IC] §cこの無限チェストはロックされています");
							$event->setCancelled();
							return false;
						}else{
							$player->sendMessage("[IC] §aこの無限チェストをアンロックしました");
						}
					}elseif ($l[$v]["public"]) {
						if ($l[$v]["name"] != $player->getName()) {
							$player->sendMessage("[IC] §cこの無限チェストは公開モードになっています");
							$event->setCancelled();
						}else{
							$player->sendMessage("[IC] §aこの無限チェスト公開モードを解除しました");
						}
					}
				}
				if ($event->isCancelled()) return false;
				$player->sendMessage("[IC] §b無限チェストを破壊しました");
				$item = Item::get($c[$v]["id"],$c[$v]["meta"],$c[$v]["count"]);
				$level->dropItem(new Vector3($x,$y,$z),$item);
				if ($player->getGamemode() === 0) $level->dropItem(new Vector3($x,$y,$z),Item::get(52));
				foreach (Server::getInstance()->getOnlinePlayers() as $player) {
					$this->close($player,$c[$v]["textid"]);
        			$this->close($player,$c[$v]["itemid"]);
        		}
				$this->C->remove($v);
				$this->L->remove($v);
			}
		}
	}

    function onTap (PlayerInteractEvent $event)
    {
    	$c = $this->C->getAll();
    	$l = $this->L->getAll();
        $player = $event->getPlayer();
        $name = $player->getName();
        $block = $event->getBlock();
        $in = $player->getInventory();
        $item = $in->getItemInHand();
        $level = $player->getLevel();
        $x = $block->x;
        $y = $block->y;
        $z = $block->z;
        $level = $player->level;
        $v = $x.":".$y.":".$z.":".$level->getName();
        if ($block->getId() === 52) {
        	$iid = $item->getId();
			$imeta = $item->getDamage();
			$icount = $item->getCount();
        	if (isset($c[$v])) {
        		if ($this->data[$name]["lock"]) {
        			if (!isset($l[$v])) {
        				$player->sendMessage("[IC] §aこの無限チェストをロックしました");
        				$this->L->set($v,["lock" => true,"public" => false,"name" => $name]);
        			}else{
        				if ($l[$v]["public"]) {
        					if ($l[$v]["name"] == $name) {
        						$this->L->set($v,["lock" => true,"public" => false,"name" => $name]);
        						$player->sendMessage("[IC] §aこの無限チェストをロックしました");
        					}else{
        						$player->sendMessage("[IC] §cこの無限チェストは公開モードになっているためロックできません");
        					}
        				}elseif ($l[$v]["lock"]) {
        					if ($l[$v]["name"] == $name) {
        						$player->sendMessage("[IC] §b既にこの無限チェストは貴方によってロックされています");
        					}else{
        						$player->sendMessage("[IC] §cこの無限チェストはほかの人にロックされています");
        					}
        				}
        			}
        			$this->data[$name]["lock"] = false;
				}elseif ($this->data[$name]["unlock"]) {
					if (isset($l[$v]) and $l[$v]["lock"]) {
						if ($l[$v]["name"] == $name) {
							$this->L->set($v,["lock" => false,"public" => false, "name" => $name]);
							$player->sendMessage("[IC] §aこの無限チェストをアンロックしました");
						}else{
							$player->sendMessage("[IC] §cほかの人がロックした無限チェストをアンロックすることはできません");
						}
					}else{
						$player->sendMessage("[IC] §cこの無限チェストはロックされていません");
					}
					$this->data[$name]["unlock"] = false;
				}elseif ($this->data[$name]["public"]) {
					if (!isset($l[$v])) {
        				$player->sendMessage("[IC] §aこの無限チェストを公開モードにしました");
        				$this->L->set($v,["lock" => false,"public" => true,"name" => $name]);
        			}else{
        				if ($l[$v]["lock"]) {
        					if ($l[$v]["name"] == $name) {
        						$player->sendMessage("[IC] §c公開モードにするにはこの無限チェストをアンロックする必要があります");
        					}else{
        						$player->sendMessage("[IC] §cこの無限チェストはロックされているため公開モードにできません");
        					}
        				}elseif ($l[$v]["public"]) {
        					if ($l[$v]["name"] == $name) {
        						$player->sendMessage("[IC] §b既にこの無限チェストは貴方によって公開モードにされています");
        					}else{
        						$player->sendMessage("[IC] §cこの無限チェストはほかの人が公開モードにしています");
        					}
        				}
        			}
					$this->data[$name]["public"] = false;
				}elseif (!$player->isSneaking()) {
					if (isset($l[$v]) and $l[$v]["lock"]) {
						if ($l[$v]["name"] != $name) {
							$player->sendMessage("[IC] §cこの無限チェストはロックされています");
							$event->setCancelled();
							return false;
						}
					}
        			if ($iid === $c[$v]["id"] and $imeta === $c[$v]["meta"]) {
        				if ($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
        					$in->setItemInHand(Item::get(0,0,0));
        					$plus = $icount;
        				}else{
        					$in->setItemInHand(Item::get($iid,$imeta,$icount-1));
        					$plus = 1;
        					$event->setCancelled();
        				}
        				$this->C->set($v,["id" => $c[$v]["id"], "meta" => $c[$v]["meta"], "count" => $c[$v]["count"]+$plus, "textid" => $c[$v]["textid"], "itemid" => $c[$v]["itemid"]]);
        				$this->addText($v);
        			}
        		}else{
        			if (isset($l[$v]) and $l[$v]["lock"]) {
						if ($l[$v]["name"] != $name) {
							$player->sendMessage("[IC] §cこの無限チェストはロックされています");
							$event->setCancelled();
							return false;
						}
					}
        			$item = Item::get($c[$v]["id"],$c[$v]["meta"]);
        			if ($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK) {
        				if ($c[$v]["count"] < $item->getMaxStackSize()) $count = $c[$v]["count"];
        				else $count = $item->getMaxStackSize();
        				$in->addItem(Item::get($c[$v]["id"],$c[$v]["meta"],$count));
        				$minus = $count;
        			}else{
        				if ($c[$v]["count"] < 1) return false;
        				$in->addItem($item);
        				$minus = 1;
        				$event->setCancelled();
        			}
        			$this->C->set($v,["id" => $c[$v]["id"], "meta" => $c[$v]["meta"], "count" => $c[$v]["count"]-$minus, "textid" => $c[$v]["textid"], "itemid" => $c[$v]["itemid"]]);
        			$this->addText($v);
        		}
        	}else{
        		if ($iid != 0) {
        			$in->setItemInHand(Item::get(0,0,0));
        			$event->setCancelled();
        			$this->C->set($v,["id" => $iid, "meta" => $imeta, "count" => $icount, "textid" => mt_rand(100000, 10000000), "itemid" => mt_rand(100000, 10000000)]);
        			$this->addText($v);
        			$this->addItem($v);
        		}
        	}
        }
    }

    function onTeleport (EntityTeleportEvent $event)
    {
    	$c = $this->C->getAll();
    	$entity = $event->getEntity();
    	if ($entity instanceof Human or $entity instanceof Player) 
    		Server::getInstance()->getScheduler()->scheduleDelayedTask(new Callback([$this, "addData"]),1);
    }

    function addText ($v)
	{
		$c = $this->C->getAll();
		$vs = explode(":",$v);
		$pk = new AddEntityPacket();
		$pk->eid = $c[$v]["textid"];
		$pk->type = 64;
		$pk->x = $vs[0]+0.5;
		$pk->y = $vs[1]+0.5-0.75;
		$pk->z = $vs[2]+0.5;
		$pk->speedX = 0;
		$pk->speedY = 0;
		$pk->speedZ = 0;
		$pk->yaw = 0;
		$pk->pitch = 0;
		$pk->item = 0;
		$pk->metadata = [Entity::DATA_FLAGS => [Entity::DATA_TYPE_BYTE, 1 << Entity::DATA_FLAG_INVISIBLE], Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $c[$v]["count"]."個"], Entity::DATA_SHOW_NAMETAG => [Entity::DATA_TYPE_BYTE, 1], Entity::DATA_NO_AI => [Entity::DATA_TYPE_BYTE, 1]];
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
        	if ($player->level->getName() == $vs[3]) {
        		$player->dataPacket($pk);
        	}else{
        		$this->close($player,$c[$v]["textid"]);
        	}
        }
	}

	function addItem ($v)
	{
		$c = $this->C->getAll();
		$vs = explode(":",$v);
		$pk = new AddItemEntityPacket();
		$pk->eid = $c[$v]["itemid"];
		$pk->item = Item::get($c[$v]["id"],$c[$v]["meta"]);
		$pk->x = $vs[0]+0.5;
		$pk->y = $vs[1];
		$pk->z = $vs[2]+0.5;
		$pk->yaw = 0;
		$pk->pitch = 0;
		$pk->roll = 0;
		foreach (Server::getInstance()->getOnlinePlayers() as $player) {
        	if ($player->level->getName() == $vs[3]) {
        		$player->dataPacket($pk);
        	}else{
        		$this->close($player,$c[$v]["itemid"]);
        	}
        }
	}

	function close ($player,$eid)
	{
		$pk = new RemoveEntityPacket();
		$pk->eid = $eid;
		$player->dataPacket($pk);
	}

	function setData ($name,$type,$value = false)
	{
		if (isset($this->data[$name])) {
			$this->data[$name][$type] = $value;
		}
	}

	public function getId ($v3,$level)
	{
		$c = $this->C->getAll();
		$v = $v3->x.":".$v3->y.":".$v3->z.":".$level->getName();
		$id = isset($c[$v]) ? $c[$v]["id"] : false;
		return $id;
	}

	public function getDamage ($v3,$level)
	{
		$c = $this->C->getAll();
		$v = $v3->x.":".$v3->y.":".$v3->z.":".$level->getName();
		$meta = isset($c[$v]) ? $c[$v]["meta"] : false;
		return $meta;
	}

	public function getCount ($v3,$level)
	{
		$c = $this->C->getAll();
		$v = $v3->x.":".$v3->y.":".$v3->z.":".$level->getName();
		$count = isset($c[$v]) ? $c[$v]["count"] : false;
		return $count;
	}

	public function setCount ($v3,$level,$count)
	{
		$c = $this->C->getAll();
		$v = $v3->x.":".$v3->y.":".$v3->z.":".$level->getName();
		if (isset($c[$v])) {
			if (!is_int($count)) return false;
			$this->C->set($v,["id" => $c[$v]["id"], "meta" => $c[$v]["meta"], "count" => $count, "textid" => $c[$v]["textid"], "itemid" => $c[$v]["itemid"]]);
		}
	}

	public function addCount ($v3,$level,$count)
	{
		$c = $this->C->getAll();
		$v = $v3->x.":".$v3->y.":".$v3->z.":".$level->getName();
		if (isset($c[$v])) {
			if (is_numeric($count) and !is_float($count)) return false;
			$this->C->set($v,["id" => $c[$v]["id"], "meta" => $c[$v]["meta"], "count" => $c[$v]["count"]+$count, "textid" => $c[$v]["textid"], "itemid" => $c[$v]["itemid"]]);
        	$this->addText($v);
		}
	}

	function addData ()
	{
		$c = $this->C->getAll();
		foreach ($c as $v => $value) {
        	$this->addText($v);
        	$this->addItem($v);
        }
	}
}

class Callback extends Task {

	public function __construct(callable $callable, array $args = [])
    {
        $this->callable = $callable;
        $this->args = $args;
        $this->args[] = $this;
    }

    public function getCallable()
    {
        return $this->callable;
    }
        
    public function onRun ($tick)
    {
    	call_user_func_array($this->callable, $this->args);
    }
}