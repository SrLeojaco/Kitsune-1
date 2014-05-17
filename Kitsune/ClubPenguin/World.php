<?php

namespace Kitsune\ClubPenguin;

final class World extends ClubPenguin {

	protected $world_handlers = array(
		"s" => array(
			"j#js" => "handleJoinWorld",
			"i#gi" => "handleGetInventoryList",
			"l#mst" => "handleMailStartEngine",
			"u#glr" => "handleGetLastRevision",
			"l#mg" => "handleGetMail",
			"u#gabcms" => "handleGetABTestData", // Currently has no method
			"j#jr" => "handleJoinRoom",
			"u#pbi" => "handleGetPlayerInfoById",
			"u#sp" => "handleSendPlayerMove",
			"u#sf" => "handleSendPlayerFrame",
			"u#h" => "handleSendHeartbeat",
			"u#sa" => "handleUpdatePlayerAction",
			"i#ai" => "handleBuyInventory",
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
			"p#pg" => "handleGetPufflesByPlayerId",
			"j#jp" => "handleJoinPlayerRoom",
			"g#aloc" => "handleBuyIglooLocation",
			"g#gail" => "handleGetAllIglooLayouts",
			"g#uic" => "handleUpdateIglooConfiguration"
		)
	);
	
	private $rooms = array();
	private $items = array();
	private $locations = array();
	
	public function __construct() {
		$downloadAndDecode = function($url) {
			$json_data = file_get_contents($url);
			$data_array = json_decode($json_data, true);
			return $data_array;
		};
		
		echo "Setting up rooms.. ";
		$rooms = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/rooms.json");
		foreach($rooms as $room => $details) {
			$this->rooms[$room] = new Room($room, sizeof($this->rooms) + 1);
			unset($rooms[$room]);
		}
		echo "done\n";
		
		echo "Building clothing list.. ";
		$items = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/paper_items.json");
		foreach($items as $item) {
			$item_id = $item["paper_item_id"];
			$this->items[$item_id] = $item["cost"];
			unset($items[$item_id]);
		}
		echo "done\n";
		
		echo "Building location list.. ";
		$locations = $downloadAndDecode("http://media1.clubpenguin.com/play/en/web_service/game_configs/igloo_locations.json");
		foreach($locations as $location) {
			$location_id = $location["igloo_location_id"];
			$this->locations[$location_id] = $location["cost"];
			unset($locations[$location_id]);
		}
		echo "done\n";
	}
	
	protected function handleUpdateIglooConfiguration($socket, $packet) {
		$penguin = $this->penguins[$socket];
		
		$player_id = $packet::$data[2];
		$igloo_type = $packet::$data[3];
		$floor = $packet::$data[4];
		$location = $packet::$data[5];
		$music = $packet::$data[6];
		$furniture = $packet::$data[7];
		
		$penguin->database->updateIglooColumn($penguin->active_igloo, "Type", $igloo_type);
		$penguin->database->updateIglooColumn($penguin->active_igloo, "Floor", $floor);
		$penguin->database->updateIglooColumn($penguin->active_igloo, "Location", $location);
		$penguin->database->updateIglooColumn($penguin->active_igloo, "Music", $music);
		$penguin->database->updateIglooColumn($penguin->active_igloo, "Furniture", $furniture);
		
		$penguin->send("%xt%uic%{$penguin->room->internal_id}%$player_id%{$penguin->active_igloo}%$igloo_type:$floor:$location:$music:$furniture%");
		
		echo "HANDLE UPDATE IGLOO CONFIGURATION NOT DONE\n";
	}
	
	protected function handleGetAllIglooLayouts($socket, $packet) {
		$penguin = $this->penguins[$socket];
		$player_id = $packet::$data[2];
		
		if($penguin->database->playerIdExists($player_id)) {
			$igloo_layouts = $penguin->database->getAllIglooLayouts($player_id);
			$active_igloo = $penguin->database->getColumnById($player_id, "Igloo");
			
			$penguin->send("%xt%gail%{$penguin->room->internal_id}%$active_igloo%$igloo_layouts%");
		}
	}
	
	protected function handleBuyIglooLocation($socket, $packet) {
		$penguin = $this->penguins[$socket];
		$location_id = $packet::$data[2];
		
		if(!isset($this->locations[$location_id])) {
			return $penguin->send("%xt%e%-1%402%");
		} elseif(isset($this->penguin->locations[$location_id])) {
			return $penguin->send("%xt%e%-1%400%");
		}
		
		$cost = $this->locations[$location_id];
		if($penguin->coins < $cost) {
			return $penguin->send("%xt%e%-1%401%");
		} else {
			$penguin->buyLocation($location_id, $cost);
		}
	}
	
