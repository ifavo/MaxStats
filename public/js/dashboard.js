function fetchUpdate () {
	// build a graph for each room
	for ( var i in building.rooms ) {
		var room = building.rooms[i];
		if ( i == 'legend' || !room.serial ) { continue; }

		// query first data set
		var serials = [];
		serials.push (room.serial);
		for ( var j in room.valves ) {
			serials.push(room.valves[j].serial);
		}
		for ( var j in room.shutter ) {
			serials.push(room.shutter[j].serial);
		}

		// special devices for the current view, e.g. outside temperature
		if ( specialDevices ) {
			for ( var j in specialDevices ) {
				serials.push(specialDevices[j]);
			}
		}

		updateQueue.push({serial: serials, from: Math.floor((tsStart+(tsOffset*1000))/1000)});
	}
	
	workQueue();
}

function workQueue () {
	if ( !updateQueue.length ) {
		$('.updateTime').text ( 'Letztes Update: ' + new Date(tsEnd*1000).toLocaleString() );
		setTimeout(fetchUpdate, 30000);
		return;
	}
	var data = updateQueue.shift();
	$.getJSON('/index/history', data, function (data) {
		renderRoom(data);
		workQueue();
	});
}

function renderRoom (data) {
	if ( !data.result ) { return; }
	var roomSerial = null;
	for ( var i in data.result ) {
		if ( data.result[i].device.type == 'Room' ) {
			roomSerial = data.result[i].device.serial;
		}
		else if ( !roomSerial ) {
			roomSerial = data.result[i].device.roomAssignment;
		}
		if ( !building.rooms[roomSerial].data[data.result[i].device.type] ) {
			building.rooms[roomSerial].data[data.result[i].device.type] = {};
		}
		if ( !building.rooms[roomSerial].data[data.result[i].device.type][data.result[i].device.serial] ) {
			building.rooms[roomSerial].data[data.result[i].device.type][data.result[i].device.serial] = [];
		}
		for ( var j in data.result[i].history ) {
			if ( !building.rooms[roomSerial].data[data.result[i].device.type][data.result[i].device.serial][data.result[i].history[j].time] ) {
				building.rooms[roomSerial].data[data.result[i].device.type][data.result[i].device.serial][data.result[i].history[j].time] = {};
			}
			building.rooms[roomSerial].data[data.result[i].device.type][data.result[i].device.serial][data.result[i].history[j].time] = data.result[i].history[j];
		}
	}

	if ( roomSerial ) {
		updateRoomPlot(roomSerial);
	}
}

