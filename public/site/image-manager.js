// Use YUI3 things
YUI().use('node', 'event-mouseenter', 'app', 'app-transitions', 'transition', 'anim', 'view', 'handlebars', 'model', 'model-list', 'io', 'json-parse', 'uploader', 'gc-widgets', function(Y) {

	// Retrieve our config parameters
	var config = Y.JSON.parse(Y.one('#config').get('value'));

	// Make sure this exists, then create some base objects we need
	Y.GcImageManager = Y.GcImageManager || {};

	// Model for all models to extend
	Y.GcImageManager.BaseModel = Y.Base.create('imageManagerBaseModel', Y.Model, [], {});

	// Model list for all model lists to extend
	Y.GcImageManager.BaseModelList = Y.Base.create('imageManagerBaseModelList', Y.ModelList, [], {});

	// View for all views to extend
	Y.GcImageManager.BaseView = new Y.Base.create('baseView', Y.View, [], {

		// Renders the view and passes along some data
		renderWithData: function(results) {
			this.get('container').setHTML(Y.Handlebars.render(Y.one(this.get('containerTemplate')).getHTML(), results));
		},

		// Renders the view
		render: function() {
			this.get('container').setHTML(Y.Handlebars.render(Y.one(this.get('containerTemplate')).getHTML()));
		}
	});

	// Represents a directory or image
	Y.GcImageManager.FileModel = Y.Base.create('imageManagerImageModel', Y.GcImageManager.BaseModel, [], {

		// Inserts the image (only valid when image manager is used for TinyMCE or form context)
		insertImage: function() {

			// Generate the image URL
			var imgUrl = config.outputImageLocation + this.get('fullPath');

			// Check whether or not we are using TinyMCE
			if(config.mce) {
				// Get MCE dialog params and set the image url in the parent dialog, then close.
				var params = top.tinymce.activeEditor.windowManager.getParams();
				var inputElement = window.parent.document.getElementById(params.input);

				inputElement.value = imgUrl;
				top.tinymce.activeEditor.windowManager.close();
			}
			else if(window.opener) {
				window.opener.document.getElementById(config.eleId).value = imgUrl;
				window.close();
			}
		},

		// Deletes the image
		deleteImage: function() {
			Y.io('/index/delete', {
				method: 'POST',
				data: {
					directory: this.get('fullPath')
				},
				on: {
					success: function(id, o) {
						// Attempt to parse out server response
						try {
							var results = Y.JSON.parse(o.responseText)
						}
						catch(e) {
							callback('Unable to parse response from server');
							return;
						}

						// Handle different result types
						switch(results.status) {
							case 'success':
								App.set('directory', App.get('directory'));
							break;
							default:
								admin.dialog('Server Error', 'An unknown error has occurred while deleting.');
							break;
						}
					}
				}
			});
		}
	}, {
		ATTRS: {
			path: {value: null},
			fullPath: {
				valueFn: function() {
					return App.get('directory') == '/' ? this.get('path') : '/' + App.get('directory') + this.get('path');
				}
			},
			isDirectory: {value: false},
			uid: {
				getter: function() {
					return this.get('clientId');
				}
			},
			filename: {
				getter: function() {
					return this.get('path').split("/").pop();
				}
			}
		}
	});

	// Represents a list of images
	Y.GcImageManager.FileModelList = Y.Base.create('imageManagerImageModelList', Y.GcImageManager.BaseModelList, [], {
		model: Y.GcImageManager.FileModel,
		sync: function(action, options, callback) {

			// Handle different actions
			switch(action) {

				// Use to load data from server
				case 'read':

					// AJAX call to return directory listing
					Y.io('/index/list', {
						method: 'POST',
						data: {
							directory: options.directory || '/'
						},
						on: {
							success: function(id, o) {

								// Attempt to parse out server response
								try {
									var results = Y.JSON.parse(o.responseText)
								}
								catch(e) {
									callback('Unable to parse response from server');
									return;
								}

								// Handle different result types
								switch(results.status) {
									case 'success':
										callback(null, results.results);
									break;
									default:
										callback('Unknown response status received from server');
									break;
								}
							}
						}
					});
				break;
				default:
					callback('Unsupported sync action: ' + action);
				break;
			}
		}
	});

	// Thumbnail view
	Y.GcImageManager.ThumbnailView = Y.Base.create('imageManagerThumbnailView', Y.GcImageManager.BaseView, [], {
		initializer: function() {

			// The container we will use for this view
			this.set('containerTemplate', '#thumbnail-view');

			// Re-render when model data changes
			App.get('fileModelList').after(['add', 'remove', 'reset', '*:change'], function(e) {
				this.renderWithData({
					imageLocation: config.imageLocation,
					files: e.currentTarget.toJSON(),
					insert: (config.mce || window.opener) ? true : false
				});
			}, this);

			// Load model data
			App.set('directory', '/');
		},
		render: function() {
			this.renderWithData({
				imageLocation: config.imageLocation,
				files: App.get('fileModelList').toJSON()
			});
		},
		events: {

			// Photo container events
			'#photos .image': {
				dblclick: function(e) {

					// Get the image model and handle directories and files differently
					var model = App.get('fileModelList').getByClientId(e.currentTarget.getData('uid'));
					if(model.get('isDirectory')) {
						App.set('directory', App.get('directory') == '/' ? model.get('filename') : App.get('directory') + '/' + model.get('filename'));
						return;
					}

					// Insert image
					model.insertImage();
				},
				mouseenter: function(e) {
					var ele = e.currentTarget.one('.more-info');
					new Y.Anim({
						node: ele,
						easing: 'bounceOut',
						duration: 0.33,
						to: {
							height: 50
						}
					}).run();
				},
				mouseleave: function(e) {				
					var ele = e.currentTarget.one('.more-info');
					new Y.Anim({
						node: ele,
						easing: 'bounceIn',
						duration: 0.33,
						to: {
							height: 0
						}
					}).run();
				}
			}
		}
	});

	// Our app object
	Y.GcImageManager.App = Y.Base.create('imageManagerApp', Y.App, [], {
		views: {
			thumbnailView: {
				type: Y.GcImageManager.ThumbnailView,
				preserve: true
			}
		}
	}, {
		ATTRS: {
			directory: {
				getter: function(val) {
					return val ? val : '/';
				},
				setter: function(value) {

					// Make sure we have something
					if(!value) {
						return '/';
					}

					// Re-load data
					App.get('fileModelList').load({
						directory: value
					});

					return value;
				}
			},
			parentDirectory: {
				getter: function() {

					// We are at the top
					if(this.get('directory').indexOf('/') === -1) {
						return '/';
					}

					var dir = this.get('directory').replace(/\/[^/]*$/i,'');

					return dir ? dir : '/';
				}
			},
			fileModelList: {
				valueFn: function() {
					return new Y.GcImageManager.FileModelList();
				}
			},
			routes: {
				value: [
					{
						path: '/thumbnail-view', callbacks: function() {
							this.showView('thumbnailView');
						}
					}
				]
			}
		}
	});

	// Instantiate our app
	App = new Y.GcImageManager.App({
		viewContainer: '#app',
		serverRouting: false,
		transitions: true
	});

	// Render the app
	App.render();
	App.navigate('/#/thumbnail-view');

	// Do this for the freeze bar
	Y.all('.freeze').on('mouseenter', function(e) {
		e.currentTarget.transition({
			duration: 0.33,
			backgroundColor: 'rgba(255, 255, 255, 1)'
		});
	});

	Y.all('.freeze').on('mouseleave', function(e) {
		e.currentTarget.transition({
			duration: 0.33,
			backgroundColor: 'rgba(255, 255, 255, 0.8)'
		});
	});

	// Parent folder button
	Y.one('#parent-folder').on('click', function(e) {
		App.set('directory', App.get('parentDirectory'));
	});

	// Create folder button
	Y.one('#create-folder').on('click', function(e) {

		// Template
		var template = "\n\
			<div class='modal' id='create-folder-modal'>\n\
				<div class='modal-header'>\n\
					<a class='close' data-dismiss='modal'>x</a>\n\
					<h3>Create Folder</h3>\n\
				</div>\n\
				<div class='modal-body'>\n\
					<label for='new-folder-name'>New Folder Name<label>\n\
					<input type='text' id='new-folder-name' class='input-xlarge'>\n\
				</div>\n\
				<div class='modal-footer'>\n\
				<button type='button' class='btn modal-cancel'>Cancel</a>\n\
				<button type='button' class='btn btn-success create-folder-confirm'>Continue</a>\n\
				</div>\n\
			</div>\n\
		";

		// Compile the template in to a node with our data we modelled above
		var mContainer = Y.Node.create(Y.Handlebars.render(template));

		// Append container to body and setup modal dialog
		Y.one('body').appendChild(mContainer);
		$('#' + mContainer.getAttribute('id')).modal({
			show : true,
			backdrop : true
		});
	});

	// Event listener for create folder buttons
	Y.one('body').delegate('click', function() {

		// New directory (folder) name
		var newName = Y.one('#new-folder-name').get('value');

		// Get rid of the modal
		$('.modal').modal('hide');
		Y.all('.modal').remove();

		Y.io('/index/create-directory', {
			method: 'POST',
			data: {
				directory: App.get('directory'),
				name: newName
			},
			on: {
				success: function(id, o) {

					// Attempt to parse out server response
					try {
						var results = Y.JSON.parse(o.responseText)
					}
					catch(e) {
						admin.dialog('Error', 'Unable to parse response from server');
						return;
					}

					// Handle different result types
					switch(results.status) {
						case 'success':
							// Do nothing
						break;
						default:
							admin.dialog('Error', 'Unknown response status received from server');
						break;
					}

					// Reload the current directory listing
					App.set('directory', App.get('directory'));
				}
			}
		});
	}, '.create-folder-confirm');

	// Insert button
	Y.one('body').delegate('click', function(e) {
		var model = App.get('fileModelList').getByClientId(e.currentTarget.getData('uid'));
		model.insertImage();
	}, '.insert-image');

	// Delete button
	Y.one('body').delegate('click', function(e) {
		var model = App.get('fileModelList').getByClientId(e.currentTarget.getData('uid'));
		model.deleteImage();
	}, '.delete-image');

	// Open folder button
	Y.one('body').delegate('click', function(e) {
		var model = App.get('fileModelList').getByClientId(e.currentTarget.getData('uid'));
		App.set('directory', App.get('directory') == '/' ? model.get('filename') : App.get('directory') + '/' + model.get('filename'));
	}, '.open-folder');

	// Generic modal cancel button
	Y.one('body').delegate('click', function(e) {
		$('.modal').modal('hide');
		Y.all('.modal').remove();
	}, '.modal-cancel');

	// Upload button
	Y.one('#upload').on('click', function(e) {

		// Template
		var template = "\n\
			<div class='modal' id='upload-modal'>\n\
				<div class='modal-header'>\n\
					<a class='close' data-dismiss='modal'>x</a>\n\
					<h3>Upload</h3>\n\
				</div>\n\
				<div class='modal-body'>\n\
					<div id='uploader'></div>\n\
					<div style='margin-top: 10px;'>\n\
						<b>Selected Files</b>\n\
						<hr style='margin: 3px;'>\n\
					</div>\n\
					<div id='upload-list' style='height: 275px; overflow-y: scroll;'></div>\n\
				</div>\n\
				<div class='modal-footer'>\n\
				<button type='button' class='btn modal-cancel'>Cancel</a>\n\
				<button type='button' class='btn btn-success upload-confirm'>Upload</a>\n\
				</div>\n\
			</div>\n\
		";

		// Compile the template in to a node with our data we modelled above
		var mContainer = Y.Node.create(Y.Handlebars.render(template));

		// Append container to body and setup modal dialog
		Y.one('body').appendChild(mContainer);
		$('#' + mContainer.getAttribute('id')).modal({
			show : true,
			backdrop : true
		});

		// YUI Uploader
		Y.Uploader.SELECT_FILES_BUTTON = '<button type="button" class="btn btn-info" role="button" aria-label="{selectButtonLabel}" tabindex="{tabIndex}" style="width: auto !important; height: auto !important;"><i class="icon-th-list icon-white"></i>{selectButtonLabel}</button>';
		var uploader = new Y.Uploader({
			multipleFiles: true,
			fileFieldName: 'Filedata[]'
		});

		// When files are selected
		uploader.on('fileselect', function(e) {

			// Get a list of the files
			var fileList = [];
			Y.Array.each(uploader.get('fileList'), function(o) {
				fileList.push(o.get('name'));
			});

			// Template for file uploads
			var template = "\n\
				<ul>\n\
					{{#each files}}\n\
					<li>{{.}}</li>\n\
					{{/each}}\n\
				</ul>\n\
			";

			// Render template
			Y.one('#upload-list').setHTML(Y.Node.create(Y.Handlebars.render(template, {
				files: fileList
			})));
		});

		// When uploading starts
		uploader.on('uploadstart', function(e) {
			Y.Gc.Widgets.LoadingBar.show();
		});

		// When uploading completes
		uploader.on('alluploadscomplete', function(e) {
			Y.Gc.Widgets.LoadingBar.hide();
			$('.modal').modal('hide');
			Y.all('.modal').remove();
			App.set('directory', App.get('directory'));
		});

		// Render uploader
		uploader.render('#uploader');

		// Upload confirm button
		Y.one('.upload-confirm').on('click', function(e) {
			uploader.uploadAll('/index/upload', {
				directory: App.get('directory')
			});
		});
	});
});