<?php


class favo_Max_Cube {
	private $_ip = null;
	private $_port = 62910;
	private $_data = array();
	private $_objData = array();
	
	public function __construct($ip) {
		$this->_ip = $ip;
	}

	public function getDevices () {
		$this->fetchData();
	}

	private function fetchData () {
		$fp = fsockopen($this->_ip, $this->_port, $errno, $errstr, 5);
		if ( !$fp ) {
			throw new Exception("could not connect to {$this->_ip}");
		}
		socket_set_blocking($fp,true);

		while (!feof($fp)) {
			$line = fgets($fp);
			$this->_data[] = trim($line);
			if ( !trim($line) || substr($line, 0, 2) == 'L:' ) {
				break;
			}
		}
		fclose($fp);
		
		file_put_contents('lastread.txt', serialize($this->_data));
		$this->convertData();
		return true;
	}

	private function convertData () {
		$deviceCount = 0;
		foreach ( $this->_data as $block ) {
			$type = substr($block, 0, 2);
			$data = explode(',', substr($block, 3));
			$objData = new StdClass;

			switch ( $type ) {
			
				// Header / Hello
				case 'H:':
					$objData->serial = $data[0];
					$objData->rfAddress = $data[1];
					$objData->firmware = hexdec($data[2]);
					$objData->httpConnectionId = hexdec($data[4]);
					$objData->date = hexdec(substr($data[7],4,2)).".".hexdec(substr($data[7],2,2)).".".hexdec(substr($data[7],0,2));
					$objData->time = hexdec(substr($data[8],0,2)).":".hexdec(substr($data[8],2,2));
					break;
				
				// Meta-Information
				case 'M:':
					$binary = base64_decode($data[2]);
					$pos = 2;
					$objData->roomCount = (ord(substr($binary,$pos,1)));
					$pos++;
					$objData->rooms = array();
					for ( $i = 1; $i <= $objData->roomCount; $i += 1 ) {
						$roomId = ord(substr($binary,$pos,1));
						$pos++;
						$nameLength = ord(substr($binary,$pos,1));
						$pos++;
						$roomName = substr($binary, $pos, $nameLength);
						$pos += $nameLength;
						$roomId2 = ord(substr($binary,$pos,3));
						$pos += 3;
						
						$objData->rooms[$roomId] = (object)array(
															'id' => $roomId,
															'groupId' => $roomId2,
															'name' => $roomName
											);
											
					}

					$deviceCount = $objData->deviceCount = (ord(substr($binary,$pos,1)));
					$pos++;
					$objData->devices = array();
					for ( $i = 1; $i <= $objData->deviceCount; $i += 1 ) {
						$deviceType = dechex(ord(substr($binary,$pos,1)));
						$pos++;
						$rfAddress = dechex(ord(substr($binary,$pos,3)));
						$pos += 3;
						$serial = substr($binary,$pos,10);
						$pos += 10;
						$nameLength = ord(substr($binary,$pos,1));
						$pos++;
						$deviceName = substr($binary, $pos, $nameLength);
						$pos += $nameLength;
						$roomId = ord(substr($binary,$pos,1));
						$pos++;
						
						$objData->devices[$serial] = (object)array(
															'rfAddress' => $rfAddress,
															'type' => $deviceType,
															'serial' => $serial,
															'name' => $deviceName,
															'roomId' => $roomId
											);
					}
					break;
				
				// Configuration information per device
				case 'C:':
					$binary = base64_decode($data[1]);
					$rfAddress = $data[0];

					$pos = 0;
					$pos++;
					$rfAddress = dechex(ord(substr($binary,$pos,3)));
					$pos += 3;
					$deviceType = dechex(ord(substr($binary,$pos,1)));
					$pos++;
					$pos += 3;
					$serial = substr($binary,$pos,10);
					$pos += 10;
					
					$objData->deviceStatus = (object)array (
															'rfAddress' => $rfAddress,
															'type' => $deviceType,
															'serial' => $serial
												);

					$config = array();
					switch ( $deviceType ) {
							// Cube
							case 0:
								$objData->typeName = 'Cube';
								$config['portalEnabled'] = dechex(ord(substr($binary,$pos,1)));
								$pos += 1;
								$pos += 66;
								$config['portalUrl'] = substr($binary,$pos,strpos($binary, chr(0), $pos)-$pos );
								break;

							// HeatingThermostat
							case 1:
								$objData->typeName = 'HeatingThermostat';
								$config['ComfortTemperature'] = ord(substr($binary,$pos,1))/2;
								$pos++;
								$config['EcoTemperature'] = ord(substr($binary,$pos,1))/2;
								$pos++;
								$config['MaxSetPointTemperature'] = ord(substr($binary,$pos,1))/2;
								$pos++;
								$config['MinSetPointTemperature'] = ord(substr($binary,$pos,1))/2;
								$pos++;
								$config['TemperatureOffset'] = ord(substr($binary,$pos,1))/2-3.5;
								$pos++;
								$config['WindowOpenTemperature'] = ord(substr($binary,$pos,1))/2;
								$pos++;
								$config['WindowOpenDuration'] = ord(substr($binary,$pos,1));
								$pos++;
								
								// this seems to be wrong
								$config['Boost'] = str_pad(decbin(ord(substr($binary,$pos,1))),8,'0',STR_PAD_LEFT);
								$pos++;
								$config['BoostDuration'] = bindec(substr($config['Boost'],0,3))*5;
								if ( $config['BoostDuration'] > 30 ) {
									$config['BoostDuration'] = 30;
								}
								$config['BoostValue'] = bindec(substr($config['Boost'],3,5))*5;

								$config['Decalcification'] = str_pad(decbin(ord(substr($binary,$pos,1))),8,'0',STR_PAD_LEFT);
								$pos++;
								$config['DecalcificationWeekday'] = bindec(substr($config['Decalcification'],0,3));
								$config['DecalcificationTime'] = bindec(substr($config['Decalcification'],3,5));
								$config['MaximumValveSetting'] = dechex(ord(substr($binary,$pos,1)))*(100/255);
								$pos++;
								$config['ValveOffset'] = dechex(ord(substr($binary,$pos,1)))*(100/255);
								
								// next: the weekly program
								break;
							
							// WallMountedThermostat
							case 3:
								$objData->typeName = 'WallMountedThermostat';
								break;

							// ShutterContact
							case 4:
								$objData->typeName = 'ShutterContact';
								break;

							// PushButton
							case 5:
								$objData->typeName = 'PushButton';
								break;
					}
					$objData->deviceStatus->config = $config;
					break;


				// Status information
				case 'L:':
					$binary = base64_decode($data[0]);

					$pos = 0;
					for($i = 1 ; $i <= $deviceCount; $i += 1 ) {
						$dataType = ord(substr($binary,$pos,1));
						$pos++;
						$rfAddress = dechex(ord(substr($binary,$pos,3)));
						$pos += 3;
						$pos += 1;
						$data1 = str_pad(decbin(ord(substr($binary,$pos,1))),8,"0",STR_PAD_LEFT);
						$pos++;
						$data2 = str_pad(decbin(ord(substr($binary,$pos,1))),8,"0",STR_PAD_LEFT);
						$pos++;

						$dataConfig = array(
												'rfAddress' => $rfAddress,
												'dataType' => $dataType,
												'data1' => $data1,
												'data2' => $data2
										);

 						if ( $dataType == 11 || $dataType == 12 ) {
 							$dataConfig['Temperature'] = ord(substr($binary,$pos,1));
 							$pos++;
 							$dataConfig['DateUntil'] = str_pad(decbin(ord(substr($binary,$pos,2))),8,"0",STR_PAD_LEFT);
 							$pos += 2;
 							$dataConfig['TimeUntil'] = str_pad(decbin(ord(substr($binary,$pos,1))),8,"0",STR_PAD_LEFT);
 							$pos++;
 							
 							if ( $dataType == 12 ) {
 								$pos++;
 							}
 						}

					break;
			}

			print_r($objData);
		}
	}
}