var plots = {};
function updateRoomPlot(roomSerial) {
	var dataPoints = {
						tempTarget: [],
						tempMeasured: [],
						tempMode1: [],
						tempMode2: [],
						tempMode3: [],
						valves: [],
						shutter: [],
						outsideHumidity: [],
						outsideTemperature: [],
						terminator: null
					};

	var tempMin = 30;
	var tempMax = 0;
	var hasMeasuredData = false;
	var valveStatus = {};
	for ( var deviceType in building.rooms[roomSerial].data ) {
		var deviceSum = {};
		var deviceCount = 0;
		for ( var deviceSerial in building.rooms[roomSerial].data[deviceType] ) {
			deviceCount++;
			for ( var i in building.rooms[roomSerial].data[deviceType][deviceSerial] ) {
				var row = building.rooms[roomSerial].data[deviceType][deviceSerial][i];
				row.time = Number(row.time);
				var ts = row.time * 1000;
				if ( ts > tsStart ) {
					tsStart = ts; // latest data timestamp to get diff from
					tsEnd = row.time; // latest update timestamp
				}
				
				switch ( deviceType ) {
					case 'Room':
						if ( !row.tempTarget ) { continue; } // catch rooms with missing data
						if ( row.tempMeasured ) {
							hasMeasuredData = true;
						}
						row.tempTarget = parseFloat(row.tempTarget);
						row.tempMeasured = parseFloat(row.tempMeasured);
						dataPoints.tempTarget.push([ts, row.tempTarget]);
						dataPoints.tempMeasured.push([ts, row.tempMeasured]);
						
						if ( row.tempTarget > tempMax ) {
							tempMax = row.tempTarget;
						}
						if ( row.tempMeasured > tempMax ) {
							tempMax = row.tempMeasured;
						}
						if ( row.tempTarget < tempMin ) {
							tempMin = row.tempTarget;
						}
						if ( row.tempMeasured < tempMin ) {
							tempMin = row.tempMeasured;
						}
						
						switch ( row.mode ) {
							case 'Auto':
								dataPoints.tempMode1.push([ts, 1]);
								dataPoints.tempMode2.push([ts, 0]);
								dataPoints.tempMode3.push([ts, 0]);
								break;

							case 'Eco':
								dataPoints.tempMode1.push([ts, 0]);
								dataPoints.tempMode2.push([ts, 1]);
								dataPoints.tempMode3.push([ts, 0]);
								break;

							default:
							case 'Normal':
								dataPoints.tempMode1.push([ts, 0]);
								dataPoints.tempMode2.push([ts, 0]);
								dataPoints.tempMode3.push([ts, 0]);
								break;

							case 'Temporary':
							case 'Permanently':
								dataPoints.tempMode1.push([ts, 0]);
								dataPoints.tempMode2.push([ts, 0]);
								dataPoints.tempMode3.push([ts, 1]);
								break;
						}
						break;

					case 'HeatingThermostat':
						if ( !deviceSum[ts] ) {
							deviceSum[ts] = 0;
						}
						deviceSum[ts] += parseInt(row.valve);
						valveStatus[deviceSerial] = parseInt(row.valve);
						break;
					
					case 'ShutterContact':
						dataPoints.shutter.push([ts, row.open]);
						break;

					case 'logview':
						dataPoints.outsideTemperature.push([ts, parseFloat(row.temperature)]);
						dataPoints.outsideHumidity.push([ts, parseFloat(row.humidity)]);
						break;
				}
			}
		}
		
		for ( var ts in deviceSum ) {
			switch ( deviceType ) {
				case 'HeatingThermostat':
					dataPoints.valves.push([ts, deviceSum[ts]/deviceCount]);
					break;
			}
		}
	}

	tempMin -= 0.25;
	tempMax += 0.25;

	if ( tempMin > tempMax ) {
		var tmp = tempMin;
		tempMin = tempMax;
		tempMax = tmp;
		tmp = null;
	}

	var plotData = 	[
										{
											data: dataPoints.tempMode1,
											lines: { show: true, fill: true, fillColor: 'rgba(38, 138, 250, 0.1)', lineWidth: 0 },
											yaxis: 2,
											label: "Auto-Modus",
											color: 'rgba(38, 138, 250, 0.1)',
											tickFormatter: boolFormatter
										},
										{
											data: dataPoints.tempMode2,
											lines: { show: true, fill: true, fillColor: 'rgba(77, 167, 77, 0.3)', lineWidth: 0 },
											yaxis: 2,
											label: "Eco-Modus",
											color: 'rgba(77, 167, 77, 0.3)',
											tickFormatter: boolFormatter
										},
										{
											data: dataPoints.tempMode3,
											lines: { show: true, fill: true, fillColor: 'rgba(255, 167, 167, 0.3)', lineWidth: 0 },
											yaxis: 2,
											label: "Manueller-Modus",
											color: 'rgba(77, 167, 77, 0.3)',
											tickFormatter: boolFormatter
										},
										{
											data: dataPoints.tempMeasured,
											lines: { show: true, fill: true, fillColor: 'rgba(237, 194, 64, 0.5)' },
											label: "Ist-Temperatur",
											color: 'rgba(237, 194, 64, 1.0)',
											tickFormatter: degreeFormatter
										},
										{
											data: dataPoints.tempTarget,
											lines: { show: !(hasMeasuredData), fill: false },
											label: "Ziel-Temperatur",
											dashes: { show: hasMeasuredData }, // performance issues!
											shadowSize: (hasMeasuredData) ? false : 2,
											color: (hasMeasuredData) ? 'rgba(203, 75, 75, 0.75)' : 'rgba(203, 75, 75, 1.0)',
											tickFormatter: degreeFormatter
										},
										{
											data: dataPoints.outsideTemperature,
											lines: { show: true, fill: false },
											label: "Außentemperatur",
											shadowSize: false,
											yaxis: 4,
											color: 'rgba(203, 75, 75, 0.5)',
											tickFormatter: degreeFormatter
										},
										{
											data: dataPoints.outsideHumidity,
											lines: { show: true, fill: false },
											label: "Luftfeuchtigkeit",
											shadowSize: false,
											yaxis: 3,
											color: 'rgba(0, 128, 255, 0.5)',
											tickFormatter: percentFormatter
										},
										{
											data: dataPoints.valves,
											lines: { show: true, fill: true, fillColor: 'rgba(0, 28, 145, 0.5)', lineWidth: 1  },
											yaxis: 3,
											label: "Ventilöffnung",
											color: 'rgba(0, 28, 145, 1.0)',
											tickFormatter: percentFormatter
										},
										{
											data: dataPoints.shutter,
											lines: { show: true, fill: true, fillColor: 'rgba(0, 128, 255, 0.5)', lineWidth: 0 },
											yaxis: 2,
											label: "Fensterstatus",
											color: 'rgba(0, 128, 255, 0.5)',
											tickFormatter: boolFormatter
										}
		];

	if ( !plots[roomSerial] ) {
		plots[roomSerial] = $.plot(
				$("div.graph[serial=" + roomSerial + "] > .plot"),
											plotData,
											{
												xaxes: [ { mode: 'time', tickSize: [(timeToDisplay<48)?1:6, "hour"] } ],
												yaxes: [ { min: tempMin, max: tempMax, tickFormatter: degreeFormatter, position: "right" }, {min: 0, max: 1, position: "right", show: false}, { min: 0, max: 100, tickFormatter: percentFormatter }, { tickFormatter: degreeFormatter, position: "right" } ],
												legend: { show: (roomSerial == 'legend') ? true : false, position: 'nw' },
												grid: { hoverable: true },
												selection: { mode: "x" }
											}
		);
		$("div.graph[serial=" + roomSerial + "] > .plot").bind("plothover", plotHover);
		$("div.graph[serial=" + roomSerial + "] > .plot").hide().fadeIn();
	}
	else {
		plots[roomSerial].setData(plotData);
		plots[roomSerial].setupGrid();
		plots[roomSerial].draw();
	}



	// room box
	// no measured temp but outside temp? copy it over for outside information!
	if ( !dataPoints.tempMeasured.length && dataPoints.outsideTemperature.length > 0 ) {
		dataPoints.tempMeasured = dataPoints.tempTarget = dataPoints.outsideTemperature;
	}

	if ( roomSerial == "legend" || !dataPoints.tempMeasured.length ) {
		return;
	}

	$('.status[serial='+roomSerial+']').parent().removeClass('directionUp').removeClass('directionDown');
	if ( dataPoints.tempMeasured.length > 10 ) {
		var tempDiff = 0.2;
		if ( (dataPoints.tempMeasured[dataPoints.tempMeasured.length-1][1] - dataPoints.tempMeasured[dataPoints.tempMeasured.length-10][1]) >= tempDiff ) {
			$('.status[serial='+roomSerial+']').parent().addClass('directionUp');
		}
		else if ( (dataPoints.tempMeasured[dataPoints.tempMeasured.length-10][1] - dataPoints.tempMeasured[dataPoints.tempMeasured.length-1][1]) >= tempDiff ) {
			$('.status[serial='+roomSerial+']').parent().addClass('directionDown');
		}
	}

	// latest entry
	$('.status[serial='+roomSerial+']').find('.tempCurrent').text (  (dataPoints.tempMeasured) ? dataPoints.tempMeasured[dataPoints.tempMeasured.length-1][1] : dataPoints.tempTarget[dataPoints.tempTarget.length-1][1] );
	$('.status[serial='+roomSerial+']').find('.tempTarget').text ( dataPoints.tempTarget[dataPoints.tempTarget.length-1][1] );

	
	if ( dataPoints.tempMeasured.length > 40 ) {
		// make the latest beautiful
		var latest = [];
		latest.push(dataPoints.tempMeasured[dataPoints.tempTarget.length-10][1]);
		latest.push(dataPoints.tempMeasured[dataPoints.tempTarget.length-20][1]);
		latest.push(dataPoints.tempMeasured[dataPoints.tempTarget.length-30][1]);
	
		var opacitySteps = 1/(latest.length+1);
		var opacityValue = 1.0;
		if ( latest.length ) {
			$('.status[serial='+roomSerial+']').find('.tempLatest').html('');
			while ( latest.length ) {
				var suffix = (latest.length > 1) ? '°C, ' : '°C';
				$('<span>').css("opacity", opacityValue).text(latest.shift()+suffix).appendTo( $('.status[serial='+roomSerial+']').find('.tempLatest') );
				opacityValue -= opacitySteps;
			}
		}
		else {
			$('.status[serial='+roomSerial+']').find('.tempLatest').html('&nbsp;');
		}
	}
	
	// valve opening
	var valveList = [];
	$.each(valveStatus, function (k,v) {
		if ( v > 30 ) {
			v = '<span class="warm">' + v + '%</span>';
		}
		else {
			v = v + '%';
		}
		valveList.push(v);
	});
	if ( valveList.length ) {
		$('.status[serial='+roomSerial+']').find('.valveOpening').html ( ((valveList.length > 1) ? 'Ventile: ' : 'Ventil: ') + valveList.join(' | ') );
	}
	else {
		$('.status[serial='+roomSerial+']').find('.valveOpening').html('&nbsp;');
	}
		
	// window status
	if ( dataPoints.shutter.length && dataPoints.shutter[dataPoints.shutter.length-1][1] ) {
		$('.status[serial='+roomSerial+']').find('.windowOpening').text('Fenster offen');
	}
	else {
		$('.status[serial='+roomSerial+']').find('.windowOpening').html('&nbsp;');
	}
}

