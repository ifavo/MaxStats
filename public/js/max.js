var updateTimer = (document.location.href.indexOf('debug') >= 0) ? 100 : 1000;
var fullWait = 60*1000;

function percentFormatter(v,axis) {
	return v.toFixed(axis.tickDecimals) +"%";
}
function boolFormatter(v,axis) {
	return (v.toFixed(axis.tickDecimals) > 0) ? 'An' : 'Aus';
}
function degreeFormatter(v,axis) {
	return v.toFixed(axis.tickDecimals) +"°";
}

var updateList = [];
function initAutoUpdate() {
	$('.plot').each (function () {
		$('.plot').parent().hide();
		updateList.push($(this));
	});
	updatePlots();
}

var updateRuns = 0;
var latestVersion = new Date();
var graphWidth = graphMinWidth = 600;
var graphsPerRow = 1;
var spinnerOptions = {
  lines: 5, // The number of lines to draw
  length: 15, // The length of each line
  width: 11, // The line thickness
  radius: 0, // The radius of the inner circle
  corners: 1, // Corner roundness (0..1)
  rotate: 55, // The rotation offset
  color: 'rgba(237, 194, 64, 0.5)', // #rgb or #rrggbb
  speed: 0.6, // Rounds per second
  trail: 100, // Afterglow percentage
  shadow: false, // Whether to render a shadow
  hwaccel: false, // Whether to use hardware acceleration
  className: 'spinner', // The CSS class to assign to the spinner
  zIndex: 2e9, // The z-index (defaults to 2000000000)
  top: '40px', // Top position relative to parent in px
  left: 'auto' // Left position relative to parent in px
};

function updatePlots () {
	var plot = updateList.shift();
	$.getJSON('/index/history', {serial: plot.attr('serial'), from: tsStart, to: tsEnd}, renderPlot);
	updateList.push(plot);

	updateRuns++;
	$('.updateTime').text ( 'Letztes Update: ' + latestVersion.toLocaleString() );
	
	// sort devices once after all is read
	if ( updateRuns == (updateList.length+1) ) {
		sortDevicesByRoom();
	}
	
	// resize graphs every complete run
	var availableSpace = $(document).width() - 60;
	graphsPerRow = Math.floor(availableSpace / graphMinWidth);
	graphWidth = Math.floor(availableSpace / graphsPerRow);
	graphWidth += Math.floor( (availableSpace - (graphsPerRow*graphWidth)) / graphsPerRow);
}

