<?php

class favo_Max_Data {

	private $_csvFile = null;
	private $_dataColumns = array(
									'ValvePos' => 'valve',
									'SetpointTemp' => 'tempTarget',
									'MeasuredTemp' => 'tempMeasured',
									'Mode' => 'mode',
									'WindowOpen' => 'open',
									'temperature' => 'temperature',
									'humidity' => 'humidity'
							);
	
	public function __construct() {
		$this->modelDevice = new favo_Max_Model_Device();
		$this->modelHistory = new favo_Max_Model_Device_History();

		// auto setup on first call
		$setupFile = '.setup.' . get_class($this);
		if ( !file_exists($setupFile) ) {
			$this->modelDevice->setupTable();
			$this->modelHistory->setupTable();
			file_put_contents($setupFile, time());
		}
	}

	/**
	 * import web service post data as new cube status
	 * @param array $status the post data
	 * @return array list of cubes found in the status
	 */
	public function importStatus ($status) {
		// init some vars
		$deviceList = array();

		// convert into a nice readable array
		$data = array();
		$keys = array_keys($status);

		foreach ($keys as $index => $key) {
			$subkeys = explode('_', $key);

			// could be recursive, but for now we leave it like this …
			if ( 3 == count($subkeys) ) {
				$data[$subkeys[0]][$subkeys[1]][$subkeys[2]] = $status[$key];
			}
			else if ( 2 == count($subkeys) ) {
				$data[$subkeys[0]][$subkeys[1]] = $status[$key];
			}
			else {
				$data[$subkeys[0]] = $status[$key];
			}
		}
		$ts = time();

		// turn a room into a device
		foreach ( $data['Room'] as $room ) {
			$id = $data['Cube']['Serial'] . '_Room_' . $room['ID'];
			$room['Type'] = 'Room';
			$room['Serial'] = $id;
			$data['Device'][] = $room;
		}

		// collect device data
		foreach ( $data['Device'] as $device ) {
			$device['Name'] = utf8_encode($device['Name']);

			// assign cube serial for grouping
			$device['Cube'] = $data['Cube']['Serial'];

			// overwrite device data to have always the latest entry in memory
			$deviceList[$device['Serial']] = $device;
			
			// put data into history, blindly
			try {
				$historyEntry = $this->modelHistory->createRow();
				$historyEntry->pk = $device['Serial'] . '_' . $ts;
				$historyEntry->serial = $device['Serial'];
				$historyEntry->time = $ts;
				$historyEntry->data = serialize($device);
				$historyEntry->save();
			}
			catch (Exception $e) {
				// ignore
			}	
		}


		// cube list to be returned
		$cubeList = array();

		// update device list in database
		foreach ( $deviceList as $serial => $deviceData ) {
			$device = $this->modelDevice->find($serial);
			if ( !count($device) ) {
				$device = $this->modelDevice->createRow();
			}
			else {
				$device = $device->current();
			}
			
			if ( isset($deviceData['RoomID']) && $deviceData['RoomID'] ) {
				$device->roomAssignment = $data['Cube']['Serial'] . '_Room_' . $deviceData['RoomID'];
			}
			
			$device->serial = $deviceData['Serial'];
			$device->type = $deviceData['Type'];
			$device->title = $deviceData['Name'];
			$device->cube = $deviceData['Cube'];
			$device->lastUpdate = new Zend_Db_Expr('NOW()');
			$device->save();
		}
		
		return array($data['Cube']['Serial']);
	}

	public function importFile ($csv) {
		$this->_csvFile = $csv;

		// process the file
		if ( $csv ) {
			return $this->processFile();
		}
	}

	public function importOpenWeather($file, $cube, $serial, $title) {
		if ( !file_exists($file) ) {
			throw new Exception("logview file {$file} does not exist");
		}

		$data = json_decode(file_get_contents($file));
		$ts = $data->list[0]->dt;
		
		if ( !$data ) {
			throw new Exception("could not decode data");
		}

		// import the uploaded data
		try {
			$historyEntry = $this->modelHistory->createRow();
			$historyEntry->pk = $serial . '_' . $ts;
			$historyEntry->serial = $serial;
			$historyEntry->time = $ts;
			$historyEntry->data = serialize($data->list[0]);
			$historyEntry->save();
			if ( is_array($title) && $title[1] ) {
				$title[$historyEntry->serial] = $title[1];
			}
		}
		catch (Exception $e) {
			// ignore
		}


		// update device data
		$device = $this->modelDevice->find($serial);
		if ( !count($device) ) {
			$device = $this->modelDevice->createRow();
		}
		else {
			$device = $device->current();
		}
		$device->serial = $serial;
		$device->type = 'OpenWeather';
		$device->title = ($title && isset($title[$serial]) && $title[$serial]) ? $title[$serial] : 'OpenWeather Import';
		$device->cube = $cube;
		$device->lastUpdate = new Zend_Db_Expr('NOW()');
		$device->save();
	}