	protected function handleJoinPlayerRoom($socket, $packet) {
		$penguin = $this->penguins[$socket];
		$player_id = $packet::$data[2];
		
		if($penguin->database->playerIdExists($player_id)) {
			$external_id = $player_id + 1000;
			
			if(!isset($this->rooms[$external_id])) {
				$this->rooms[$external_id] = new Room($external_id, $player_id);
			}
			
			$penguin->send("%xt%jp%$player_id%$external_id%");
			$this->joinRoom($penguin, $external_id);
		}
	}
	
	// TODO: Work on puffle adoption so this actually does something
	protected function handleGetPufflesByPlayerId($socket, $packet) {
		$penguin = $this->penguins[$socket];
		$penguin->send("%xt%pg%{$penguin->room->internal_id}%%");
	}
	
	protected function handleGetGameData($socket, $packet) {
		$penguin = $this->penguins[$socket];
		$penguin->send("%xt%ggd%{$penguin->room->internal_id}%Kitsune%");
	}
	
	protected function handleGetActiveIgloo($socket, $packet) {
		$penguin = $this->penguins[$socket];
		$player_id = $packet::$data[2];
		
		if($penguin->database->playerIdExists($player_id)) {
			$active_igloo = $penguin->database->getColumnById($player_id, "Igloo");
			
			if($player_id == $penguin->id) {
				$penguin->active_igloo = $active_igloo;
			}
			
			$igloo_details = $penguin->database->getIglooDetails($active_igloo);
			$penguin->send("%xt%gm%{$penguin->room->internal_id}%$player_id%$igloo_details%");
		}
	}
	
	protected function handleGetFurnitureInventory($socket, $packet) {
		$penguin = $this->penguins[$socket];
		
		$furniture_list = $penguin->database->getColumnsById($penguin->id, array("Furniture", "Floors", "Igloos", "Locations"));
		$furniture_list = implode('%', $furniture_list);
		
		$penguin->send("%xt%gii%{$penguin->room->internal_id}%$furniture_list%");
	}
	
	// Because I'm super lazy
	protected function handleSendUpdatePlayerClothing($socket, $packet) {
		$penguin = $this->penguins[$socket];
		$item_id = $packet::$data[2];
		$clothing_type = substr($packet::$data[0], 2);
		$clothing = array(
			"upc" => "Color",
			"uph" => "Head",
			"upf" => "Face",
			"upn" => "Neck",
			"upb" => "Body",
			"upa" => "Hand",
			"upe" => "Feet",
			"upp" => "Photo",
			"upl" => "Flag"
		);
			
		call_user_func(array($penguin, "update{$clothing[$clothing_type]}"), $item_id);
	}
	
	protected function handleBuyInventory($socket, $packet) {
		$penguin = $this->penguins[$socket];
		$item_id = $packet::$data[2];
		
		if(!isset($this->items[$item_id])) {
			return $penguin->send("%xt%e%-1%402%");
		} elseif(isset($this->penguin->inventory[$item_id])) {
			return $penguin->send("%xt%e%-1%400%");
		}
		
		$cost = $this->items[$item_id];
		if($penguin->coins < $cost) {
			return $penguin->send("%xt%e%-1%401%");
		} else {
			$penguin->addItem($item_id, $cost);
		}
	}
	
	protected function handleUpdatePlayerAction($socket, $packet) {
		$penguin = $this->penguins[$socket];
		$action_id = $packet::$data[2];
		
		$penguin->room->send("%xt%sa%{$penguin->room->internal_id}%{$penguin->id}%{$action_id}%");
	}
	
	protected function handleSendHeartbeat($socket, $packet) {
		$penguin = $this->penguins[$socket];
		
		$penguin->send("%xt%h%{$penguin->room->internal_id}%");
	}
	
	protected function handleSendPlayerFrame($socket, $packet) {
		$penguin = $this->penguins[$socket];
		
		$penguin->frame = $packet::$data[2];
		$penguin->room->send("%xt%sf%{$penguin->room->internal_id}%{$penguin->id}%{$penguin->frame}%");
	}
		
	protected function handleSendPlayerMove($socket, $packet) {
		$penguin = $this->penguins[$socket];
		
		$penguin->x = $packet::$data[2];
		$penguin->y = $packet::$data[3];
		$penguin->room->send("%xt%sp%{$penguin->room->internal_id}%{$penguin->id}%{$penguin->x}%{$penguin->y}%"); 
	}
	
	protected function handleGetPlayerInfoById($socket, $packet) {
		$penguin = $this->penguins[$socket];
		
		if($penguin->database->playerIdExists($packet::$data[2])) {
			$player_array = $penguin->database->getColumnsById($packet::$data[2], array("Username", "SWID"));
			$penguin->send("%xt%pbi%{$penguin->room->internal_id}%{$player_array["SWID"]}%{$packet::$data[2]}%{$player_array["Username"]}%");
		}	
	}
	
	protected function handleJoinRoom($socket, $packet) {
		$penguin = $this->penguins[$socket];
		
		$room = $packet::$data[2];
		$x = $packet::$data[3];
		$y = $packet::$data[3];
		
		$this->joinRoom($penguin, $room, $x, $y);
	}
	
