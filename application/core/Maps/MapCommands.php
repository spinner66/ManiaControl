<?php

namespace ManiaControl\Maps;

use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Icons64x64_1;
use FML\Controls\Quads\Quad_UIConstruction_Buttons;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\IconManager;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use Maniaplanet\DedicatedServer\Xmlrpc\Exception;

/**
 * Class offering commands to manage maps
 *
 * @author steeffeen & kremsy
 */
class MapCommands implements CommandListener, ManialinkPageAnswerListener, CallbackListener {
	/**
	 * Constants
	 */
	const ACTION_OPEN_MAPLIST = 'MapCommands.OpenMapList';
	const ACTION_OPEN_XLIST   = 'MapCommands.OpenMXList';
	const ACTION_RESTART_MAP  = 'MapCommands.RestartMap';
	const ACTION_SKIP_MAP     = 'MapCommands.NextMap';

	/**
	 * Private Properties
	 */
	private $maniaControl = null;

	/**
	 * Create MapCommands instance
	 *
	 * @param \ManiaControl\ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->initActionsMenuButtons();

		// Register for admin chat commands
		$this->maniaControl->commandManager->registerCommandListener('nextmap', $this, 'command_NextMap', true);
		$this->maniaControl->commandManager->registerCommandListener('restartmap', $this, 'command_RestartMap', true);
		$this->maniaControl->commandManager->registerCommandListener('addmap', $this, 'command_AddMap', true);
		$this->maniaControl->commandManager->registerCommandListener(array('removemap', 'removethis', 'erasemap', 'erasethis'), $this, 'command_RemoveMap', true);
		$this->maniaControl->commandManager->registerCommandListener(array('shufflemaps', 'shuffle'), $this, 'command_ShuffleMaps', true);

		// Register for player chat commands
		$this->maniaControl->commandManager->registerCommandListener('nextmap', $this, 'command_showNextMap');
		$this->maniaControl->commandManager->registerCommandListener(array('maps', 'list'), $this, 'command_List');
		$this->maniaControl->commandManager->registerCommandListener(array('xmaps', 'xlist'), $this, 'command_xList');

		// Menu Buttons
		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_OPEN_XLIST, $this, 'command_xList');
		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_OPEN_MAPLIST, $this, 'command_List');
		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_RESTART_MAP, $this, 'command_RestartMap');
		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_SKIP_MAP, $this, 'command_NextMap');
	}

	/**
	 * Add all Actions Menu Buttons
	 */
	private function initActionsMenuButtons() {
		// Menu Open xList
		$itemQuad = new Quad();
		$itemQuad->setImage($this->maniaControl->manialinkManager->iconManager->getIcon(IconManager::MX_ICON));
		$itemQuad->setImageFocus($this->maniaControl->manialinkManager->iconManager->getIcon(IconManager::MX_ICON_MOVER));
		$itemQuad->setAction(self::ACTION_OPEN_XLIST);
		$this->maniaControl->actionsMenu->addPlayerMenuItem($itemQuad, 5, 'Open MX List');

		// Menu Open List
		$itemQuad = new Quad_Icons64x64_1();
		$itemQuad->setSubStyle($itemQuad::SUBSTYLE_ToolRoot);
		$itemQuad->setAction(self::ACTION_OPEN_MAPLIST);
		$this->maniaControl->actionsMenu->addPlayerMenuItem($itemQuad, 10, 'Open MapList');

		// Menu RestartMap
		$itemQuad = new Quad_UIConstruction_Buttons();
		$itemQuad->setSubStyle($itemQuad::SUBSTYLE_Reload);
		$itemQuad->setAction(self::ACTION_RESTART_MAP);
		$this->maniaControl->actionsMenu->addAdminMenuItem($itemQuad, 10, 'Restart Map');

		// Menu NextMap
		$itemQuad = new Quad_Icons64x64_1();
		$itemQuad->setSubStyle($itemQuad::SUBSTYLE_ArrowFastNext);
		$itemQuad->setAction(self::ACTION_SKIP_MAP);
		$this->maniaControl->actionsMenu->addAdminMenuItem($itemQuad, 20, 'Skip Map');
	}

