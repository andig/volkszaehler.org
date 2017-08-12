/**
 * Javascript chart functions
 *
 * @author Florian Ziegler <fz@f10-home.de>
 * @author Justin Otherguy <justin@justinotherguy.org>
 * @author Steffen Vogel <info@steffenvogel.de>
 * @author Andreas Götz <cpuidle@gmx.de>
 * @copyright Copyright (c) 2011,2016 The volkszaehler.org project
 * @package default
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */
/*
 * This file is part of volkzaehler.org
 *
 * volkzaehler.org is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or any later version.
 *
 * volkzaehler.org is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * volkszaehler.org. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Update headline on zoom
 */
vz.wui.updateHeadline = function() {
	var delta = vz.options.plot.xaxis.max - vz.options.plot.xaxis.min,
			format = 'D. MMM YYYY',
			from = vz.options.plot.xaxis.min,
			to = vz.options.plot.xaxis.max;

	if (delta < 3*24*3600*1000) {
		format += ' HH:mm'; // under 3 days
		if (delta < 5*60*1000) format += ':%S'; // under 5 minutes
	}
	else {
		// only formatting days- remove 1ms to display previous day for consumption mode
		to--;
	}

	// timezone-aware dates if timezone-js is included
	from = moment(from).format(format);
	to = moment(to).format(format);

	$('#title').html(from + ' - ' + to);
};

/**
 * Draws plot to container
 *
 * The general flow of chart drawing looks like this:
 *   1. assign entities to axis (persisted)
 *   2. for each entity/series
 *      a. determine drawing style
 *      b. manipulate points matching style (steps, bars and consumption mode)
 *   3. call plot
 *      a. orderBars adjusts Xaxis min/max for consumption mode (orderBars core modification)
 *      b. tickFormatter takes care of y axis ticks (flot core modification)
 */
