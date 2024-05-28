import Icons from '@typo3/backend/icons.js';

export class Acc {

    constructor() {

        this.container;
        this.iframeURL_HOST;
        this.iframeURL;
        this.readyCallbacks = [];
        this.elAdmiralCloud;
        this.admiralCloudAction;
        this.irreObjectTarget;
        this.mediaContainerId;
        this.embedLink;
        this.ajaxUrl;
        this.currentIframeURL = '';
        this.modalParent;
        this.closeIcon;

        this.setWindowMessageHandler();
    }

    async getIcon() {
        return await Icons.getIcon('actions-close', Icons.sizes.medium, null, 'disabled').then((icon) => {
            return icon;
        });
    }

    async loadIframeWithAuthCode(device) {

        const resp = await fetch(this.ajaxUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                callbackUrl: this.iframeURL,
                device,
            }),
        });

        const data = await resp.json();
        //console.info(data);

        if (data.error !== undefined) {
            this.hideAC();

            // Remove iframe to close all connections and the next time try the authentication again
            if (this.elAdmiralCloud) {
                this.elAdmiralCloud.remove()
            }

            // Show error message
            parent.TYPO3.Notification.error('The authentication to AdmiralCloud was not possible.', data.error, 30);

            return;
        }

        this.applyAuthCode(data.code);
    }

    applyAuthCode(code) {
        const iframeURL = this.currentIframeURL;
        this.load(this.currentIframeURL + '&code=' + code);
        this.currentIframeURL = this.iframeURL;
    }

    init(element) {
        this.container = element;
        this.iframeURL = element.getAttribute('data-iframeurl');
        this.ajaxUrl = element.getAttribute('data-typo3-ajax-url');
        this.iframeURL_HOST = element.getAttribute('data-iframeHost');
        this.admiralCloudAction = element.getAttribute('data-modus');
        this.irreObjectTarget = element.getAttribute('data-irreObject');
        this.mediaContainerId = element.getAttribute('data-mediaContainerId');
        this.embedLink = element.getAttribute('data-embedLink');

        this.load();
    }

    async load(newIframeURL = false){

        this.modalParent = parent.document.querySelector('body');
        this.elAdmiralCloud = this.modalParent.querySelector('#acModalParent');

        if (newIframeURL) this.iframeURL = newIframeURL;

        if (this.elAdmiralCloud) {
            if (this.mediaContainerId && this.embedLink) {
                this.editImage();
            }
            const iframe = this.elAdmiralCloud.querySelector('iframe');

            // If the requested URL is already open, simply show it
            if (this.currentIframeURL.includes(this.iframeURL)) {
                this.elAdmiralCloud.querySelector('.acBackdrop').classList.remove('hidden');
                this.executeReadyCallbacks();
                return;
            }

            // Otherwise navigate to the requested URL
            iframe.src = this.iframeURL;
            this.currentIframeURL = this.iframeURL;
            this.elAdmiralCloud.querySelector('.acBackdrop').classList.remove('hidden');
            return;
        }

        this.closeIcon = await this.getIcon();

        // Create a new iframe and authenticate
        let el = document.createElement('div');
        el.setAttribute('class','acModalParent');
        el.setAttribute('id','acModalParent');
        el.innerHTML = `<div class="acBackdrop"><iframe src="${this.iframeURL}&auth=1"></iframe><div class="close">${this.closeIcon}</div></div>`;
        this.currentIframeURL = this.iframeURL;

        this.modalParent.appendChild(el);

        el.addEventListener('click', () => this.hideAC());
        this.elAdmiralCloud = el;
        if (this.mediaContainerId && this.embedLink) {
            this.editImage();
        }
    }

    editImage() {
        const elAdmiralCloud = this.elAdmiralCloud,
            mediaContainerId = this.mediaContainerId, 
            embedLink = this.embedLink,
            iframeURL_HOST = this.iframeURL_HOST;

        this.readyCallbacks.unshift(function() {
            elAdmiralCloud.querySelector('iframe').contentWindow.postMessage(JSON.stringify({
                command: 'CROP_IMAGE',
                mediaContainerId,
                embedLink
            }), iframeURL_HOST);
        });
    }

    executeReadyCallbacks() {
        //console.info('readyCallbacks', this.readyCallbacks);
        while (this.readyCallbacks.length > 0) {
            this.readyCallbacks.pop().call();
        }
    }

    setWindowMessageHandler() {

        const classObject = this;

        parent.window.onmessage = function (e) {
            let data = false;

            if(e.data) {
                data = JSON.parse(e.data);
            }

            // Receive Auth Device-Identifier
            if (data.command === 'AUTH') {
                const {device} = data;
                classObject.loadIframeWithAuthCode(device);
                return;
            }

            // Receive severe Auth Failure -> Reload
            if (data.command === 'AUTH_FAILURE') {
                console.info('auth_failure');
                return;
            }

            // Receive Signal to execute Ready Callbacks
            if (data.command === 'READY') {
                classObject['mediaContainerId'] = classObject.container.getAttribute('data-mediaContainerId');
                classObject['embedLink'] = classObject.container.getAttribute('data-embedLink');
                if (classObject.mediaContainerId && classObject.embedLink) {
                    classObject.editImage();
                }

                classObject.executeReadyCallbacks();

                return;
            }

            // Receive Media
            if (data.command === 'MEDIA') {
                const isInsertAllowed = true;
                //console.log('INSERT?', isInsertAllowed);

                // Dispatch internal interaction for TYPO3/CMS/AdmiralCloudConnector/Browser
                let event, parameters = {
                    detail: {
                        target: classObject.irreObjectTarget,
                        media: data,
                        modus: classObject.admiralCloudAction
                    }
                };

                if (typeof CustomEvent === 'function') {
                    event = new CustomEvent('AdmiralCloudBrowserAddMedia', parameters);
                } else {
                    // Add IE11 support
                    event = top.document.createEvent('CustomEvent');
                    event.initCustomEvent('AdmiralCloudBrowserAddMedia', true, true, parameters);
                }

                top.document.dispatchEvent(event);
                classObject.hideAC();
            }
        }
    }

    hideAC() {
        this.elAdmiralCloud.querySelector('iframe').contentWindow.postMessage(JSON.stringify({command: 'HIDE_CROPPER_MODAL'}), this.iframeURL_HOST);
        this.elAdmiralCloud.querySelector('.acBackdrop').classList.add('hidden');
    }

}