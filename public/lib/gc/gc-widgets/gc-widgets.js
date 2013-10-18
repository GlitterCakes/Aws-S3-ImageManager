// Widgets module
YUI.add('gc-widgets', function(Y) {

	// Make sure namespace exists
	Y.Gc = Y.Gc || {};
	
	// Define Gc Widgets
	Y.Gc.Widgets = {
		
		/**
		 * Loading Bar
		 * 
		 * Container for loading bar functionality
		 */
		LoadingBar: {

			// Awesome loader found here http://preloaders.net/

			// Our bar object definition
			Bar: Y.Base.create('panel', Y.Panel, [], {}, {
				ATTRS: {
					headerContent: {value: null},		
					bodyContent: {value: '<div style="text-align: center;"><img src="/images/loading-bar.gif" /><br>Please Wait...</div>'},
					width: {value: 300},
					centered: {value: true},
					modal: {value: true},
					zIndex: {value: 10000},
					buttons: {value: []},
					hideOn: {value: []},
					render: {value: true}
				}
			}),
		
			// Internal instance of loading bar
			instance: null,

			// The number of times the widget has been requested to display
			iCount: 0,

			// Shows loading bar
			show: function() {
				Y.Gc.Widgets.LoadingBar.iCount++;
				if(Y.Gc.Widgets.LoadingBar.iCount === 1) {
					Y.Gc.Widgets.LoadingBar.instance = new Y.Gc.Widgets.LoadingBar.Bar();
				}
			},

			// Hides loading bar
			hide: function() {
				Y.Gc.Widgets.LoadingBar.iCount--;
				if(Y.Gc.Widgets.LoadingBar.iCount === 0) {
					Y.Gc.Widgets.LoadingBar.instance.destroy();
				}
			}
		},
		
		/**
		 * Get Modal Template
		 * 
		 * Returns the handlebars template used for generating Bootstrap style modals
		 * @returns string Handlebars template for bootstrap modal
		 */
		GetModalTemplate: function() {
			return "\n\
				<div class='modal fade' id='gc-modal'>\n\
					<div class='modal-dialog'>\n\
						<div class='modal-content'>\n\
							<div class='modal-header'>\n\
								<button type='button' class='close' data-dismiss='modal' aria-hidden='true'>&times;</button>\n\
								<h4 class='modal-title'>{{title}}</h4>\n\
							</div>\n\
							<div class='modal-body'>\n\
								<p>{{{content}}}</p>\n\
							</div>\n\
							<div class='modal-footer'>\n\
								{{#each buttons}}\n\
								<button type='button' class='{{./class}}'{{#if ./close}} data-dismiss='modal'{{/if}}>{{./label}}</button>\n\
								{{/each}}\n\
							</div>\n\
						</div>\n\
					</div>\n\
				</div>\n\
			";
		},
		
		/**
		 * Notification
		 * 
		 * Generates a twitter bootstrap style dialog box
		 * @param object config Configuration info for the dialog
		 * @returns void
		 */
		Dialog: function(config) {
			
			// Compile the template in to a node with our data we modelled above
			var mContainer = Y.Node.create(Y.Handlebars.render(Y.Gc.Widgets.GetModalTemplate(), {
				title: config.title ? config.title : '',
				content: config.content ? '<p>' + config.content + '</p>' : '',
				buttons: config.buttons ? config.buttons : [{label: 'Continue', class: 'btn btn-success', close: true}]
			}));

			// Append container to body
			Y.one('body').appendChild(mContainer);

			// Setup modal dialog
			$J('#' + mContainer.getAttribute('id')).modal({
				show : true,
				backdrop : true
			});
		},
		
		/**
		 * Server Error
		 * 
		 * Called when a server error has occurred via an AJAX call
		 * @param object config Configuration info for the dialog
		 * @returns void
		 */
		ServerError: function(config) {
			
			// Construct content template for modal
			var template = "\n\
				<p>{{message}}</p>\n\
				{{#if exception}}\n\
				<hr>\n\
				<h5>Message</h4>\n\
				{{exception.message}}\n\
				<h5>Stack Trace</h4>\n\
				<pre>{{exception.stackTrace}}</pre>\n\
				<h5>Request Parameters</h4>\n\
				<pre>{{{exception.requestParams}}}</pre>\n\
				{{/if}}\n\
			";
			
			// Compile the template in to a node with our data we modelled above
			var mContainer = Y.Node.create(Y.Handlebars.render(Y.Gc.Widgets.GetModalTemplate(), {
				title: 'Epic Error',
				buttons: config.buttons ? config.buttons : [{label: 'Continue', class: 'btn btn-success', close: true}],
				content: Y.Handlebars.render(template, {
					message: config.message,
					exception: config.exception ? config.exception : false
				})
			}));
			
			// Append container to body
			Y.one('body').appendChild(mContainer);

			// Setup modal dialog
			$J('#' + mContainer.getAttribute('id')).modal({
				show : true,
				backdrop : true
			});
		}
	};
}, null, {
	requires: ['node', 'handlebars', 'panel']
});