vz.wui.drawPlot = function () {
	vz.options.interval = vz.options.plot.xaxis.max - vz.options.plot.xaxis.min;
	vz.wui.updateHeadline();

	// assign entities to axes
	if (vz.options.plot.axesAssigned === false) {
		vz.entities.eachActiveChannel(function(entity) {
			entity.assignAxis();
		}, true);

		vz.options.plot.axesAssigned = true;
	}

	// consumption mode does some Xaxis manupulation- preserve original options
	var plotOptions = $.extend(true, {}, vz.options.plot);
	var series = [], index = 0;

	vz.entities.eachActiveChannel(function(entity) {
		var i, maxTuples = 0;

		// work on copy here to be able to redraw
		var tuples = entity.data.tuples.map(function(t) {
			return t.slice(0);
		});

		var style = vz.options.style || (entity.isConsumptionMode() ? 'bars' : entity.style);
		var linestyle = vz.options.linestyle || entity.linestyle;
		var fillstyle = parseFloat(vz.options.fillstyle || entity.fillstyle);
		var linewidth = parseFloat(vz.options.linewidth ||
			entity.selected ? vz.options.lineWidthSelected : entity.linewidth || vz.options.lineWidthDefault
		);

		var serie = {
			data: tuples,
			color: entity.color,
			label: entity.title,
			title: entity.title,
			unit:  entity.getUnitForMode(),
			yaxis: entity.assignedYaxis,
			// chartjs only
			linewidth: linewidth,
			linestyle: linestyle,
			style: style,
			consumption: entity.isConsumptionMode(),
		};

/*
		if (['lines', 'steps', 'states'].indexOf(style) >= 0) {
			$.extend(serie, {
				lines: {
					show:       true,
					steps:      style == 'steps' || style == 'states',
					fill:       fillstyle !== undefined ? fillstyle : false,
					lineWidth:  linewidth
				}
			});

			if (linestyle == 'dashed' || linestyle == 'dotted') {
				// dashes are an extension of lines
				$.extend(serie, {
					dashes: {
						show: true,
						dashLength: linestyle == 'dashed' ? 5 : [1, 2]
					}
				});
			}

			// disable interpolation when data has gaps
			if (entity.gap) {
				var minGapWidth = (entity.data.to - entity.data.from) / tuples.length;
				serie.xGapThresh = Math.max(entity.gap * 1000 * maxTuples, minGapWidth);
				plotOptions.xaxis.insertGaps = true;
			}
		}
		else if (style == 'points') {
			$.extend(serie, {
				points: {
					show:       true,
					lineWidth:  linewidth
				}
			});
		}
		else if (style == 'bars') {
			$.extend(serie, {
				bars: {
					show:       true,
					lineWidth:  0,
					fill:       entity.selected ? 1.0 : 0.8,
					order:      index++ // only used for bars
				}
			});
		}
*/

		series.push(serie);
	});

	// reorder - bars last
	series =
		series.filter(function(serie) { return serie.style != 'bars'; }).concat(
		series.filter(function(serie) { return serie.style == 'bars'; })
	);

	// configure chart
	var config = {
		type: 'line',
		data: { },
		options: {
			responsive: true,
			maintainAspectRatio: false,
			scales: {
				xAxes: [
					vz.wui.timeAxis({
						id: 'axis-bar',
						type: 'category',
						display: false,
						gridLines: {
							display: false
						},
						ticks: {
							maxRotation: 0,
							stepSize: 1,
							callback: vz.wui.tickCategoryFormatter
						}
					}),
					vz.wui.timeAxis({
						id: 'axis-time',
					}),
				],
			},
			tooltips: {
				enabled: false
			},
			hover: {
				mode: 'nearest'
			}
		}
	};

	var datasets = vz.wui.prepareDatasets(config, series);
	var yaxes = vz.wui.prepareYAxes(config, datasets);
	vz.wui.configureOptionalBarMode(config, series);

console.log("series:");
console.log(series);
console.log("datasets:");
console.log(datasets);
console.log("axes:");
console.log(yaxes);

	if (vz.chart) {
		vz.chart.destroy();
		$('#plot').empty().append('<canvas id="flot"></canvas>');
	}

	var print = $.extend({}, config);
	vz.chartconfig = JSON.stringify(print);
	// print = JSON.stringify(print);
	console.log(print);
	var chart = new Chart($('#flot'), config);

	vz.chart = chart;

	// disable automatic refresh if we are in past
	if (vz.options.refresh) {
		if (vz.wui.tmaxnow) {
			vz.wui.setTimeout();
		} else {
			vz.wui.clearTimeout('(suspended)');
		}
	} else {
		vz.wui.clearTimeout();
	}
};

/**
 * Configure chartjs y axes
 */
vz.wui.prepareYAxes = function(config, datasets) {
	var axes = [];
	vz.options.plot.yaxes.forEach(function(_axis, id) {
		var axisId = "axis" + (id+1);

		// check if axis is used - otherwise bail out
		if (!datasets.some(function(dataset) {
			return dataset.yAxisID == axisId;
		})) {
			var unit = _axis.axisLabel;
			return;
		}

		// prepare scaling for tick formatter
		var si = vz.wui.scaleNumberAndUnit(_axis.maxAbsValue);
		si.precision = Math.max(0, vz.wui.getPrecision(si.number) - 1);

		// defined axis and position
		var axis = {
			id: axisId,
			ticks: {
				callback: vz.wui.tickValueFormatter
			},
			position: _axis.position == 'right' ? 'right' : 'left',
			si: si
		};

		// hide grid lines for secondary and right axes
		if (axis.position == 'right' || (id > 0 && axis.position == 'left')) {
			axis.gridLines = {
				drawOnChartArea: false
			};
		}

		// show axis label
		if (_axis.axisLabel) {
			axis.scaleLabel = {
				display: true,
				labelString: axis.si.prefix + _axis.axisLabel
			};
		}
		if (_axis.min === undefined) {
			axis.ticks = {
				beginAtZero: true
			};
		}

		axes.push(axis);
	});

	config.options.scales.yAxes = axes;
	return axes;
};