var roomCache = {};
function renderPlot (data) {
	try {
		// fade in in the first run
		if ( updateRuns <= updateList.length ) {
			$(".plot[serial=" + data.device.serial+"]").parent().fadeIn();
		}
		$(".plot[serial=" + data.device.serial+"]").width(graphWidth-((graphsPerRow>1)?40:0)).parent().width(graphWidth);
	
		switch ( data.device.type ) {
			case 'HeatingThermostat':
				var dataPoints = [];
				for ( var j in data.history ) {
					var ts = Number(data.history[j].time) * 1000;
					dataPoints.push([ts, data.history[j].valve]);
	
					if ( ts > latestVersion.getTime() ) {
						latestVersion.setTime(ts);
					}
				}
	
				$(".plot[serial=" + data.device.serial+"]").parent().find('select.room').val ( data.device.roomAssignment );

				var dataRows = [];

					
				if ( data.device.roomAssignment && roomCache[data.device.roomAssignment] && roomCache[data.device.roomAssignment].window ) {
					dataRows.push ( {
													data: roomCache[data.device.roomAssignment].window,
													lines: { show: true, fill: true, fillColor: 'rgba(0, 128, 255, 0.5)', lineWidth: 0 },
													yaxis: 3,
													label: "Fensteröffnung",
													color: 'rgba(0, 128, 255, 0.5)'
												} );
				}


				if ( data.device.roomAssignment && roomCache[data.device.roomAssignment] && roomCache[data.device.roomAssignment].measured && roomCache[data.device.roomAssignment].target) {
					if ( !roomCache[data.device.roomAssignment] ) {
						roomCache[data.device.roomAssignment] = {};
					}
					if ( !roomCache[data.device.roomAssignment].valve ) {
						roomCache[data.device.roomAssignment].valve = {};
					}
					roomCache[data.device.roomAssignment].valve[data.device.serial] = dataPoints[dataPoints.length-1][1];

					var tempMin = 30;
					var tempMax = 0;

					// min/max temperature values
					for ( var i in roomCache[data.device.roomAssignment].measured ) {
						if ( parseFloat(roomCache[data.device.roomAssignment].measured[i][1]) < tempMin ) {
							tempMin = roomCache[data.device.roomAssignment].measured[i][1];
						}
						if ( parseFloat(roomCache[data.device.roomAssignment].target[i][1]) < tempMin ) {
							tempMin = roomCache[data.device.roomAssignment].target[i][1];
						}

						if ( parseFloat(roomCache[data.device.roomAssignment].measured[i][1]) > tempMax ) {
							tempMax = parseFloat(roomCache[data.device.roomAssignment].measured[i][1]);
						}
						if ( parseFloat(roomCache[data.device.roomAssignment].target[i][1]) > tempMax ) {
							tempMax = parseFloat(roomCache[data.device.roomAssignment].target[i][1]);
						}
					}
					tempMin -= 0.25;
					tempMax += 0.25;


					dataRows.push ( {
														data: roomCache[data.device.roomAssignment].measured,
														lines: { show: true, fill: true, fillColor: 'rgba(237, 194, 64, 0.5)', lineWidth: 1 },
														yaxis: 2,
														label: "Ist-Temperatur",
														color: 'rgba(237, 194, 64, 1.0)',
														shadowSize: false
													} );
					dataRows.push ( {
														data: roomCache[data.device.roomAssignment].target,
														yaxis: 2,
														label: "Ziel-Temperatur",
														color: 'rgba(203, 75, 75, 0.5)',
														dashes: { show: true },
														shadowSize: false
													} );
				}

				dataRows.push ( {
													data: dataPoints,
													lines: { show: true, fill: true, fillColor: 'rgba(0, 28, 145, 0.5)', lineWidth: 1  },
													label: "Ventilöffnung",
													color: 'rgba(0, 28, 145, 1.0)'
												} );


				$.plot(
						$(".plot[serial=" + data.device.serial+"]"),
											dataRows
											,
											{
												xaxes: [ { mode: 'time' } ],
												yaxes: [ { min: 0, max: 100, tickFormatter: percentFormatter }, {min: tempMin, max: tempMax, tickFormatter: degreeFormatter, position: "right"}, {min: 0, max: 1, show: false} ],
												legend: { show: false, position: 'nw' },
												selection: { mode: "x" }
											}
				);
				break;

			case 'ShutterContact':
				var dataPoints = [];
				for ( var j in data.history ) {
					var ts = Number(data.history[j].time) * 1000;
					dataPoints.push([ts, data.history[j].open]);
	
					if ( ts > latestVersion.getTime() ) {
						latestVersion.setTime(ts);
					}
				}

				$(".plot[serial=" + data.device.serial+"]").parent().find('select.room').val ( data.device.roomAssignment );

				if ( data.device.roomAssignment && roomCache[data.device.roomAssignment] && roomCache[data.device.roomAssignment].measured && roomCache[data.device.roomAssignment].target) {
					if ( !roomCache[data.device.roomAssignment] ) {
						roomCache[data.device.roomAssignment] = {};
					}
					if ( !roomCache[data.device.roomAssignment].valve ) {
						roomCache[data.device.roomAssignment].valve = {};
					}

					if ( !roomCache[data.device.roomAssignment].window ) {
						roomCache[data.device.roomAssignment].window = {};
					}
					roomCache[data.device.roomAssignment].window = dataPoints;

					var tempMin = 30;
					var tempMax = 0;

					// min/max temperature values
					for ( var i in roomCache[data.device.roomAssignment].measured ) {
						if ( parseFloat(roomCache[data.device.roomAssignment].measured[i][1]) < tempMin ) {
							tempMin = roomCache[data.device.roomAssignment].measured[i][1];
						}
						if ( parseFloat(roomCache[data.device.roomAssignment].target[i][1]) < tempMin ) {
							tempMin = roomCache[data.device.roomAssignment].target[i][1];
						}

						if ( parseFloat(roomCache[data.device.roomAssignment].measured[i][1]) > tempMax ) {
							tempMax = parseFloat(roomCache[data.device.roomAssignment].measured[i][1]);
						}
						if ( parseFloat(roomCache[data.device.roomAssignment].target[i][1]) > tempMax ) {
							tempMax = parseFloat(roomCache[data.device.roomAssignment].target[i][1]);
						}
					}
					tempMin -= 0.25;
					tempMax += 0.25;

					$.plot(
							$(".plot[serial=" + data.device.serial+"]"),
												[
													{
														data: roomCache[data.device.roomAssignment].measured,
														lines: { show: true, fill: true, fillColor: 'rgba(237, 194, 64, 0.5)', lineWidth: 1 },
														yaxis: 2,
														label: "Ist-Temperatur",
														color: 'rgba(237, 194, 64, 1.0)',
														shadowSize: false
													},
													{
														data: roomCache[data.device.roomAssignment].target,
														yaxis: 2,
														label: "Ziel-Temperatur",
														color: 'rgba(203, 75, 75, 0.5)',
														dashes: { show: true },
														shadowSize: false
													},
													{
														data: dataPoints,
														lines: { show: true, fill: true, fillColor: 'rgba(0, 128, 255, 0.5)' },
														label: "Fensterstatus",
														color: 'rgba(0, 128, 255, 0.5)'
													}
												],
												{
													xaxes: [ { mode: 'time' } ],
													yaxes: [ { min: 0, max: 1, show: false }, {min: tempMin, max: tempMax, tickFormatter: degreeFormatter, position: "right"} ],
													legend: { show: false, position: 'nw' },
													selection: { mode: "x" }
												}
					);
				}
				else {
					$.plot(
							$(".plot[serial=" + data.device.serial+"]"),
													[{
														data: dataPoints,
														lines: { show: true, fill: true, fillColor: 'rgba(0, 128, 255, 0.5)' },
														color: 'rgba(0, 128, 255, 0.5)'
													}],
													{
														xaxes: [ { mode: 'time' } ],
														yaxes: [ { min: 0, max: 1, show: false } ],
														selection: { mode: "x" }
													}
					);
				}
				break;


			case 'logview':
				var dataPoints = [[], []];
				for ( var j in data.history ) {
					var ts = Number(data.history[j].time) * 1000;
					dataPoints[0].push([ts, data.history[j].temperature]);
					dataPoints[1].push([ts, data.history[j].humidity]);
	
					if ( ts > latestVersion.getTime() ) {
						latestVersion.setTime(ts);
					}
				}

				$(".plot[serial=" + data.device.serial+"]").parent().find('select.room').val ( data.device.roomAssignment );

				$.plot(
						$(".plot[serial=" + data.device.serial+"]"),
												[
													{
														data: dataPoints[0],
														lines: { show: true, fill: false, fillColor: 'rgba(203, 75, 75, 0.5)' },
														label: "Temperatur",
														color: 'rgba(203, 75, 75, 1.0)'
													},
													{
														data: dataPoints[1],
														lines: { show: true, false: true, fillColor: 'rgba(0, 128, 255, 0.5)' },
														label: "Luftfeuchtigkeit",
														color: 'rgba(0, 128, 255, 1.0)',
														yaxis: 2
													}
												],
												{
													xaxes: [ { mode: 'time' } ],
													yaxes: [ { show: true, tickFormatter: degreeFormatter }, { show: true, tickFormatter: percentFormatter, position: "right" } ],
													legend: { show: false, position: 'nw' },
													selection: { mode: "x" }
												}
				);
				break;

			case 'Room':
				var dataPoints = [[], [], [], [], []];
				var latestDatasets = [];
				for ( var j in data.history ) {
					var ts = Number(data.history[j].time) * 1000;
					dataPoints[0].push([ts, data.history[j].tempTarget]);
					dataPoints[1].push([ts, data.history[j].tempMeasured]);
					switch (data.history[j].mode) {
						case 'Auto':
							dataPoints[2].push([ts, 1]);
							dataPoints[3].push([ts, 0]);
							dataPoints[4].push([ts, 0]);
							break;
	
						case 'Eco':
							dataPoints[2].push([ts, 0]);
							dataPoints[3].push([ts, 1]);
							dataPoints[4].push([ts, 0]);
							break;
	
						default:
						case 'Normal':
							dataPoints[2].push([ts, 0]);
							dataPoints[3].push([ts, 0]);
							dataPoints[4].push([ts, 0]);
							break;
	
						case 'Temporary':
						case 'Permanently':
							dataPoints[2].push([ts, 0]);
							dataPoints[3].push([ts, 0]);
							dataPoints[4].push([ts, 1]);
							break;
					}
					latestDatasets.push(data.history[j]);
					if ( latestDatasets.length > 10 ) {
						latestDatasets.shift();
					}
					
					if ( ts > latestVersion.getTime() ) {
						latestVersion.setTime(ts);
					}
				}
				updateRoomBox(data.device, latestDatasets);
	
				var hasMeasuredValues = (dataPoints[1][0][1] > 0) ? true : false;
				if ( !roomCache[data.device.serial] ) {
					roomCache[data.device.serial]=  {};
				}
				roomCache[data.device.serial].valve = {};
				roomCache[data.device.serial].measured = dataPoints[1];
				roomCache[data.device.serial].target = dataPoints[0];
				$.plot(
						$(".plot[serial=" + data.device.serial+"]"),
											[
													{
														data: dataPoints[2],
														lines: { show: true, fill: true, fillColor: 'rgba(38, 138, 250, 0.1)', lineWidth: 0 },
														yaxis: 2,
														label: "Auto-Modus",
														color: 'rgba(38, 138, 250, 0.1)'
													},
													{
														data: dataPoints[3],
														lines: { show: true, fill: true, fillColor: 'rgba(77, 167, 77, 0.3)', lineWidth: 0 },
														yaxis: 2,
														label: "Eco-Modus",
														color: 'rgba(77, 167, 77, 0.3)'
													},
													{
														data: dataPoints[4],
														lines: { show: true, fill: true, fillColor: 'rgba(255, 167, 167, 0.3)', lineWidth: 0 },
														yaxis: 2,
														label: "Normal-Modus",
														color: 'rgba(77, 167, 77, 0.3)'
													},
													{
														data: dataPoints[1],
														lines: { show: true, fill: true, fillColor: 'rgba(237, 194, 64, 0.5)' },
														label: "Ist-Temperatur",
														color: 'rgba(237, 194, 64, 1.0)'
													},
													{
														data: dataPoints[0],
														lines: { show: !hasMeasuredValues, fill: false },
														label: "Ziel-Temperatur",
														dashes: { show: hasMeasuredValues },
														shadowSize: (hasMeasuredValues) ? false : 2,
														color: (hasMeasuredValues) ? 'rgba(203, 75, 75, 0.75)' : 'rgba(203, 75, 75, 1.0)'
													}
												],
													{
														xaxes: [ { mode: 'time' } ],
														yaxes: [ { min: 0, max: 30, tickFormatter: degreeFormatter }, {min: 0, max: 1, position: "right", show: false} ],
														legend: { show: false, position: 'nw' },
														selection: { mode: "x" }
													}
				);
				break;
		}
	}
	catch (e) {
		
		$(".plot[serial=" + data.device.serial+"]").html('').append( $('<center>').append( $('<span>').spin(spinnerOptions) ) );
	}
	if ( updateRuns > updateList.length ) {
		setTimeout(updatePlots, ( ( !(updateList.length % updateRuns) ) ?fullWait:updateTimer));
	}
	else {
		updatePlots();
	}
}