function showTooltip(x, y, contents) {
	$('<div id="tooltip">' + contents + '</div>').css( {
		position: 'absolute',
		display: 'none',
		top: y + 5,
		left: x + 5,
		border: '1px solid #fdd',
		padding: '2px',
		'background-color': '#fee',
		opacity: 0.80
	}).appendTo("body").fadeIn(200);
}

var previousPoint = null;
function plotHover (event, pos, item) {
	$("#x").text(pos.x.toFixed(2));
	$("#y").text(pos.y.toFixed(2));

		if (item) {
			if (previousPoint != item.dataIndex) {
				previousPoint = item.dataIndex;

				$("#tooltip").remove();
				var x = item.datapoint[0].toFixed(0),
					y = item.datapoint[1];
				if ( item.series.tickFormatter ) {
					y = item.series.tickFormatter(y, {tickDecimals: 1});
				}
				var xDate = new Date();
				xDate.setTime(x)
				showTooltip(item.pageX, item.pageY,
							"[" + xDate.toLocaleTimeString() + " ("+xDate.getDate() + '.' + (xDate.getMonth()+1) + '.'+")] " + item.series.label + ": " + y);
			}
		}
		else {
			$("#tooltip").remove();
			previousPoint = null;            
		}
}

$.fn.spin = function(opts) {
  this.each(function() {
    var $this = $(this),
        data = $this.data();

    if (data.spinner) {
      data.spinner.stop();
      delete data.spinner;
    }
    if (opts !== false) {
      data.spinner = new Spinner($.extend({color: $this.css('color')}, opts)).spin(this);
    }
  });
  return this;
};