/**
 * Map series to chartjs datasets
 */
vz.wui.prepareDatasets = function(config, series) {
	var datasets = [], labels;
	series.forEach(function(serie) {
		var dataset = {
			type: 'line',
			label: serie.label,
			backgroundColor: serie.color,
			borderColor: serie.color,
			yAxisID: 'axis' + serie.yaxis,
			xAxisID: 'axis-time',
			borderWidth: serie.linewidth,
			fill: false,
			pointRadius: 0
		};

		switch (serie.style) {
			case 'steps':
				dataset.steppedLine = 'after';
				break;
			case 'states':
				dataset.steppedLine = 'before';
				break;
			case 'bars':
				dataset.type = 'bar';
				dataset.xAxisID = 'axis-bar';
				break;
		}

		switch (serie.linestyle) {
			case 'dashed':
				dataset.borderDash = [5, 5];
				break;
			case 'dotted':
				dataset.borderDash = [1, 2];
				break;
		}

		// lines and points
		if (serie.data && serie.style != 'bars') {
			dataset.data = serie.data.map(function(t) {
				return {
					x: t[0],
					y: t[1],
				};
			});
		}

		// bars
		if (serie.data && serie.style == 'bars') {
			if (!config.data.labels) {
				// config.type = 'bar';
				// config.data.labels = vz.wui.prepareCategoryLabels();
			}

			// add missing data points
			var data = [];
			var periodLocale = vz.options.mode == 'week' ? 'isoweek' : vz.options.mode;
			var current = moment(vz.options.plot.xaxis.min).endOf(periodLocale);

			serie.data.forEach(function(t) {
				var timestamp = t[0];
				while (timestamp > current.valueOf()) {
					data.push(null);
				}

				data.push(t[1]);
				current.add(1, periodLocale);
			});

			dataset.data = data;
		}

		datasets.push(dataset);
	});

	config.data.datasets = datasets;
	return datasets;
};

vz.wui.prepareCategoryLabels = function() {
	var labels = [];

	var periodLocale = vz.options.mode == 'week' ? 'isoweek' : vz.options.mode;
	var start = moment(vz.options.plot.xaxis.min);
	var end = moment(vz.options.plot.xaxis.max);

	// create label series
	var current = moment(vz.wui.adjustTimestamp(start, true));
	// var current = moment(vz.wui.adjustTimestamp(start, false));
	while (current.valueOf() < end.valueOf()) {
		labels.push(current.valueOf());
		current.add(1, periodLocale);
	}

	return labels;
};

vz.wui.configureOptionalBarMode = function(config, series) {
	// change chart options for bar mode
	if (config.data.datasets.some(function(dataset) {
		return dataset.type == 'bar';
	})) {
		// global options
		config.type = 'bar';
		config.data.labels = vz.wui.prepareCategoryLabels();

		// scale visibility
		config.options.scales.xAxes.forEach(function(axis) {
			if (axis.id == 'axis-bar') {
				axis.display = true;
				axis.time.unit = vz.options.mode;
			}
			if (axis.id == 'axis-time') {
				// axis.display = false;
			}
		});
	}
};

vz.wui.tickValueFormatter = function(value, index, values) {
	return value;

	// format 0.0 as 0
	var precision = value == 0.0 ? 0 : this.options.si.precision;
	value = (value * this.options.si.scaler).toFixed(precision);
	return value;
};

vz.wui.tickCategoryFormatter = function(value, index, values) {
	var format = vz.options.time[vz.options.mode];
	// console.log(vz.options.mode);
	// console.log(format);
	return moment(value).format(format);
};

vz.wui.timeAxis = function(config)  {
	return $.extend({}, {
		type: 'time',
		gridLines: {
			display: true,
			offsetGridLines: false
		},
		time: {
			displayFormats: vz.options.time,
			min: vz.options.plot.xaxis.min,
			max: vz.options.plot.xaxis.max,
		},
		barPercentage: 0.8,
		ticks: {
			maxRotation: 0,
		}
	}, config);
};
