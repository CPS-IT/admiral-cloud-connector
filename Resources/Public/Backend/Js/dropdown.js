'use strict';

import Notification from '@typo3/backend/notification.js';
import Viewport from '@typo3/backend/viewport.js';

class Dropdown {
  constructor() {
    Viewport.Topbar.Toolbar.registerEvent(this.initializeEvents);
  }

  initializeEvents() {
    document.querySelector('#js-admiral-cloud-toolbar-dropdown-update-changed-metadata').onclick = Dropdown.updateChangedMetadata;
    document.querySelector('#js-admiral-cloud-toolbar-dropdown-close-connection').onclick = Dropdown.closeAdmiralCloudConnection;
  }

  static closeAdmiralCloudConnection() {
    const modalParent = top.document.getElementById('acModalParent');

    if (modalParent) {
      modalParent.remove();
    }

    Notification.success('', TYPO3.lang.acSuccessMessage, 5);

    return false;
  }

  static async updateChangedMetadata() {
    Notification.info('', TYPO3.lang.acUpdateChangedMetadataInfoMessage, 5);

    const url = TYPO3.settings.ajaxUrls['admiral_cloud_toolbar_update_changed_metadata'];

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
      });

      await response.json();

      Notification.success('', TYPO3.lang.acUpdateChangedMetadataSuccessMessage, 5);
    } catch (error) {
      Notification.error('', TYPO3.lang.acUpdateChangedMetadataErrorMessage, 5);
    }

    return false;
  }
}

export default new Dropdown();
