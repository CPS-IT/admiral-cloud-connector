'use strict';

import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import LinkBrowser from '@typo3/backend/link-browser.js';
import {MessageUtility} from '@typo3/backend/utility/message-utility.js';
import labels from '~labels/admiral_cloud_connector.be';
import '@typo3/backend/element/progress-bar-element.js';

/**
 * The AdmiralCloud browser.
 *
 * It owns exactly one persistent AdmiralCloud iframe. That iframe is created once and never
 * recreated, so the AdmiralCloud auth session (device/code handshake) survives across opens.
 * While a picker is open, the iframe lives inside a native TYPO3 modal's body; while closed, it's
 * parked in a hidden host element in the top document.
 */
class Browser {
  constructor() {
    this.progressBarElement = null;

    /** @type {{ajaxUrl: string, iframeUrl: string, irreObject: string|null, modus: string|undefined, mediaContainerId: string|undefined, embedLink: string|undefined}} */
    this.config = {};

    // The one persistent iframe (and its host element) and its navigation/ready-callback state.
    this.host = null;
    this.iframe = null;
    this.readyCallbacks = [];

    this.initialize();
  }

  /**
   * Initialize all variables and listeners for the AdmiralCloud browser.
   *
   * @private
   */
  initialize() {
    document.addEventListener('click', (event) => {
      const trigger = event.target.closest('.t3js-admiral_cloud-browser-btn');

      if (trigger) {
        void this.open(trigger.dataset.admiral_cloudBrowserUrl);
      }
    });

    top.document.addEventListener('AdmiralCloudBrowserAddMedia', (event) => {
      const target = event.detail.target;
      const media = event.detail.media;
      const modus = event.detail.modus;

      if (modus === 'rte-link' && LinkBrowser.parameters !== undefined && Object.keys(LinkBrowser.parameters).length > 0) {
        void this.getMediaPublicUrl(media);
      }

      if (target && media && !modus) {
        void this.addMedia(target, media);
      }

      if (target && media && modus === 'crop') {
        void this.cropMedia(target, media);
      }
    });

    this.setWindowMessageHandler();
  }

  /**
   * Fetch this action's config (iframe URL, auth URL, modus, ...) and show it in a modal.
   *
   * @private
   */
  async open(browserUrl) {
    try {
      const response = await new AjaxRequest(browserUrl).get();
      this.config = await response.resolve();
      this.showModal();
    } catch {
      Notification.error('', labels.get('browser.error-open'));
    }
  }

  /**
   * Open a native TYPO3 modal and move the persistent iframe into it once it's rendered.
   *
   * @private
   */
  showModal() {
    const modal = Modal.advanced({
      type: Modal.types.default,
      title: 'AdmiralCloud',
      size: Modal.sizes.full,
      content: '',
      callback: (modalElement) => this.mount(modalElement)
    });

    modal.addEventListener('typo3-modal-hide', () => this.unmount(), {once: true});
  }

  /**
   * Move the persistent iframe host into the modal body and (re)point the iframe at the
   * requested AdmiralCloud URL, reusing the existing session whenever possible.
   *
   * @param {HTMLElement} modalElement
   * @private
   */
  mount(modalElement) {
    const host = this.ensureHost(modalElement.ownerDocument);
    const modalBody = modalElement.querySelector('.t3js-modal-body');
    modalBody.replaceChildren(host);
    modalBody.style.padding = 0;
    host.classList.add('is-active');

    this.load();
  }

  /**
   * Move the persistent iframe host back to the top document before TYPO3 removes the modal.
   *
   * @private
   */
  unmount() {
    if (!this.host) {
      return;
    }

    if (this.iframe) {
      this.postToIframe({command: 'HIDE_CROPPER_MODAL'});

      // Avoid multiple re-authentication attempts when moving the iframe from modal to the top document
      this.iframe.src = '#';
    }

    this.host.classList.remove('is-active');
    this.host.ownerDocument.body.appendChild(this.host);
  }

  /**
   * @param {Document} doc
   * @return {HTMLElement}
   * @private
   */
  ensureHost(doc) {
    if (!this.host) {
      this.host = this.findHost(doc);

      if (!this.host) {
        this.host = doc.createElement('div');
        this.host.className = 'admiral-cloud-browser-host';
        doc.body.appendChild(this.host);
      }
    }

    return this.host;
  }