	/**
	 * Shows which map is the next
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function command_ShowNextMap(array $chat, Player $player) {
		$nextQueued = $this->maniaControl->mapManager->mapQueue->getNextQueuedMap();
		if ($nextQueued != null) {
			/** @var Player $requester */
			$requester = $nextQueued[0];
			/** @var Map $map */
			$map = $nextQueued[1];
			$this->maniaControl->chat->sendInformation("Next map is $<" . $map->name . "$> from $<" . $map->authorNick . "$> requested by $<" . $requester->nickname . "$>.", $player->login);
		} else {
			try {
				$mapIndex = $this->maniaControl->client->getNextMapIndex();
			} catch(Exception $e) {
				// TODO: is it even possible that an exception other than connection errors will be thrown? - remove try-catch?
				trigger_error("Error while Reading the next Map Index");
				$this->maniaControl->chat->sendError("Error while Reading next Map Inde");
				return;
			}
			$maps = $this->maniaControl->mapManager->getMaps();
			$map  = $maps[$mapIndex];
			$this->maniaControl->chat->sendInformation("Next map is $<" . $map->name . "$> from $<" . $map->authorNick . "$>.", $player->login);
		}
	}

	/**
	 * Handle removemap command
	 *
	 * @param array                        $chat
	 * @param \ManiaControl\Players\Player $player
	 */
	public function command_RemoveMap(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, MapManager::SETTING_PERMISSION_REMOVE_MAP)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		// Get map
		$map = $this->maniaControl->mapManager->getCurrentMap();
		if (!$map) {
			$this->maniaControl->chat->sendError("Couldn't remove map.", $player->login);
			return;
		}

		//RemoveMap
		$this->maniaControl->mapManager->removeMap($player, $map->uid);
	}

	/**
	 * Handle addmap command
	 *
	 * @param array                        $chatCallback
	 * @param \ManiaControl\Players\Player $player
	 */
	public function command_ShuffleMaps(array $chatCallback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, MapManager::SETTING_PERMISSION_SHUFFLE_MAPS)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}

		// Shuffles the maps
		$this->maniaControl->mapManager->shuffleMapList($player);
	}

	/**
	 * Handle addmap command
	 *
	 * @param array                        $chatCallback
	 * @param \ManiaControl\Players\Player $player
	 */
	public function command_AddMap(array $chatCallback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, MapManager::SETTING_PERMISSION_ADD_MAP)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$params = explode(' ', $chatCallback[1][2], 2);
		if (count($params) < 2) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //addmap 1234', $player->login);
			return;
		}

		// add Map from Mania Exchange
		$this->maniaControl->mapManager->addMapFromMx($params[1], $player->login);
	}

	/**
	 * Handle /nextmap Command
	 *
	 * @param array                        $chat
	 * @param \ManiaControl\Players\Player $player
	 */
	public function command_NextMap(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, MapManager::SETTING_PERMISSION_SKIP_MAP)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}

		try {
			$this->maniaControl->client->nextMap();
		} catch(Exception $e) {
			// TODO: is it even possible that an exception other than connection errors will be thrown? - remove try-catch?
			$this->maniaControl->chat->sendError("Error while Skipping the Map");
			return;
		}

		$message = '$<' . $player->nickname . '$> skipped the current Map!';
		$this->maniaControl->chat->sendSuccess($message);
		$this->maniaControl->log($message, true);
	}

	/**
	 * Handle restartmap command
	 *
	 * @param array                        $chat
	 * @param \ManiaControl\Players\Player $player
	 */
	public function command_RestartMap(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, MapManager::SETTING_PERMISSION_RESTART_MAP)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$message = '$<' . $player->nickname . '$> restarted the current Map!';
		$this->maniaControl->chat->sendSuccess($message);
		$this->maniaControl->log($message, true);
		try {
			$this->maniaControl->client->restartMap();
		} catch(Exception $e) {
			// TODO: is it even possible that an exception other than connection errors will be thrown? - remove try-catch?
			//do nothing
		}
	}

	/**
	 * Handle /maps command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_List(array $chatCallback, Player $player) {
		$this->maniaControl->mapManager->mapList->showMapList($player);
	}

	/**
	 * Handle ManiaExchange list command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_xList(array $chatCallback, Player $player) {
		$this->maniaControl->mapManager->mxList->showList($chatCallback, $player);
	}
}
