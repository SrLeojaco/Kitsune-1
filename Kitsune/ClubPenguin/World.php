<?php

namespace Kitsune\ClubPenguin;

use Kitsune\Logging\Logger;
use Kitsune\ClubPenguin\Handlers;
use Kitsune\ClubPenguin\Packets\Packet;

final class World extends ClubPenguin {

	protected $worldHandlers = array(
		"s" => array(
			"j#js" => "handleJoinWorld",
			"j#jr" => "handleJoinRoom",
			"j#jp" => "handleJoinPlayerRoom",
			"j#grs" => "handleRefreshRoom",
			
			"i#gi" => "handleGetInventoryList",
			"i#ai" => "handleBuyInventory",
			"i#qpp" => "handleGetPlayerPins",
			"i#qpa" => "handleGetPlayerAwards",
			
			"u#glr" => "handleGetLastRevision",
			"u#pbi" => "handleGetPlayerInfoById",
			"u#sp" => "handleSendPlayerMove",
			"u#sf" => "handleSendPlayerFrame",
			"u#h" => "handleSendHeartbeat",
			"u#sa" => "handleUpdatePlayerAction",
			"u#gabcms" => "handleGetABTestData", // Currently has no method
			"u#se" => "handleSendEmote",
			"u#sb" => "handlePlayerThrowBall",
			"u#gbffl" => "handleGetBestFriendsList",
			"u#pbsu" => "handlePlayerBySwidUsername",
			"u#ss" => "handleSafeMessage",
			"u#followpath" => "handlePenguinOnSlideOrZipline",
			
			"l#mg" => "handleGetMail",
			"l#mst" => "handleStartMailEngine",
			"l#ms" => "handleSendMailItem",
			"l#mc" => "handleMailChecked",
			"l#md" => "handleDeleteMailItem",
			"l#mdp" => "handleDeleteMailFromUser",
			
			"s#upc" => "handleSendUpdatePlayerClothing",
			"s#uph" => "handleSendUpdatePlayerClothing",
			"s#upf" => "handleSendUpdatePlayerClothing",
			"s#upn" => "handleSendUpdatePlayerClothing",
			"s#upb" => "handleSendUpdatePlayerClothing",
			"s#upa" => "handleSendUpdatePlayerClothing",
			"s#upe" => "handleSendUpdatePlayerClothing",
			"s#upp" => "handleSendUpdatePlayerClothing",
			"s#upl" => "handleSendUpdatePlayerClothing",
			
			"g#gii" => "handleGetFurnitureInventory",
			"g#gm" => "handleGetActiveIgloo",
			"g#ggd" => "handleGetGameData",			
			"g#aloc" => "handleBuyIglooLocation",
			"g#gail" => "handleGetAllIglooLayouts",
			"g#uic" => "handleUpdateIglooConfiguration",
			"g#af" => "handleBuyFurniture",
			"g#ag" => "handleSendBuyIglooFloor",
			"g#au" => "handleSendBuyIglooType",
			"g#al" => "handleAddIglooLayout",
			"g#pio" => "handleLoadIsPlayerIglooOpen",
			"g#cli" => "handleCanLikeIgloo",
			"g#uiss" => "handleUpdateIglooSlotSummary",
			"g#gr" => "handleGetOpenIglooList",
			"g#gili" => "handleGetIglooLikeBy",
			"g#li" => "handleLikeIgloo",
			
			"m#sm" => "handleSendMessage",
			
			"o#k" => "handleKickPlayerById",
			"o#m" => "handleMutePlayerById",
			"o#initban" => "handleInitBan",
			"o#ban" => "handleModeratorBan",
			"o#moderatormessage" => "handleModeratorMessage",
			
			"st#sse" => "handleStampAdd",
			"st#gps" => "handleGetStamps",
			"st#gmres" => "handleGetRecentStamps",
			"st#gsbcd" => "handleGetBookCover",
			"st#ssbcd" => "handleUpdateBookCover",
			
			"p#pg" => "handleGetPufflesByPlayerId",
			"p#checkpufflename" => "handleCheckPuffleNameWithResponse",
			"p#pn" => "handleAdoptPuffle",
			"p#pgmps" => "handleGetMyPuffleStats",
			"p#pw" => "handleSendPuffleWalk",
			"p#pufflewalkswap" => "handlePuffleSwap",
			"p#puffletrick" => "handlePuffleTrick",
			"p#puffleswap" => "handleSendChangePuffleRoom",
			"p#pgpi" => "handleGetPuffleCareInventory",
			"p#papi" => "handleSendBuyPuffleCareItem",
			"p#phg" => "handleGetPuffleHanderStatus",
			"p#puphi" => "handleVisitorHatUpdate",
			"p#pp" => "handleSendPufflePlay",
			
			"t#at" => "handleOpenPlayerBook",
			"t#rt" => "handleClosePlayerBook",
			"bh#lnbhg" => "handleLeaveGame"
		),
		
		"z" => array(
			"gz" => "handleGetGame",
			"m" => "handleGameMove",
			"zo" => "handleGameOver"
		)
	);
	
