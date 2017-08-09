/**
 * Initialization and configuration of frontend
 *
 * @author Florian Ziegler <fz@f10-home.de>
 * @author Justin Otherguy <justin@justinotherguy.org>
 * @author Steffen Vogel <info@steffenvogel.de>
 * @copyright Copyright (c) 2011, The volkszaehler.org project
 * @package default
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */
/*
 * This file is part of volkzaehler.org
 *
 * volkzaehler.org is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * volkzaehler.org is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with volkszaehler.org. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * volkszaehler.org namespace
 *
 * holds all data, options and functions for the frontend
 * we dont want to pollute the global namespace
 */
var vz = {
	entities: [],			// entity properties + data
	middleware: [],		// array of all known middlewares
	wui: {						// web user interface
		dialogs: { },
		requests: {
			issued: 0,
			completed: 0
		},
		timeout: null
	},
	capabilities: {		// debugging and runtime information from middleware
		definitions: {}	// definitions of entities & properties
	},
	plot: { },				// flot instance
	options: { }			// options loaded from cookies in options.js
};

$.plot = {
	formatDate: function(d, fmt, monthNames, dayNames) {
		if (typeof d.strftime == "function") {
			return d.strftime(fmt);
		}

		var leftPad = function(n, pad) {
			n = "" + n;
			pad = "" + (pad == null ? "0" : pad);
			return n.length == 1 ? pad + n : n;
		};

		var r = [];
		var escape = false;
		var hours = d.getHours();
		var isAM = hours < 12;

		if (monthNames == null) {
			monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
		}

		if (dayNames == null) {
			dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
		}

		var hours12;

		if (hours > 12) {
			hours12 = hours - 12;
		} else if (hours == 0) {
			hours12 = 12;
		} else {
			hours12 = hours;
		}

		for (var i = 0; i < fmt.length; ++i) {

			var c = fmt.charAt(i);

			if (escape) {
				switch (c) {
					case 'a': c = "" + dayNames[d.getDay()]; break;
					case 'b': c = "" + monthNames[d.getMonth()]; break;
					case 'd': c = leftPad(d.getDate()); break;
					case 'e': c = leftPad(d.getDate(), " "); break;
					case 'h':	// For back-compat with 0.7; remove in 1.0
					case 'H': c = leftPad(hours); break;
					case 'I': c = leftPad(hours12); break;
					case 'l': c = leftPad(hours12, " "); break;
					case 'm': c = leftPad(d.getMonth() + 1); break;
					case 'M': c = leftPad(d.getMinutes()); break;
					// quarters not in Open Group's strftime specification
					case 'q':
						c = "" + (Math.floor(d.getMonth() / 3) + 1); break;
					case 'S': c = leftPad(d.getSeconds()); break;
					case 'y': c = leftPad(d.getFullYear() % 100); break;
					case 'Y': c = "" + d.getFullYear(); break;
					case 'p': c = (isAM) ? ("" + "am") : ("" + "pm"); break;
					case 'P': c = (isAM) ? ("" + "AM") : ("" + "PM"); break;
					case 'w': c = "" + d.getDay(); break;
				}
				r.push(c);
				escape = false;
			} else {
				if (c == "%") {
					escape = true;
				} else {
					r.push(c);
				}
			}
		}

		return r.join("");
	},

	// To have a consistent view of time-based data independent of which time
	// zone the client happens to be in we need a date-like object independent
	// of time zones.  This is done through a wrapper that only calls the UTC
	// versions of the accessor methods.

	makeUtcWrapper: function(d) {
		function addProxyMethod(sourceObj, sourceMethod, targetObj, targetMethod) {
			sourceObj[sourceMethod] = function() {
				return targetObj[targetMethod].apply(targetObj, arguments);
			};
		}

		var utc = {
			date: d
		};

		// support strftime, if found
		if (d.strftime !== undefined) {
			addProxyMethod(utc, "strftime", d, "strftime");
		}

		addProxyMethod(utc, "getTime", d, "getTime");
		addProxyMethod(utc, "setTime", d, "setTime");

		var props = ["Date", "Day", "FullYear", "Hours", "Milliseconds", "Minutes", "Month", "Seconds"];

		for (var p = 0; p < props.length; p++) {
			addProxyMethod(utc, "get" + props[p], d, "getUTC" + props[p]);
			addProxyMethod(utc, "set" + props[p], d, "setUTC" + props[p]);
		}

		return utc;
	},

	dateGenerator: function(ts, opts) {
		if (opts.timezone == "browser") {
			return new Date(ts);
		} else if (!opts.timezone || opts.timezone == "utc") {
			return makeUtcWrapper(new Date(ts));
		} else if (typeof timezoneJS != "undefined" && typeof timezoneJS.Date != "undefined") {
			var d = new timezoneJS.Date();
			// timezone-js is fickle, so be sure to set the time zone before
			// setting the time.
			d.setTimezone(opts.timezone);
			d.setTime(ts);
			return d;
		} else {
			return makeUtcWrapper(new Date(ts));
		}
	},
};

