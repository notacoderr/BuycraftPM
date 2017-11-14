<?php

namespace Buycraft\PocketMine\Execution;

use Buycraft\PocketMine\BuycraftPlugin;
use Buycraft\PocketMine\PluginApi;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class ImmediateExecutionRunner extends AsyncTask{
	private $pluginApi;

	/**
	 * ImmediateExecutionRunner constructor.
	 * @param PluginApi $pluginApi
	 */
	public function __construct(PluginApi $pluginApi){
		$this->pluginApi = $pluginApi;
	}

	/**
	 * Actions to execute when run
	 *
	 * @return void
	 */
	public function onRun(){
		$response = $this->pluginApi->basicGet("/queue/offline-commands");
		$this->setResult($response->commands);
	}

	public function onCompletion(Server $server){
		foreach($this->getResult() as $command){
			BuycraftPlugin::getInstance()->getCommandExecutionTask()->queue($command, $command->player->name, false);
		}
	}
}