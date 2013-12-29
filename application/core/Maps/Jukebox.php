<?php

namespace ManiaControl\Maps;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\ManiaControl;

/**
 * Jukebox Class
 *
 * @author steeffeen & kremsy
 */
class Jukebox implements CallbackListener {
	/**
	 * Constants
	 */
	const CB_JUKEBOX_CHANGED =  'Jukebox.JukeBoxChanged';
	const SETTING_SKIP_MAP_ON_LEAVE = 'Skip Map when the requester leaves';
	const SETTING_SKIP_JUKED_ADMIN = 'Skip Map when admin leaves';

	/**
	 * Private properties
	 */
	private $maniaControl = null;
	private $jukedMaps = array();

	/**
	 * Create a new server jukebox
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->maniaControl->callbackManager->registerCallbackListener(CallbackManager::CB_MC_BEGINMAP, $this,'beginMap');
		$this->maniaControl->callbackManager->registerCallbackListener(CallbackManager::CB_MC_ENDMAP, $this,'endMap');

		// Init settings
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_SKIP_MAP_ON_LEAVE, true);
		$this->maniaControl->settingManager->initSetting($this, self::SETTING_SKIP_JUKED_ADMIN, false);
	}

	/**
	 * Adds a Map to the jukebox
	 * @param $login
	 * @param $uid
	 */
	public function addMapToJukebox($login, $uid){

		//Check if the map is already juked
		if(array_key_exists($uid, $this->jukedMaps)){
			//TODO message map already juked
			return;
		}

		//TODO recently maps not able to add to jukebox setting, and management


		$this->jukedMaps[$uid] = array($login, $this->maniaControl->mapManager->getMapByUid($uid));

		//TODO Message

		// Trigger callback
		$this->maniaControl->callbackManager->triggerCallback(self::CB_JUKEBOX_CHANGED, array('add', $this->jukedMaps[$uid]));

	}

	/**
	 * Revmoes a Map from the jukebox
	 * @param $login
	 * @param $uid
	 */
	public function removeFromJukebox($login, $uid){
		//unset($this->jukedMapsUid[$uid]);
		unset($this->jukedMaps[$uid]);


	}

	public function beginMap(){


	}


	/**
	 * Called on endmap
	 * @param array $callback
	 */
	public function endMap(array $callback){

		if($this->maniaControl->settingManager->getSetting($this, self::SETTING_SKIP_MAP_ON_LEAVE) == TRUE){
			//Skip Map if requester has left
			for($i = 0; $i < count($this->jukedMaps); $i++){
				$jukedMap = reset($this->jukedMaps);

				//found player, so play this map
				if($this->maniaControl->playerManager->getPlayer($jukedMap[0]) != null){
					break;
				}


				if($this->maniaControl->settingManager->getSetting($this, self::SETTING_SKIP_JUKED_ADMIN) == FALSE){
					//TODO check in database if a the juker of the map is admin, and if he is, just break
				}

				// Trigger callback
				$this->maniaControl->callbackManager->triggerCallback(self::CB_JUKEBOX_CHANGED, array('skip', $jukedMap[0]));

				//Player not found, so remove the map from the jukebox
				array_shift($this->jukedMaps);

				//TODO Message, report skip
			}
		}
		$nextMap = array_shift($this->jukedMaps);

		//Check if Jukebox is empty
		if($nextMap == null)
			return;

		$nextMap = $nextMap[1];


		$success = $this->maniaControl->client->query('ChooseNextMap', $nextMap->fileName);
		if (!$success) {
			trigger_error('[' . $this->maniaControl->client->getErrorCode() . '] ChooseNextMap - ' . $this->maniaControl->client->getErrorCode(), E_USER_WARNING);
			return;
		}

	}

	/**
	 * Returns a list with the indexes of the juked maps
	 * @return array
	 */
	public function getJukeBoxRanking(){
		$i = 1;
		$jukedMaps = array();
		foreach($this->jukedMaps as $map){
			$map = $map[1];
			$jukedMaps[$map->uid] = $i;
			$i++;
		}
		return $jukedMaps;
	}

	/**
	 * Dummy Function for testing
	 */
	public function printAllMaps(){
		foreach($this->jukedMaps as $map){
			$map = $map[1];
			var_dump($map->name);
		}
	}

} 