	use Handlers\Navigation;
	use Handlers\Item;
	use Handlers\Player;
	use Handlers\Mail;
	use Handlers\Setting;
	use Handlers\Igloo;
	use Handlers\Message;
	use Handlers\Moderation;
	use Handlers\Pet;
	use Handlers\Toy;
	use Handlers\Stampbook;
	use Handlers\Blackhole;
	
	public $rooms = array();
	public $items = array();
	public $pins = array();
	public $locations = array();
	public $furniture = array();
	public $floors = array();
	public $igloos = array();
	public $gameStamps = array();
	
	public $spawnRooms = array();
	public $penguinsById = array();
	
	private $openIgloos = array();
	
	public $rinkPuck = array(0, 0, 0, 0);
	
	public function __construct() {
		parent::__construct();
		
		if(is_dir("crumbs") === false) {
			mkdir("crumbs", 0777);
		}
		
		$downloadAndDecode = function($url) {
			$filename = basename($url, ".json");
			
			if(file_exists("crumbs/$filename.json")) {
				$jsonData = file_get_contents("crumbs/$filename.json");
			} else {
				$jsonData = file_get_contents($url);
				file_put_contents("crumbs/$filename.json", $jsonData);
			}
			
			$dataArray = json_decode($jsonData, true);
			return $dataArray;
		};
		
		$rooms = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/rooms.json");
		foreach($rooms as $room => $details) {
			$this->rooms[$room] = new Room($room, sizeof($this->rooms) + 1, ($details['path'] == '' ? true : false));
		}
		
		$stamps = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/stamps.json");
		foreach($stamps as $stampCat) {
			if($stampCat['parent_group_id'] == 8) {
				foreach($stampCat['stamps'] as $stamp) {
					foreach($rooms as $room){
						if(str_replace("Games : ", "", $stampCat['display']) == $room['display_name']) {
							$roomId = $room['room_id'];
						}
					}
					
					$this->gameStamps[$roomId][] = $stamp['stamp_id'];
				}
			}
		}

		unset($rooms);
		unset($stamps);
		
		$agentRooms = array(210, 212, 323, 803);
		$rockhoppersShip = array(422, 423);
		$ninjaRooms = array(320, 321, 324, 326);
		$hotelRooms = range(430, 434);
		
		$noSpawn = array_merge($agentRooms, $rockhoppersShip, $ninjaRooms, $hotelRooms);
		$this->spawnRooms = array_keys(
			array_filter($this->rooms, function($room) use ($noSpawn) {
				if(!in_array($room->externalId, $noSpawn) && $room->externalId <= 810) {
					return true;
				}
			})
		);
		
		$items = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/paper_items.json");
		foreach($items as $itemIndex => $item) {
			$itemId = $item["paper_item_id"];
			
			$this->items[$itemId] = $item["cost"];
			
			if($item["type"] == 8) {
				array_push($this->pins, $itemId);
			}
			
			unset($items[$itemIndex]);
		}
		
		$locations = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/igloo_locations.json");
		foreach($locations as $locationIndex => $location) {
			$locationId = $location["igloo_location_id"];
			$this->locations[$locationId] = $location["cost"];
			
			unset($locations[$locationIndex]);
		}
		
		$furnitureList = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/furniture_items.json");
		foreach($furnitureList as $furnitureIndex => $furniture) {
			$furnitureId = $furniture["furniture_item_id"];
			$this->furniture[$furnitureId] = $furniture["cost"];
			
			unset($furnitureList[$furnitureIndex]);
		}
		
		$floors = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/igloo_floors.json");
		foreach($floors as $floorIndex => $floor) {
			$floorId = $floor["igloo_floor_id"];
			$this->floors[$floorId] = $floor["cost"];
			
			unset($floors[$floorIndex]);
		}
		
		$igloos = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/igloos.json");
		foreach($igloos as $iglooId => $igloo) {
			$this->igloos[$iglooId] = $igloo["cost"];
			
			unset($igloos[$iglooId]);
		}
		
		$careItems = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/puffle_items.json");
		foreach($careItems as $careId => $careItem) {
			$itemId = $careItem["puffle_item_id"];
			
			$this->careItems[$itemId] = array($careItem["cost"], $careItem["quantity"]);
			
			unset($careItems[$careId]);
		}
		
		Logger::Fine("World server is online");
	}
	