	protected function handleGetABTestData($socket, $packet) {
		
	}
	
	protected function handleGetMail($socket, $packet) {
		$this->penguins[$socket]->send("%xt%mg%-1%");
	}
	
	protected function handleGetLastRevision($socket, $packet) {
		$this->penguins[$socket]->send("%xt%glr%-1%10915%");
	}
	
	protected function handleMailStartEngine($socket, $packet) {
		$penguin = $this->penguins[$socket];
		
		$penguin->send("%xt%mst%-1%0%0%");
	}
	
	protected function handleGetInventoryList($socket, $packet) {
		$penguin = $this->penguins[$socket];
		
		$inventory_list = implode('%', $penguin->inventory);
		$penguin->send("%xt%gi%-1%$inventory_list%");
	}
	
	public function joinRoom($penguin, $room_id, $x = 0, $y = 0) {
		if(!isset($this->rooms[$room_id])) {
			return;
		} elseif(isset($penguin->room)) {
			$penguin->room->remove($penguin);
		}
		
		$penguin->frame = 1;
		$penguin->x = $x;
		$penguin->y = $y;
		$this->rooms[$room_id]->add($penguin);
	}
	
	private function getOpenRoom() {
		// Non-game/party rooms, perhaps have a totally separate array consisting of these room ids?
		$room_ids = array_keys(
			array_filter($this->rooms, function($room) {
				if($room->external_id <= 810) {
					return true;
				}
			})
		);
		
		shuffle($room_ids);
		foreach($room_ids as $room_id) {
			if(sizeof($this->rooms[$room_id]->penguins) < 75) {
				return $room_id;
			}
		}
		
		return 100;
	}
	
	protected function handleJoinWorld($socket, $packet) {
		$penguin = $this->penguins[$socket];
		
		if($penguin->id != $packet::$data[2]) {
			return $this->removePenguin($penguin);
		}
		
		$login_key = $packet::$data[3];
		$db_login_key = $penguin->database->getColumnById($penguin->id, "LoginKey");
		
		if($db_login_key != $login_key) {
			$penguin->send("%xt%e%-1%101%");
			$penguin->database->updateColumnByid($penguin->id, "LoginKey", "");
			return $this->removePenguin($penguin);
		}
		
		$penguin->database->updateColumnByid($penguin->id, "LoginKey", "");
		
		$penguin->loadPlayer();
		$penguin->send("%xt%activefeatures%-1%");
		$penguin->send("%xt%js%-1%1%0%0%1%");
		$penguin->send("%xt%gps%-1%{$penguin->id}%");
		$penguin->send("%xt%pgu%-1%");
		
		$player_string = $penguin->getPlayerString();
		$current_time = time();
		
		$load_player = "$player_string|%{$penguin->coins}%0%1440%$current_time%{$penguin->age}%0%7521%%7%1%0%211843";
		$penguin->send("%xt%lp%-1%$load_player%");
		
		$open_room = $this->getOpenRoom();
		$this->joinRoom($penguin, $open_room, 0, 0);
		
		// The 0 after the player id is probably a transformation id, will be looking into a proper implementation
		$penguin->room->send("%xt%spts%-1%{$penguin->id}%0%{$penguin->avatar}%");
	}

	protected function handleLogin($socket, $packet) {
		$penguin = $this->penguins[$socket];
		$raw_player_string = $packet::$data['body']['login']['nick'];
		$player_hashes = $packet::$data['body']['login']['pword'];
		
		$player_array = explode('|', $raw_player_string);
		list($id, $swid, $username) = $player_array;
		
		if($penguin->database->playerIdExists($id) === false) {
			return $this->removePenguin($penguin);
		}
		
		if($penguin->database->usernameExists($username) === false) {
			$penguin->send("%xt%e%-1%101%");
			return $this->removePenguin($penguin);
		}
		
		$hashes_array = explode('#', $player_hashes);
		list($login_key, $confirmation_hash) = $hashes_array;
		
		$db_confirmation_hash = $penguin->database->getColumnById($id, "ConfirmationHash");
		if($db_confirmation_hash != $confirmation_hash) {
			$penguin->send("%xt%e%-1%101%");
			return $this->removePenguin($penguin);
		} else {
			echo "Login successful!\n";
			$penguin->database->updateColumnByid($id, "ConfirmationHash", ""); // Maybe the column should be cleared even when the login is unsuccessful
			$penguin->id = $id;
			$penguin->swid = $swid;
			$penguin->username = $username;
			$penguin->identified = true;
			$penguin->send("%xt%l%-1%");
		}
		
	}
	
	protected function handleDisconnect($socket) {
		$penguin = $this->penguins[$socket];
		$penguin->room->remove($penguin);
		unset($this->penguins[$socket]);
		
		echo "Player disconnected\n";
	}
	
}

?>