/**
 * Executed on document loaded complete
 * this is where it all starts...
 */
$(document).ready(function() {
	window.onerror = function(errorMsg, url, lineNumber) {
		vz.wui.dialogs.error('Javascript Runtime Error', errorMsg);
	};

	NProgress.configure({ showSpinner: false });

	// add timezone-js support
	if (typeof timezoneJS !== "undefined" && typeof timezoneJS.Date !== "undefined") {
		timezoneJS.timezone.zoneFileBasePath = "tz";
		timezoneJS.timezone.defaultZoneFile = [];
		timezoneJS.timezone.init({ async: false });
	}

	// middleware(s)
	vz.options.middleware.forEach(function(middleware) {
		vz.middleware.push(new Middleware(middleware));
	});

	// TODO make language/translation dependent (vz.options.language)
	vz.options.plot.xaxis.monthNames = vz.options.monthNames;
	vz.options.plot.xaxis.dayNames = vz.options.dayNames;

	// clear cookies and localStorage cache
	var params = $.getUrlParams();
	if (params.hasOwnProperty('reset') && params.reset) {
		$.setCookie('vz_entities', null);
	}

	// start loading cookies/url params
	vz.entities.loadCookie(); // load uuids from cookie
	vz.options.loadCookies(); // load options from cookie

	// set x axis limits _after_ loading options cookie
	vz.options.plot.xaxis.max = new Date().getTime();
	vz.options.plot.xaxis.min = vz.options.plot.xaxis.max - vz.options.interval;

	// parse additional url params (new uuid etc e.g. for permalink) after loading defaults
	vz.parseUrlParams();

	// initialize user interface (may need to wait for onLoad on Safari)
	vz.wui.init();

	// chaining ajax request with jquery deferred object
	vz.capabilities.load().done(function() {
		vz.entities.loadDetails().done(function() {
			if (vz.entities.length === 0) {
				vz.wui.dialogs.init();
			}

			// create table and apply initial state
			vz.entities.showTable();
			vz.entities.inheritVisibility();

			// set global parameters for display mode, then load data accordingly
			vz.wui.changeDisplayMode(vz.options.mode || "current");
			vz.entities.loadData().done(function() {
				// vz.wui.resizePlot();
				vz.wui.drawPlot();
				vz.entities.loadTotalConsumption();
			});

			// create WAMP session and load public entities for each middleware
			vz.middleware.forEach(function(middleware) {
				middleware.loadEntities();

				var parser = document.createElement('a');
				parser.href = middleware.url;
				var host = parser.hostname || location.host; // location object for IE
				var protocol = (parser.protocol || location.protocol).toLowerCase().indexOf("https") === 0 ? "wss" : "ws";
				var uri = protocol + "://" + host;

				// try uri if nothing configured - requires Apache ProxyPass, see
				// https://github.com/volkszaehler/volkszaehler.org/issues/382
				if (isNaN(parseFloat(middleware.live))) {
					if (parser.port) {
						uri += ":" + parser.port;
					}
					// if Apache ProxyPass is used, connect with http(s) but always forward to unencrypted port
					uri += "/ws"; // parser.pathname.replace(/(\.\.\/)?middleware.php$/, "ws")
				}
				else {
				 	// use dedicated port
				 	uri += ":" + middleware.live;
				}

				// connect and store session
				new ab.connect(uri, function(session) {
					middleware.session = session;

					// subscribe entities for middleware
					vz.entities.each(function(entity) {
						if (entity.active && entity.middleware.indexOf(middleware.url) >= 0) {
							entity.subscribe(session);
						}
					}, true); // recursive
				}, function(code, reason) {
					delete middleware.session;
				});
			});
		});
	});
});
