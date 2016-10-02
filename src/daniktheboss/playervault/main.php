<?php
namespace Daniktheboss\playervault;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\command;
use pocketmine\utils\TextFormat;
use pocketmine\level\Level;

class main extends PluginBase implements Listener {
	
	public function onEnable(){
		$this->getLogger()->info("PlayerVault has Loaded!");
		@mkdir($this->getDataFolder());
		@mkdir($this->getDataFolder() . "Vaults/");
		$this->saveResources('config.yml');
	}	
	public function onDisable(){
		$this->getLogger()->info("PlayerVault has been disabled!");
	}
	public function hasPrivateVault($player) {
		if($player instanceof Player) {
			$player = $player->getName();
		}
		$player = strtolower($player);
		return is_file($this->getDataFolder() . "Vaults/" . $player . ".yml");
	}
}