	protected function handleGameOver($socket) {
		$penguin = $this->penguins[$socket];
		
		$score = Packet::$Data[2];
		
		if($penguin->room->externalId < 900) {
			$penguin->send("%xt%zo%{$penguin->room->internalId}%{$penguin->coins}%%0%0%0%");

			return;
		}

		if(is_numeric($score)) {
			$coins = (strlen($score) > 1 ? round($score / 10) : $score);

			if($score < 99999) {
				$penguin->setCoins($penguin->coins + $coins);
			}
		}

		if(isset($this->gameStamps[$penguin->room->externalId])) {
			$myStamps = explode(",", $penguin->database->getColumnById($penguin->id, "Stamps"));
			$collectedStamps = "";
			$totalGameStamps = 0;

			foreach($myStamps as $stamp) {
				if(in_array($stamp, $this->gameStamps[$penguin->room->externalId])) {
					$collectedStamps .= $stamp."|";
				}

				foreach($this->gameStamps as $gameArray) {
					if(in_array($stamp, $gameArray)){
						$totalGameStamps += 1;
					}
				}
			}

			$totalStamps = count(explode("|", $collectedStamps)) - 1;
			$totalStampsGame = count($this->gameStamps[$penguin->room->externalId]);
			$collectedStamps = rtrim($collectedStamps, "|");

			$penguin->send("%xt%zo%{$penguin->room->internalId}%{$penguin->coins}%$collectedStamps%$totalStamps%$totalStampsGame%$totalGameStamps%");
		} else {	
			$penguin->send("%xt%zo%{$penguin->room->internalId}%{$penguin->coins}%%0%0%0%");
		}
	}
	
	public function joinRoom($penguin, $roomId, $x = 0, $y = 0) {
		if(!isset($this->rooms[$roomId])) {
			return;
		} elseif(isset($penguin->room)) {
			$penguin->room->remove($penguin);
		}
		
		$penguin->frame = 1;
		$penguin->x = $x;
		$penguin->y = $y;
		$this->rooms[$roomId]->add($penguin);
	}
	
	private function getOpenRoom() {
		$spawnRooms = $this->spawnRooms;
		shuffle($spawnRooms);
		
		foreach($spawnRooms as $roomId) {
			if(sizeof($this->rooms[$roomId]->penguins) < 75) {
				return $roomId;
			}
		}
		
		return 100;
	}
	
	protected function handleGameMove($socket) {
		$penguin = $this->penguins[$socket];
		
		$this->rinkPuck = array_splice(Packet::$Data, 3);
		
		$puckData = implode('%', $this->rinkPuck);
		
		$penguin->send("%xt%zm%{$penguin->room->internalId}%{$penguin->id}%$puckData%");
	}
	
