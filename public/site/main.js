// YUI Config
YUI_config = {
	groups: {
		gc: {
			filter: 'raw',
			base: '/lib/gc/',
			patterns: {
				'gc-': {}
			}
		}
	}
};

// Prerequisites module
YUI.add('gc-prereqs', function(Y) {

	// Bind callbacks for AJAX errors, and loading bar
	Y.on('io:start', Y.Gc.Widgets.LoadingBar.show);
	Y.on('io:end', Y.Gc.Widgets.LoadingBar.hide);
	Y.on('io:failure', function(id, o) {
		
		// Attempt to parse out response from server
		try {
			var results = Y.JSON.parse(o.responseText);
		}
		catch(e) {
			Y.Gc.Widgets.Dialog({
				title: 'Epic Error',
				content: 'Error parsing response from server, please try again. If the problem continues contact support.'
			});
			return;
		}
		
		// Display the server error modal
		Y.Gc.Widgets.ServerError({
			message: results.message,
			exception: results.exception
		});
	});
	
}, null, {
	condition: {
		trigger: 'io-base'
	},
	requires: ['io-base', 'json-parse', 'gc-widgets']
});