function updateRoomBox (device, latestDatasets) {
	// temperature going up each time?
	var previousTemperature = 0;
	var direction = {up: 0, down: 0, neutral: 0};
	var latest = [];
	for ( var i in latestDatasets ) {
		if ( latestDatasets[i].tempMeasured && latestDatasets[i].tempMeasured > previousTemperature ) {
			previousTemperature = latestDatasets[i].tempMeasured;
			direction.up++;
		}
		else if ( latestDatasets[i].tempMeasured && latestDatasets[i].tempMeasured < previousTemperature ) {
			previousTemperature = latestDatasets[i].tempMeasured;
			direction.down++;
		}
		else if ( latestDatasets[i].tempMeasured ) {
			previousTemperature = latestDatasets[i].tempMeasured;
			direction.neutral++;
		}
		
		if ( latestDatasets[i].tempMeasured ) {
			latest.push(latestDatasets[i].tempMeasured);
		}
	}

	$('.status[serial='+device.serial+']').parent().removeClass('directionUp').removeClass('directionDown');
	if ( direction.up > direction.down && direction.up >= 2 ) {
		$('.status[serial='+device.serial+']').parent().addClass('directionUp');
	}
	else if ( direction.down > direction.up && direction.down >= 2 ) {
		$('.status[serial='+device.serial+']').parent().addClass('directionDown');
	}

	// latest entry
	var data = latestDatasets.pop();
	$('.status[serial='+device.serial+']').find('.tempCurrent').text (  (data.tempMeasured) ? data.tempMeasured : data.tempTarget );
	$('.status[serial='+device.serial+']').find('.tempTarget').text ( data.tempTarget );

	// make the latest beautiful
	while ( latest.length > 3 ) {
		latest.shift();
	}
	latest.reverse();
	var opacitySteps = 1/(latest.length+1);
	var opacityValue = 1.0;
	if ( latest.length ) {
		$('.status[serial='+device.serial+']').find('.tempLatest').html('');
		while ( latest.length ) {
			var suffix = (latest.length > 1) ? '°C, ' : '°C';
			$('<span>').css("opacity", opacityValue).text(latest.pop()+suffix).appendTo( $('.status[serial='+device.serial+']').find('.tempLatest') );
			opacityValue -= opacitySteps;
		}
	}
	else {
		$('.status[serial='+device.serial+']').find('.tempLatest').html('&nbsp;');
	}

	// valve opening
	if ( roomCache[device.serial] && roomCache[device.serial].valve ) {
		var valveList = [];
		$.each(roomCache[device.serial].valve, function (k,v) {
			if ( v > 40 ) {
				v = '<span class="warm">' + v + '%</span>';
			}
			else {
				v = v + '%';
			}
			valveList.push(v);
		});
		if ( valveList.length ) {
			$('.status[serial='+device.serial+']').find('.valveOpening').html ( ((valveList.length > 1) ? 'Ventile: ' : 'Ventil: ') + valveList.join(' | ') );
		}
		else {
			$('.status[serial='+device.serial+']').find('.valveOpening').html('&nbsp;');
		}
	}
}


function updateRoomAssignment (sel) {
	var serial = sel.parentsUntil('.graph').parent().find('.plot').attr('serial');
	var room = sel.val();
	$.getJSON('/index/config', {cmd: "assignRoom", serial: serial, room: sel.val()});
	sortDevicesByRoom();
}

function sortDevicesByRoom () {
	$('#tabs-rooms').find('.graph').each(function () {
		var serial = $(this).find('.plot').attr('serial');
		$('#device-list').find('.graph').each(function () {
			if ( $(this).find('select.room').val() == serial ) {
				$(this).detach();
				$(this).appendTo($('#device-list'));
			}
		});
	});
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

var tsStart = 0;
function setStartTime (ts) {
	tsStart = Math.floor(ts);
}
setStartTime(Math.floor(new Date().getTime()/1000)-86400);

var tsEnd = 0;
function setEndTime (ts) {
	tsEnd = ts;
}

function isiPhone(){
    return (
        //Detect iPhone
        (navigator.platform.indexOf("iPhone") != -1) ||
        //Detect iPod
        (navigator.platform.indexOf("iPod") != -1)
    );
}