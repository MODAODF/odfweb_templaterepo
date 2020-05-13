/*
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

// HACK: this piece needs to be loaded AFTER the files app (for unit tests)
$(document).ready(function () {
	(function (OCA) {
		/**
		 * @class OCA.Files.TemplateRepoFileList
		 * @augments OCA.Files.TemplateRepoFileList
		 *
		 * @classdesc TemplateRepo file list.
		 * Displays the list of files marked as favorites
		 *
		 * @param $el container element with existing markup for the #controls
		 * and a table
		 * @param [options] map of options, see other parameters
		 */
		var TemplateRepoFileList = function ($el, options) {
			this.initialize($el, options);
		};
		TemplateRepoFileList.prototype = _.extend({}, OCA.Files.FileList.prototype,
			/** @lends OCA.Files.TemplateRepoFileList.prototype */ {
				id: 'templaterepolist',
				appName: t('files', 'TemplateRepo'),

				_clientSideSort: true,
				_allowSelection: false,

				/**
				 * @private
				 */
				initialize: function ($el, options) {
					OCA.Files.FileList.prototype.initialize.apply(this, arguments);
					if (this.initialized) {
						return;
					}
					OC.Plugins.attach('OCA.Files.TemplateRepoFileList', this);
					$.ajax({
						url: OC.generateUrl('/apps/templaterepo/folderlist'),
						type: 'GET',
						dataType: 'json'
					}).done(() => {
						console.log("ffffff");
					});
				},

				updateEmptyContent: function () {
					var dir = this.getCurrentDirectory();
					if (dir === '/') {
						// root has special permissions
						this.$el.find('#emptycontent').toggleClass('hidden', !this.isEmpty);
						this.$el.find('#filestable thead th').toggleClass('hidden', this.isEmpty);
					}
					else {
						OCA.Files.FileList.prototype.updateEmptyContent.apply(this, arguments);
					}
				},

				getDirectoryPermissions: function () {
					return OC.PERMISSION_READ | OC.PERMISSION_DELETE;
				},

				updateStorageStatistics: function () {
					// no op because it doesn't have
					// storage info like free space / used space
				},

				reload: function () {
					this.showMask();
					if (this._reloadCall) {
						this._reloadCall.abort();
					}

					// there is only root
					this._setCurrentDir('/', false);

					this._reloadCall = $.ajax({
						url: OC.generateUrl('/apps/templaterepo/folderlist'),
						type: 'GET',
						dataType: 'json'
					});
					var callBack = this.reloadCallback.bind(this);
					return this._reloadCall.then(callBack, callBack);
				},

				reloadCallback: function (result) {
					delete this._reloadCall;
					this.hideMask();

					if (result.files) {
						this.setFiles(result.files.sort(this._sortComparator));
						return true;
					}
					return false;

					files = result.files;
					result = [];
					files.forEach((data)=>{
						result.push(OC.Files.FileInfo(data));
					});
					if (result) {
						// prepend empty dir info because original handler
						result.unshift({});
					}
					status = 207; // Fake propFind success status
					return OCA.Files.FileList.prototype.reloadCallback.call(this, status, result);
				},
			});

		OCA.Files.TemplateRepoFileList = TemplateRepoFileList;
	})(OCA);
});
