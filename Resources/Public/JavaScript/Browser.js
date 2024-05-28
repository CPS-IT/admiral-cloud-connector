'use strict';

import $ from 'jquery';
import NProgress from 'nprogress';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import LinkBrowser from "@typo3/backend/link-browser.js";
import {MessageUtility} from '@typo3/backend/utility/message-utility.js';
import {Acc} from "@cpsit/admiral-cloud-connector-backend/acc.js";

const init = function() {

    /**
     * @type {{currentLink: string, identifier: string, linkRecord: function, linkCurrent: function}}
     */
    var RecordLinkHandler = {
        currentLink: '',
        identifier: '',

        /**
         * @param {Event} event
         */
        linkRecord: function(event) {
            event.preventDefault();

            var data = $(this).parents('span').data();
            LinkBrowser.finalizeFunction(RecordLinkHandler.identifier + data.uid);
        },

        /**
         * @param {Event} event
         */
        linkCurrent: function(event) {
            event.preventDefault();

            LinkBrowser.finalizeFunction(RecordLinkHandler.currentLink);
        }
    };

    /**
     * The main CompactView object for AdmiralCloud
     *
     * @type {{compactViewUrl: string, inlineButton: string, title: string}}
     * @exports TYPO3/CMS/AdmiralCloud/CompactView
     */
    var Browser = {
        overviewButton: '.t3js-admiral_cloud-browser-btn.overview',
        uploadButton: '.t3js-admiral_cloud-browser-btn.upload',
        cropButton: '.t3js-admiral_cloud-browser-btn.crop',
        rteLinkButton: '.t3js-admiral_cloud-browser-btn.rte-link',
        browserUrl: '',
        title: 'AdmiralCloud',
        currentLink: '',
        acc: false,
        /**
         * @param {Event} event
         */
        linkCurrent: function(event) {
            event.preventDefault();

            LinkBrowser.finalizeFunction(RecordLinkHandler.currentLink);
        },
        finalizeFunction: function(t) {
            LinkBrowser.finalizeFunction(t);
        }
    };

    /**
     * Initialize all variables and listeners for CompactView
     *
     * @private
     */
    Browser.initialize = function () {
        //$('#iframeContainer').append($('#elAdmiralCloud'))

        // start acc class once
        Browser.acc = new Acc();

        // Add all listeners based on inline button
        $(document).on('click', Browser.overviewButton, function () {
            Browser.browserUrl = $(this).data('admiral_cloudBrowserUrl');
            Browser.open();
        });
        $(document).on('click', Browser.uploadButton, function () {
            Browser.browserUrl = $(this).data('admiral_cloudBrowserUrl');
            Browser.open();
        });
        $(document).on("click", Browser.cropButton, function () {
            Browser.browserUrl = $(this).data('admiral_cloudBrowserUrl');
            Browser.open();
        });
        $(document).on("click", Browser.rteLinkButton, function () {
            // Store if rte link should set to be downloaded
            window.rteLinkDownload = !document.getElementById('rteLinkDownload').checked;
            Browser.browserUrl = $(this).data('admiral_cloudBrowserUrl');
            Browser.open();
        });

        $(top.document).on('AdmiralCloudBrowserAddMedia', function (event) {

            var target = event.detail.target;
            var media = event.detail.media;
            var modus = event.detail.modus;

            if (modus === 'rte-link') {
                if (typeof inline !== 'undefined') {
                    if (LinkBrowser.thisScriptUrl !== undefined) {
                        Browser.getMediaPublicUrl(media);
                    }
                } else {
                    if (LinkBrowser.parameters !== undefined && !$.isEmptyObject(LinkBrowser.parameters)) {
                        Browser.getMediaPublicUrl(media);
                    }
                }
            }

            if (target && media && !modus) {
                Browser.addMedia(target, media);
            }

            if (target && media && modus === 'crop') {
                Browser.cropMedia(target, media);
            }
        });
        var body = $('body');
        Browser.currentLink = body.data('currentLink');
        $('input.t3js-linkCurrent').on('click', Browser.linkCurrent);
    };

    /**
     * Open Compact View through CompactViewController
     *
     * @private
     */
    Browser.open = function () {
        Modal.advanced({
            type: Modal.types.ajax,
            title: Browser.title,
            content: Browser.browserUrl,
            size: Modal.sizes.full,
            callback: Browser.loadAfter
        });
        $(parent.document).on("click", '.acModalParent', function () {
            Modal.dismiss();
        });
    };

    Browser.loadAfter = function(currentModal) {

        // Create an observer instance
        const observer = new MutationObserver(function( mutations ) {
            mutations.forEach(function( mutation ) {
                const newNodes = mutation.addedNodes; // DOM NodeList
                if( newNodes !== null ) { // If there are new nodes added
                   [...newNodes].forEach(node => {
                        if(node.nodeType === 1) {
                            let hasAdmiralCloudBrowser = node.querySelector('#admiral_cloud-browser');
                            if(hasAdmiralCloudBrowser) {
                                Browser.acc.init(hasAdmiralCloudBrowser);
                            }
                        }
                    });
                }
            });
        });

        // Pass in the target node, as well as the observer options
        observer.observe(currentModal, {attributes: false, childList: true, subtree: true});
    }


    /**
     * Add media to irre element in frontend for possible saving
     *
     * @param {String} target
     * @param {Array} media
     *
     * @private
     */
    Browser.addMedia = function (target, media) {
        return $.ajax({
            type: 'POST',
            url: TYPO3.settings.ajaxUrls['admiral_cloud_browser_get_files'],
            dataType: 'json',
            data: {
                target: target,
                media: media
            },
            beforeSend: function () {
                Modal.dismiss();
                NProgress.start();
            },
            success: function (data) {
                if (typeof data.files === 'object' && data.files.length) {
                    if (typeof inline !== 'undefined') {
                        inline.importElementMultiple(
                            target,
                        'sys_file',
                        data.files,
                        'file'
                        );
                    } else {
                        data.files.forEach((fileId) => {
                            MessageUtility.send({
                                objectGroup: target,
                                table: 'sys_file',
                                uid: fileId,
                                actionName: 'typo3:foreignRelation:insert'
                            });
                        });
                    }
                }

                if (data.message) {
                    Notification.success('', data.message, Notification.duration);
                }
            },
            error: function (xhr, type) {
                var data = xhr.responseJSON || {};
                if (data.error) {
                    Notification.error('', data.error, Notification.duration);
                } else {
                    Notification.error('', 'Unknown ' + type + ' occured.', Notification.duration);
                }
            },
            complete: function () {
                NProgress.done();
            }
        });
    };

    /**
     * Add media to irre element in frontend for possible saving
     *
     * @param {String} target
     * @param {Array} media
     *
     * @private
     */
    Browser.cropMedia = function (target, media) {
        return $.ajax({
            type: 'POST',
            url: TYPO3.settings.ajaxUrls['admiral_cloud_browser_crop_file'],
            dataType: 'json',
            data: {
                target: target,
                media: media
            },
            beforeSend: function () {
                Modal.dismiss();
                NProgress.start();
            },
            success: function (data) {
                if (data.cropperData.length && data.target.length) {
                    //console.info(data);
                    $('#' + data.target).val(data.cropperData);
                    $('#' + data.target + '_image').attr('src',data.link);
                }

                if (data.message) {
                    Notification.success('', data.message, Notification.duration);
                }
            },
            error: function (xhr, type) {
                var data = xhr.responseJSON || {};
                if (data.error) {
                    Notification.error('', data.error, Notification.duration);
                } else {
                    Notification.error('', 'Unknown ' + type + ' occured.', Notification.duration);
                }
            },
            complete: function () {
                NProgress.done();
            }
        });
    };

    /**
     * Get public url from media
     *
     * @param {Array} media
     *
     * @private
     */
    Browser.getMediaPublicUrl = function (media) {
        //console.info(TYPO3.settings.ajaxUrls['admiral_cloud_browser_get_media_public_url']);

        return $.ajax({
            type: 'POST',
            url: TYPO3.settings.ajaxUrls['admiral_cloud_browser_get_media_public_url'],
            dataType: 'json',
            data: {
                media: media,
                rteLinkDownload: window.rteLinkDownload
            },
            beforeSend: function () {
                NProgress.start();
                Modal.dismiss();
            },
            success: function (data) {
                if (data.publicUrl) {
                    LinkBrowser.finalizeFunction(data.publicUrl);
                } else {
                    Notification.error('', 'It was not possible to get the file public url.', Notification.duration);
                }

                if (data.message) {
                    Notification.success('', data.message, Notification.duration);
                }
            },
            error: function (xhr, type) {
                var data = xhr.responseJSON || {};
                if (data.error) {
                    Notification.error('', data.error, Notification.duration);
                } else {
                    Notification.error('', 'Unknown ' + type + ' occured.', Notification.duration);
                }
            },
            complete: function () {
                NProgress.done();
            }
        });
    };

    Browser.initialize();
    return Browser;
}

export default init();