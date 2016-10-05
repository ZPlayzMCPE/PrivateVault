<?php
namespace PrivateVaults;

use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\item\ItemBlock;
use pocketmine\permission\Permission;

class main extends PluginBase implements Listener {
	
	public $using = array();
	
	public function onEnable() {
		$this->getServer()->getLogger()->info(TextFormat::GREEN."<<PlayerVault>> ENABLED");
		$this->saveDefaultConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		@mkdir($this->getDataFolder() . "players/");
		for($i = 0; $i < 4; $i++) {
			$vaultperms = new Permission("vault.use." . $i, "PrivateVaults permission", "default");
			$this->getServer()->getPluginManager()->addPermission($vaultperms);
		}
	}
	
	public function onDisable() {
		$this->getServer()->getLogger()->info(TextFormat::GREEN."<<PlayerVault>> DISABLED");
	}

	public function onJoin(PlayerJoinEvent $event) {
		$this->using[strtolower($event->getPlayer()->getName())] = null;
	}

	public function hasPrivateVault($player) {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);
		return is_file($this->getDataFolder() . "players/" . $player . ".yml");
	}

	public function createVault($player, $number) {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);
		$cfg = new Config($this->getDataFolder() . "players/" . $player . ".yml", Config::YAML);
		$cfg->set("items", array());
		for ($i = 0; $i < 26; $i++) {
			$cfg->setNested("$number.items." . $i, array(0, 0, 0, array()));
		}
		$cfg->save();
	}

	public function loadVault(Player $player, $number) {
		$x=$player->getX();
		$y=$player->getY() - 3;
		$z=$player->getZ();
		$player->getLevel()->setBlock(new Vector3($x, $y, $z), Block::get(54));
		$chest = new Chest($player->getLevel()->getChunk($x >> 4, $z >> 4, true), new CompoundTag(false, array(new IntTag("x", $x), new IntTag("y", $y), new IntTag("z", $z), new StringTag("id", Tile::CHEST))));
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);
		$cfg = new Config($this->getDataFolder() . "players/" . $player . ".yml", Config::YAML);
		$chest->getInventory()->clearAll();
		for ($i = 0; $i < 26; $i++) {
			$ite = $cfg->getNested("$number.items." . $i);
			$item = Item::get($ite[0]);
			$item->setDamage($ite[1]);
			$item->setCount($ite[2]);
			foreach ($ite[3] as $key => $en) {
				$enchantment = Enchantment::getEnchantment($en[0]);
				$enchantment->setLevel($en[1]);
				$item->addEnchantment($enchantment);
			}
			$chest->getInventory()->setItem($i, $item);
		}
		return $chest->getInventory();
	}

	public function onInventoryClose(InventoryCloseEvent $event) {
		$inventory = $event->getInventory();
		$player = $event->getPlayer();
		if($inventory instanceof ChestInventory) {
			if($this->using[strtolower($player->getName())] !== null) {
				if($player instanceof Player) {
					$player = $player->getName();
				}
				$player = strtolower($player);
				$cfg = new Config($this->getDataFolder() . "players/" . $player . ".yml", Config::YAML);
				for ($i = 0; $i < 26; $i++) {
					$item = $inventory->getItem($i);
					$id = $item->getId();
					$damage = $item->getDamage();
					$count = $item->getCount();
					$enchantments = $item->getEnchantments();
					$ens = array();
					foreach ($enchantments as $en) {
						$ide = $en->getId();
						$level = $en->getLevel();
						array_push($ens, array($ide, $level));
					}
					$number = $this->using[strtolower($event->getPlayer()->getName())];
					$cfg->setNested("$number.items." . $i, array($id, $damage, $count, $ens));
					$cfg->save();
				}
				$realChest = $inventory->getHolder();
				$event->getPlayer()->getLevel()->setBlock(new Vector3($realChest->getX(), 128, $realChest->getZ()), Block::get(Block::AIR));
				$this->using[strtolower($event->getPlayer()->getName())] = null;
			}
		}
	}

	public function saveVault($player, $inventory, $number) {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);
		
		if($inventory instanceof ChestInventory) {
			$cfg = new Config($this->getDataFolder() . "players/" . $player . ".yml", Config::YAML);
			for ($i = 0; $i < 26; $i++) {
				$item = $inventory->getItem($i);
				$id = $item->getId();
				$damage = $item->getDamage();
				$count = $item->getCount();
				$enchantments = $item->getEnchantments();
				$ens = array();
				
				foreach ($enchantments as $en) {
					$id = $en->getId();
					$level = $en->getLevel();
					array_push($ens, array($id, $level));
				}
				
				$cfg->setNested("$number.items." . $i, array($id, $damage, $count, $ens));
				$cfg->save();
			}
			
			$realChest = $inventory->getHolder();
			$realChest->getLevel()->setBlock(new Vector3($realChest->getX(), 128, $realChest->getZ()), Block::get(Block::AIR));
		}
	}

	public function onQuit(PlayerQuitEvent $event) {
		if($this->using[strtolower($event->getPlayer()->getName())] !== null) {
			$chest = $event->getPlayer()->getLevel()->getTile(new Position($event->getPlayer()->x, $event->getPlayer()->y, $event->getPlayer()->z));
			if($chest instanceof Chest) {
				$inv = $chest->getInventory();
				$this->saveVault($event->getPlayer(), $inv, $this->using[strtolower($event->getPlayer()->getName())]);
				unset($this->using[strtolower($event->getPlayer()->getName())]);
			}
		}
	}

	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
		if($sender instanceof Player) {
			switch ($cmd->getName()) {
				case "vault":
					if(!empty($args[0])) {
						if($args[0] === "info") {
							$sender->sendMessage("§6<§2<§7PrivateVaults§2>§6>§4 Made by Daniktheboss");
							return true;
						}
					}
					
					if(!empty($args[0])) {
						if($args[0] === "Help") {
							$sender->sendMessage("§6<§2<§7PrivateVaults§2>§6>§4 Made by Daniktheboss");
							return true;
						}
					}
					
					if($this->hasPrivateVault($sender)) {
						if(empty($args[0])) {
							if($sender->hasPermission("vault.use.1")) {
								$args[0] = 1;
								$sender->addWindow($this->loadVault($sender, 1));
								$sender->sendMessage("§6<§2<§ePrivateVaults§2>§6>§a Please run /vault again to open the Vault.");
								$this->using[strtolower($sender->getName())] = (int)$args[0];
								return true;
							}else {
								$sender->sendMessage("§6<§2<§ePrivateVaults§2>§6>§a §cYou do not have permission to open that vault.");
								return true;
							}
						}else {
							if($args[0] < 1 || $args[0] > 3) {
								$sender->sendMessage("§6<§2<§ePrivateVaults§2>§6>§a Usage: /vault [0-3]");
								return true;
							}else {
								if($sender->hasPermission("vault.use." . $args[0])) {
									$sender->addWindow($this->loadVault($sender, $args[0]));
									$sender->sendTip("§aOpening Vault...");
									$this->using[strtolower($sender->getName())] = (int)$args[0];
									return true;
								}else {
									$sender->sendMessage("§6<§2<§ePrivateVaults§2>§6>§a You do not have permission to open that vault.");
									return true;
								}
							}
						}
					}else {
						$sender->sendMessage("§6<§2<§ePrivateVaults§2>§6>§a Creating Vault...");
						$sender->sendMessage("§6<§2<§ePrivateVaults§2>§6>§a Remeber, you get 3 free vaults");
						for($i = 0; $i < 4; $i++) {
							$this->createVault($sender, $i);
						}
						$sender->sendMessage("§6<§2<§ePrivateVaults§2>§6>§a Vault created, run the command again to open it!");
						return true;
					}
				}
			}
		return true;
	}
}
