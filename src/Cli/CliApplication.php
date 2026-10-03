<?php 
/**
 * Copyright (c) 2025 SCHENCK Simon
 * 
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @copyright     Copyright (c) SCHENCK Simon
 *
 */

namespace SkankyDev\Cli;

use SkankyDev\Cli\Tools\ArgParser;
use SkankyDev\Config\Config;
use SkankyDev\Core\MasterFactory;
use SkankyDev\Utilities\Traits\CliMessage;
use SkankyDev\Utilities\Traits\StringFacility;

class CliApplication {

	use CliMessage, StringFacility;

	private array $arg = [];
	private array $commandsInfo = [];

	/**
	 * Initializes config, parses CLI arguments and starts execution.
	 * @param array $info raw argv passed by the `craft` entry point
	 */
	public function __construct(array $info){
		Config::initConf();
		$this->arg = ArgParser::_pars($info);
		$this->run();

	}

	/**
	 * Registers available commands, resolves the requested command and executes it.
	 * Displays help if no command is provided or if the command is `help`.
	 */
	public function run(): void {
		$this->autoRegister();
		if(!isset($this->arg['command']) || $this->arg['command'] == 'help'){
			$this->help();
			exit;
		}
		$target = $this->findCommand($this->arg['command']);

		if(!$target){
			$this->error('Command Not Found');
			exit;
		}
		$arg = $this->arg;
		unset($arg['command']);
		$command = MasterFactory::_make($target['class']);
		$command->run($arg);
	}

	/**
	 * Auto-discovers commands from all modules declared in config and from SkankyDev itself.
	 * Scans each `Command/` folder, skips `MasterCommand.php`,
	 * and registers the info of every valid command class found.
	 */
	public function autoRegister(): void {
		$modules = Config::get('Module');
		$modules[] = 'SkankyDev';

		foreach ($modules as $key => $module) {
			$dir = ($module === 'SkankyDev' ? SKANKY_FOLDER : SRC_FOLDER.DS.$module).DS.'Command';
			if(!is_dir($dir)){
				continue;
			}
			$files = scandir($dir);
			foreach($files as $file){
				if(in_array($file, ['.','..','MasterCommand.php'])){
					continue;
				}
				$name = str_replace('.php','',$file);
				$className = $module.'\\Command\\'.$name;
				if(class_exists($className)){
					$info = $className::getInfo();
					$this->commandsInfo[$name] = $info;
				}
			}
		}
	}

	/**
	 * Finds a registered command by its signature.
	 * @param  string      $signature CLI signature e.g. `queue:work`
	 * @return array|null  command info array or null if not found
	 */
	public function findCommand(string $signature): ?array {
		foreach ($this->commandsInfo as $name => $command) {
			if($signature == $command['signature']){
				return $command;
			}
		}
		return null;

	}

	/**
	 * Displays the help screen with the ASCII logo and the list of available commands.
	 */
	public function help(): void {
		$this->info("");
		$this->success("╭──────────────────────────────────────────────────────╮");
		$this->success("│    _____ __ __            __             __          │");
		$this->success("│   / ___// //_/___ _____  / /____  ______/ /__ _   __ │");
		$this->success("│   \__ \/ ,< / __ `/ __ \/ //_/ / / / __  / _ \ | / / │");
		$this->success("│  ___/ / /| / /_/ / / / / ,< / /_/ / /_/ /  __/ |/ /  │");
		$this->success("│ /____/_/ |_\__,_/_/ /_/_/|_|\__, /\__,_/\___/|___/   │");
		$this->success("│                            /____/                    │");
		$this->success("│                                                      │");
		$this->success("╰──────────────────────────────────────────────────────╯");
		$this->text("");
		$this->text(bleu('SkankyDev').' version '.jaune(Config::get('skankydev.version')));
		$this->text("");
		$this->warning("Usage:");
		$this->text("");
		$this->text("\tcommand [options] [arguments]");
		$this->text("");
		$this->text(jaune('Available commands:'));
		$this->text("");


		foreach ($this->commandsInfo as $name => $cmd) {
			$this->cmdHelp($cmd['signature'],$cmd['help']);
		}
		$this->text("");
		$this->text("");
	}

	/**
	 * Renders a single command help line in the command list.
	 * @param string $signature command signature e.g. `queue:work`
	 * @param string $help      short description of the command
	 */
	public function cmdHelp(string $signature, string $help): void {
		echo '  '.vert(str_pad($signature, 20)) . str_pad($help, 40)."\n";
	}
}