  /**
   * @param {Document} doc
   * @return {HTMLElement|null}
   */
  findHost(doc) {
    return doc.querySelector('.admiral-cloud-browser-host');
  }

  /**
   * Remove the persistent iframe entirely, so the next open starts a fresh auth handshake.
   *
   * @private
   */
  destroyHost() {
    (this.host ?? this.findHost(top.document))?.remove();

    this.host = null;
    this.iframe = null;
    this.readyCallbacks = [];
  }

  /**
   * Load (or reuse) the AdmiralCloud iframe for the current config.
   *
   * @private
   */
  load() {
    const iframeUrl = new URL(this.config.iframeUrl);

    if (!this.iframe) {
      this.iframe = this.host.querySelector('iframe');
    }

    if (this.config.mediaContainerId && this.config.embedLink) {
      this.queueCropCommand();
    }

    if (this.iframe) {
      // If the requested URL is already open, simply show it
      if (this.iframe.src.includes(iframeUrl.toString())) {
        this.executeReadyCallbacks();
        return;
      }

      // Otherwise navigate to the requested URL
      this.iframe.src = iframeUrl.toString();
      return;
    }

    // Create a new iframe and authenticate
    iframeUrl.searchParams.set('auth', '1');

    this.iframe = this.host.ownerDocument.createElement('iframe');
    this.host.appendChild(this.iframe);
    this.iframe.src = iframeUrl.toString();
    this.iframe.classList.add('modal-iframe');
  }

  /**
   * @param {String} device
   * @private
   */
  async loadIframeWithAuthCode(device) {
    try {
      const response = await new AjaxRequest(this.config.ajaxUrl).post(
        {
          callbackUrl: this.config.iframeUrl,
          device,
        },
        {
          headers: {
            'Content-Type': 'application/json',
          },
        },
      );
      const {code} = await response.resolve();

      this.applyAuthCode(code);
    } catch (error) {
      Modal.dismiss();
      this.destroyHost();
      Notification.error('', (await this.extractErrorMessage(error) || labels.get('browser.error-auth')), 30);
    }
  }

  /**
   * @param {String} code
   * @private
   */
  applyAuthCode(code) {
    const currentIframeURL = new URL(this.iframe.src);
    currentIframeURL.searchParams.delete('auth');
    currentIframeURL.searchParams.set('code', code);

    this.iframe.src = currentIframeURL.toString();
  }

  /**
   * @private
   */
  queueCropCommand() {
    const mediaContainerId = this.config.mediaContainerId;
    const embedLink = this.config.embedLink;

    this.readyCallbacks.unshift(() => {
      this.postToIframe({
        command: 'CROP_IMAGE',
        mediaContainerId,
        embedLink,
      });
    });
  }

  /**
   * @private
   */
  executeReadyCallbacks() {
    while (this.readyCallbacks.length > 0) {
      this.readyCallbacks.pop().call();
    }
  }

  /**
   * @param {Object} message
   * @private
   */
  postToIframe(message) {
    this.iframe.contentWindow.postMessage(JSON.stringify(message), this.resolveIframeHost());
  }

  /**
   * @return {String}
   * @private
   */
  resolveIframeHost() {
    return this.iframe.src.replace(/\/$/, '');
  }

  /**
   * @private
   */
  setWindowMessageHandler() {
    parent.window.addEventListener('message', (e) => {
      let data = false;
      const origin = new URL(e.origin);

      // Make sure only AdmiralCloud messages are handled
      if (!origin.hostname.endsWith('admiralcloud.com')) {
        return;
      }

      // Ignore messages that aren't from this instance's own iframe, since
      // multiple Browser instances (e.g. one per browsing context/frame) may
      // each be listening at the same time.
      if (!this.iframe || e.source !== this.iframe.contentWindow) {
        return;
      }

      if (e.data) {
        data = JSON.parse(e.data);
      }

      // Receive Auth Device-Identifier
      if (data.command === 'AUTH') {
        const {device} = data;
        void this.loadIframeWithAuthCode(device);
        return;
      }

      // Receive severe Auth Failure -> Reload
      if (data.command === 'AUTH_FAILURE') {
        Notification.error('', labels.get('browser.error-auth-failure'));
        return;
      }

      // Receive Signal to execute Ready Callbacks
      if (data.command === 'READY') {
        this.executeReadyCallbacks();
        return;
      }

      // Receive Media
      if (data.command === 'MEDIA') {
        const event = new CustomEvent('AdmiralCloudBrowserAddMedia', {
          detail: {
            target: this.config.irreObject,
            media: data,
            modus: this.config.modus,
          },
        });

        try {
          top.document.dispatchEvent(event);
        } finally {
          Modal.dismiss();
        }
      }
    });
  }

