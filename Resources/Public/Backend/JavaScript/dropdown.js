'use strict';

import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Browser from '@cpsit/admiral-cloud-connector/browser.js';
import Notification from '@typo3/backend/notification.js';
import Viewport from '@typo3/backend/viewport.js';
import labels from '~labels/admiral_cloud_connector.be';

class Dropdown {
  constructor() {
    Viewport.Topbar.Toolbar.registerEvent(this.initializeEvents);
  }

  initializeEvents() {
    document.querySelector('#js-admiral-cloud-toolbar-dropdown-update-changed-metadata').onclick = Dropdown.updateChangedMetadata;
    document.querySelector('#js-admiral-cloud-toolbar-dropdown-close-connection').onclick = Dropdown.closeAdmiralCloudConnection;
  }

  static async closeAdmiralCloudConnection() {
    Browser.resetConnection();

    Notification.success('', labels.get('toolbarItem.closeConnection.success'), 5);

    return false;
  }

  static async updateChangedMetadata() {
    Notification.info('', labels.get('toolbarItem.updateChangedMetadata.info'), 5);

    const url = TYPO3.settings.ajaxUrls['admiral_cloud_toolbar_update_changed_metadata'];

    new AjaxRequest(url)
      .post({}, {
        headers: {
          'Content-Type': 'application/json',
        },
      })
      .then(
        async function (response) {
          await response.resolve();

          Notification.success('', labels.get('toolbarItem.updateChangedMetadata.success'), 5);
        },
        function () {
          Notification.error('', labels.get('toolbarItem.updateChangedMetadata.error'), 5);
        },
      )
    ;

    return false;
  }
}

export default new Dropdown();