	protected function handleGetGame($socket) {
		$penguin = $this->penguins[$socket];
		
		$puckData = implode('%', $this->rinkPuck);
		
		$penguin->send("%xt%gz%{$penguin->room->internalId}%$puckData%");
	}
	
	public function mutePlayer($targetPlayer, $moderatorUsername) {
		if(!$targetPlayer->muted) {
			$targetPlayer->muted = true;
			$targetPlayer->send("%xt%moderatormessage%-1%2%");
			Logger::Info("$moderatorUsername has muted {$targetPlayer->username}");
		} else {
			$targetPlayer->muted = false;
			Logger::Info("$moderatorUsername has unmuted {$targetPlayer->username}");
		}
	}
	
	public function kickPlayer($targetPlayer, $moderatorUsername) {
		$targetPlayer->send("%xt%moderatormessage%-1%3%");
		$this->removePenguin($targetPlayer);
		
		Logger::Info("$moderatorUsername kicked {$targetPlayer->username}");
	}
	
	public function getPlayerById($playerId) {
		if(isset($this->penguinsById[$playerId])) {
			return $this->penguinsById[$playerId];
		}
		
		return null;
	}
	
	private function joinPuffleData(array $puffleData, $walkingPuffleId = null, $iglooAppend = false) {
		$puffles = implode('%', array_map(
			function($puffle) use($walkingPuffleId, $iglooAppend) {
				if($puffle["ID"] != $walkingPuffleId) {
					if($puffle["Subtype"] == 0) {
						$puffle["Subtype"] = "";
					}
					
					$playerPuffle = implode('|', $puffle);
					
					if($iglooAppend !== false) {
						$playerPuffle .= "|0|0|0|0";
					}
					
					return $playerPuffle;
				}
			}, $puffleData
		));	
		
		return $puffles;
	}

	protected function handleLogin($socket) {
		$penguin = $this->penguins[$socket];
		$rawPlayerString = Packet::$Data['body']['login']['nick'];
		$playerHashes = Packet::$Data['body']['login']['pword'];
		
		$playerArray = explode('|', $rawPlayerString);
		list($id, $swid, $username) = $playerArray;
		
		if(!$penguin->database->playerIdExists($id)) {
			return $this->removePenguin($penguin);
		}
		
		if(!$penguin->database->usernameExists($username)) {
			$penguin->send("%xt%e%-1%101%");
			return $this->removePenguin($penguin);
		}
		
		$hashesArray = explode('#', $playerHashes);
		list($loginKey, $confirmationHash) = $hashesArray;
		
		$dbConfirmationHash = $penguin->database->getColumnById($id, "ConfirmationHash");
		if($dbConfirmationHash != $confirmationHash) {
			$penguin->send("%xt%e%-1%101%");
			return $this->removePenguin($penguin);
		} else {
			$penguin->database->updateColumnByid($id, "ConfirmationHash", ""); // Maybe the column should be cleared even when the login is unsuccessful
			$penguin->id = $id;
			$penguin->swid = $swid;
			$penguin->username = $username;
			$penguin->identified = true;
			$penguin->send("%xt%l%-1%");
		}
		
	}
	
	protected function removePenguin($penguin) {
		$this->removeClient($penguin->socket);

		if($penguin->room !== null) {
			$penguin->room->remove($penguin);
		}
		
		if(isset($this->penguinsById[$penguin->id])) {
			unset($this->penguinsById[$penguin->id]);
		}

		unset($this->penguins[$penguin->socket]);
	}

	protected function handleDisconnect($socket) {
		$penguin = $this->penguins[$socket];

		if($penguin->room !== null) {
			$penguin->room->remove($penguin);
		}
		
		if(isset($this->penguinsById[$penguin->id])) {
			unset($this->penguinsById[$penguin->id]);
		}

		unset($this->penguins[$socket]);

		Logger::Info("Player disconnected");
	}
	
}

?>