  /**
   * @private
   */
  showProgressBar() {
    this.progressBarElement = document.createElement('typo3-backend-progress-bar');
    document.body.appendChild(this.progressBarElement);
    this.progressBarElement.start();
  }

  /**
   * @private
   */
  hideProgressBar() {
    this.progressBarElement?.done();
  }

  /**
   * Add media to irre element in frontend for possible saving
   *
   * @param {String} target
   * @param {Array} media
   *
   * @private
   */
  async addMedia(target, media) {
    this.showProgressBar();

    try {
      const response = await new AjaxRequest(TYPO3.settings.ajaxUrls['admiral_cloud_browser_get_files']).post({
        target,
        media
      });
      const data = await response.resolve();

      if (typeof data.files === 'object' && data.files.length) {
        if (typeof inline !== 'undefined') {
          inline.importElementMultiple(target, 'sys_file', data.files, 'file');
        } else {
          data.files.forEach((fileId) => {
            MessageUtility.send({
              objectGroup: target,
              table: 'sys_file',
              uid: fileId,
              actionName: 'typo3:foreignRelation:insert',
            });
          });
        }
      }

      if (data.message) {
        Notification.success('', data.message);
      }
    } catch (error) {
      Notification.error('', (await this.extractErrorMessage(error)) || labels.get('browser.error-unknown'));
    } finally {
      this.hideProgressBar();
    }
  }

  /**
   * Add media to irre element in frontend for possible saving
   *
   * @param {String} target
   * @param {Array} media
   *
   * @private
   */
  async cropMedia(target, media) {
    this.showProgressBar();

    try {
      const response = await new AjaxRequest(TYPO3.settings.ajaxUrls['admiral_cloud_browser_crop_file']).post({
        target,
        media
      });
      const data = await response.resolve();

      if (data.cropperData.length && data.target.length) {
        const targetField = document.getElementById(data.target);
        const targetImage = document.getElementById(data.target + '_image');

        if (targetField) {
          targetField.value = data.cropperData;
        }
        if (targetImage) {
          targetImage.setAttribute('src', data.link);
        }
      }

      if (data.message) {
        Notification.success('', data.message);
      }
    } catch (error) {
      Notification.error('', (await this.extractErrorMessage(error)) || labels.get('browser.error-unknown'));
    } finally {
      this.hideProgressBar();
    }
  }

  /**
   * Get public url from media
   *
   * @param {Array} media
   *
   * @private
   */
  async getMediaPublicUrl(media) {
    this.showProgressBar();

    try {
      const response = await new AjaxRequest(TYPO3.settings.ajaxUrls['admiral_cloud_browser_get_media_public_url']).post({media});
      const data = await response.resolve();

      if (data.publicUrl) {
        LinkBrowser.finalizeFunction(data.publicUrl);
      } else {
        Notification.error('', labels.get('browser.error-get-public-url'));
      }

      if (data.message) {
        Notification.success('', data.message);
      }
    } catch (error) {
      Notification.error('', (await this.extractErrorMessage(error)) || labels.get('browser.error-unknown'));
    } finally {
      this.hideProgressBar();
    }
  }

  /**
   * Extract the server-provided error message from a rejected AjaxRequest call, if any.
   *
   * @param {*} error
   * @return {Promise<string|undefined>}
   */
  async extractErrorMessage(error) {
    if (typeof error === 'string') {
      return error;
    }

    if (typeof error?.resolve !== 'function') {
      return undefined;
    }

    try {
      const data = await error.resolve();
      return data?.error;
    } catch {
      return undefined;
    }
  }

  /**
   * Reset the AdmiralCloud connection: remove the persistent iframe so the next open runs a
   * fresh auth handshake. Used by the backend toolbar's "close AdmiralCloud connection" action.
   */
  resetConnection() {
    this.destroyHost();
  }
}

export default new Browser();
