<?php

namespace Buycraft\PocketMine;

use Buycraft\PocketMine\Commands\BuycraftCommand;
use Buycraft\PocketMine\Execution\CommandExecutor;
use Buycraft\PocketMine\Execution\DeleteCommandsTask;
use Buycraft\PocketMine\Execution\DuePlayerCheck;
use Buycraft\PocketMine\Util\AnalyticsSend;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;

class BuycraftPlugin extends PluginBase{
	private static $instance;
	private $pluginApi;
	private $commandExecutionTask;
	private $deleteCommandsTask;
	private $serverInformation;
	private $allDue = array();

	/**
	 * @return BuycraftPlugin
	 */
	public static function getInstance(){
		return self::$instance;
	}

	public function onEnable(){
		// Ensure cURL is available and supports SSL.
		if(!extension_loaded("curl")){
			$this->getLogger()->error("BuycraftPM requires the curl extension to be installed with SSL support. Halting...");
			return;
		}

		$version = curl_version();
		$ssl_supported = ($version['features'] & CURL_VERSION_SSL);
		if(!$ssl_supported){
			$this->getLogger()->error("BuycraftPM requires the curl extension to be installed with SSL support. Halting...");
			return;
		}

		self::$instance = $this;

		$this->saveDefaultConfig();

		$secret = $this->getConfig()->get('secret');
		if($secret){
			$api = new PluginApi($secret, $this->getDataFolder());
			try{
				$this->verifyInformation($api);
				$this->pluginApi = $api;
				$this->startInitialTasks();
			}catch(\Exception $e){
				$this->getLogger()->warning("Unable to verify information");
				$this->getLogger()->logException($e);
			}
		}else{
			$this->getLogger()->info("Looks like this is your first time using Buycraft. Set up your server by using 'buycraft secret <key>'.");
		}

		$this->getServer()->getPluginManager()->registerEvents(new BuycraftListener(), $this);
		$this->getServer()->getCommandMap()->register("buycraft", new BuycraftCommand($this));
	}

	private function verifyInformation(PluginApi $api){
		$this->serverInformation = $api->basicGet("/information");

		// Nag if the store is in online mode
		if($this->serverInformation->account->online_mode){
			$this->getLogger()->warning("Your Buycraft store is set to online mode. As Minecraft Pocket Edition " .
				"has no username authentication, this is likely a mistake.");
			$this->getLogger()->warning("This message is safe to ignore, however Buycraft strongly recommends you use " .
				"an offline mode store.");
		}
	}

	private function startInitialTasks(){
		$this->commandExecutionTask = new CommandExecutor();
		$this->getScheduler()->scheduleRepeatingTask($this->commandExecutionTask, 1);
		$this->deleteCommandsTask = new DeleteCommandsTask($this->pluginApi);
		$this->getScheduler()->scheduleRepeatingTask($this->deleteCommandsTask, 20);
		Server::getInstance()->getAsyncPool()->submitTask(new DuePlayerCheck($this->pluginApi, true));

		AnalyticsSend::sendAnalytics($this);
	}

	public function onDisable(){
		$this->saveConfig();
	}

	/**
	 * @return PluginApi
	 */
	public function getPluginApi(){
		return $this->pluginApi;
	}

	/**
	 * @return CommandExecutor
	 */
	public function getCommandExecutionTask(){
		return $this->commandExecutionTask;
	}

	/**
	 * @return DeleteCommandsTask
	 */
	public function getDeleteCommandsTask(){
		return $this->deleteCommandsTask;
	}

	/**
	 * @return array
	 */
	public function getAllDue() : array{
		return $this->allDue;
	}

	/**
	 * @param array $allDue
	 */
	public function setAllDue(array $allDue){
		// Because PHP logic.
		$this->allDue = (array) $allDue;
	}

	/**
	 * Attempts to change the current API object. Will not always work, but due to the "design" of threaded PHP, this
	 * is the only way we can accomplish this.
	 * @param $newApi PluginApi
	 * @param $information mixed
	 */
	public function changeApi(PluginApi $newApi, $information){
		$this->pluginApi = $newApi;
		$this->getScheduler()->cancelTasks($this);
		$this->startInitialTasks();

		// change information if required (for secret command)
		if($information !== NULL){
			$this->serverInformation = $information;
		}
	}

	/**
	 * @return mixed
	 */
	public function getServerInformation(){
		return $this->serverInformation;
	}
}