	public function importLogView($file, $cube, $serial, $title) {
		if ( !file_exists($file) ) {
			throw new Exception("logview file {$file} does not exist");
		}

		$fh = fopen ($file, 'r');
		$deviceData = array();
		$deviceList = array();
		while ( $row = fgetcsv($fh, 0, ';') ) {

			// raw data
			if ( $row[0] == '$1' ) {
				$indexOffset = 3;
				$ts = time();
			}
			else if ( strpos($row[0], ', ') !== FALSE ) {
				$indexOffset = 1;
				$time = explode(', ', $row[0]);
				$ts = strtotime($time[0] . ' ' . $time[1]);
			}
			else {
				$indexOffset = 1;
				$ts = strtotime($row[0]);
			}

			for ( $i = $indexOffset; $i < ($indexOffset+8); $i++ ) {
				// ignore empty columns
				if ( !isset($row[$i]) || $row[$i] == "" ) {
					continue;
				}

				// format the data into a nice array
				$data = array (
								'temperature' => floatval(str_replace(',','.',$row[$i])),
								'humidity' => floatval(str_replace(',','.',$row[$i+8]))
							);
				// import the uploaded data
				try {
					$historyEntry = $this->modelHistory->createRow();
					$historyEntry->pk = $serial . '_' . ($i-$indexOffset+1) . '_' . $ts;
					$historyEntry->serial = $serial . '_' . ($i-$indexOffset+1);
					$historyEntry->time = $ts;
					$historyEntry->data = serialize($data);
					$historyEntry->save();
					$deviceList[$historyEntry->serial] = true;
					if ( is_array($title) && $title[($i-$indexOffset+1)] ) {
						$title[$historyEntry->serial] = $title[($i-$indexOffset+1)];
					}
				}
				catch (Exception $e) {
					// ignore
				}
			}
		}
		fclose ($fh);


		// update device data
		foreach ( $deviceList as $deviceSerial => $dummy ) {
			$device = $this->modelDevice->find($deviceSerial);
			if ( !count($device) ) {
				$device = $this->modelDevice->createRow();
			}
			else {
				$device = $device->current();
			}
			$device->serial = $deviceSerial;
			$device->type = 'logview';
			$device->title = ($title && $title[$deviceSerial]) ? $title[$deviceSerial] : 'logview import';
			$device->cube = $cube;
			$device->lastUpdate = new Zend_Db_Expr('NOW()');
			$device->save();
		}
	}

	private function processFile () {
		$this->_cache = array();
		$tsOffset = -3600;
		
		if ( !file_exists($this->_csvFile) ) {
			throw new Exception("data file {$this->_csvFile} does not exist");
		}

		// init some vars
		$deviceList = array();

    	$file = fopen($this->_csvFile, 'r');
    	$counter = 0;
    	while ( $row = fgetcsv($file, 0, ';') ) {
    		$counter++;

    		// first line = keys
    		if ( !isset($keys) ) {
    			$keys = $row;
    			continue;
    		}
    		
    		// validate column count first
    		if ( count($keys) != count($row) ) {
    			throw new Exception("import aborted, column count differs at row #{$counter}");
    		}

			// convert into a nice readable array
			$data = array();
			foreach ($keys as $index => $key) {
				$subkeys = explode('.', $key);
				if ( 3 == count($subkeys) ) {
					$data[$subkeys[0]][$subkeys[1]][$subkeys[2]] = $row[$index];
				}
				else if ( 2 == count($subkeys) ) {
					$data[$subkeys[0]][$subkeys[1]] = $row[$index];
				}
				else {
					$data[$subkeys[0]] = $row[$index];
				}
			}
			$ts = strtotime($data['Date'] . ' ' . $data['Time']) + $tsOffset;
			
			// turn a room into a device
			foreach ( $data['Room'] as $room ) {
				$id = $data['Cube']['Serial'] . '_Room_' . md5($room['Name']);
				$room['Type'] = 'Room';
				$room['Serial'] = $id;
				$data['Device'][] = $room;
			}
			
			// collect device data
	    	foreach ( $data['Device'] as $device ) {
		    	$device['Name'] = utf8_encode($device['Name']);

				// assign cube serial for grouping
				$device['Cube'] = $data['Cube']['Serial'];

				// overwrite device data to have always the latest entry in memory
				$deviceList[$device['Serial']] = $device;
				
				// put data into history, blindly
				try {
					$historyEntry = $this->modelHistory->createRow();
					$historyEntry->pk = $device['Serial'] . '_' . $ts;
					$historyEntry->serial = $device['Serial'];
					$historyEntry->time = $ts;
					$historyEntry->data = serialize($device);
					$historyEntry->save();
				}
				catch (Exception $e) {
					// ignore
				}	
		   	}
    	}
    	fclose($file);


		// cube list to be returned
		$cubeList = array();

		// update device list in database
		foreach ( $deviceList as $serial => $data ) {
			$device = $this->modelDevice->find($serial);
			if ( !count($device) ) {
				$device = $this->modelDevice->createRow();
			}
			else {
				$device = $device->current();
			}
			$device->serial = $data['Serial'];
			$device->type = $data['Type'];
			$device->title = $data['Name'];
			$device->cube = $data['Cube'];
			$device->lastUpdate = new Zend_Db_Expr('NOW()');
			$device->save();
			
			$cubeList[$data['Cube']] = $data['Cube'];
		}
		
		return array_keys($cubeList);
	}

	public function getDevice ($serial) {
		$device = $this->modelDevice->find($serial);
		return $device->current()->toArray();
	}
	
	public function getDevices ($type = null, $cubes = array() ) {
		$select = $this->modelDevice->select();
		if ( $type ) {
			$select->where('type = ?', $type);
		}
		$select->where('cube IN (?)', $cubes);
		$list = $this->modelDevice->fetchAll ( $select->order('title') );
		$return = array();
		foreach ( $list as $device ) {
			$return[] = $device->toArray();
		}
		
		return $return;
	}
	
	public function getHistory ($serial, $from = null, $to = null) {
		$select = $this->modelHistory->select()->where('serial = ?', $serial)->order('time');
		if ( $from ) {
			$select->where('time > ?', $from);
		}
		if ( $to ) {
			$select->where('time < ?', $to);
		}
		$list = $this->modelHistory->fetchAll ( $select );
		$return = array();
		foreach ( $list as $entry ) {
			$data = unserialize($entry->data);
			$returnData = array('time' => $entry->time);
			
			// only return the important data
			foreach ( $this->_dataColumns as $column => $outputKey ) {
				if ( isset($data[$column]) ) {
				
					// true / false turns into an integer
					if ( $column == 'WindowOpen' ) {
						$data[$column] = ($data[$column] == 'true') ? 1 : 0;
					}

					// manual flag
					if ( $column == 'Mode' && $data[$column] == 'Temporary' ) {
						// leave it like that to prevent not auto overwrite
					}

					// eco mode flag
					else if ( $column == 'Mode' && $data[$column] != 'Auto' && isset($data['TempMode']) ) {
						$data[$column] = $data['TempMode'];
					}

					// ignore tempMeasured if no value
					if ( $column == 'MeasuredTemp' && $data[$column] == "0" ) {
						continue;
					}
					
					// ignore zero values for humidity
					if ( $column == 'humidity' && $data[$column] == "0" ) {
						continue;
					}
					
					// zero values for temperature if humidity is also missing
					if ( $column == 'temperature' && $data[$column] == "0" && isset($data['humidity']) ) {
						continue;
					}
					

					$returnData[$outputKey] = $data[$column];
				}
			}
			$return[] = $returnData;
		}
		return $return;
	}
	
	public function assignRoom ($serial, $room) {
		$device = $this->modelDevice->find($serial);
		if ( !count($device) ) {
			return false;
		}
		$device->current()->roomAssignment = $room;
		$device->current()->save();
		return true;
	}
	
	public function getCubes () {
		$deviceList = $this->modelDevice->fetchAll();
		$cubes = array();
		foreach ($deviceList as $device) {
			if ( !isset($cubes[$device->cube]) ) {
				$cubes[$device->cube] = array (
												'serial' => $device->cube,
												'deviceCount' => 0,
												'lastUpdate' => 0
											);
			}

			$ts = strtotime($device->lastUpdate);
			if ( $ts > $cubes[$device->cube]['lastUpdate']) {
				$cubes[$device->cube]['lastUpdate'] = $ts;
			}
			$cubes[$device->cube]['deviceCount']++;
		}
		return $cubes;
	}
}