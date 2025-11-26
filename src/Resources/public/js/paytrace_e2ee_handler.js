/**
 * Rhyme Contao Isotope Paytrace Bundle
 *
 * Copyright (c) 2025 Rhyme.Digital
 *
 * @license GPLv3
 */

'use strict';

window.Rhyme = window.Rhyme || {};

(function(){

    // Private
    let _registered	= {};

    class PaytraceE2EEHandler {

        // Private
        #uid;
        #form;
        #options;

        /**
         * Constructor
         * @param form
         * @param options
         */
        constructor(form, options) {
            let self = this;

            self.#form = form;
            self.#options = options;

            // Add a data attribute to the wrapper containing the UID
            // and store in registered so we can access it from the outside if needed
            self.#uid = Date.now()+Math.floor(Math.random() * 999999);
            self.#form.setAttribute('data-rhyme-paytrace-e2ee-handler-id', self.#uid);
            _registered[self.#uid] = self;

            self.#initialize();
        }

        /**
         * Init
         */
        #initialize() {
            let self = this;

            paytrace.setKeyAjax('https://api.sandbox.paytrace.com/v3/e2ee/public-key.pem');
            self.#setEvents();
        }

        /**
         * Set events
         */
        #setEvents() {
            let self = this;

            self.#form.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log(e);
                self.#handleSubmit();
                return false;
            });
        }


        #handleSubmit() {
            let self = this;

            if (!window.paytrace) {
                alert('Paytrace E2EE is not loaded');
                return;
            }

            window.paytrace.submitEncrypted(self.#form);

            // let self = this;
            //
            // let authData = {};
            // authData.clientKey = clientKey;
            // authData.apiLoginID = apiLoginId;
            //
            // let cardData = {};
            // cardData.cardNumber = _doc.getElementById("cardNumber").value;
            // cardData.month = _doc.getElementById("expMonth").value;
            // cardData.year = _doc.getElementById("expYear").value;
            // cardData.cardCode = _doc.getElementById("cardCode").value;
            // cardData.fullName = _doc.getElementById("fullName").value;
            // cardData.zip = _doc.getElementById("zip").value;
            //
            // let secureData = {};
            // secureData.authData = authData;
            // secureData.cardData = cardData;
            //
            // if(_win.Accept) {
            //
            //     //Set form class to processing
            //     form.setAttribute('class', form.getAttribute('class') + ' processing');
            //
            //     Accept.dispatchData(secureData, handleResponse);
            //
            //     //Handle response and clear all sensitive form fields
            //     function handleResponse (responseData) {
            //         //Remove form processing class
            //         form.setAttribute('class', form.getAttribute('class').replace(/ ?processing/, ''));
            //
            //         let messages = responseData.messages;
            //         if(messages.resultCode === 'Ok' && messages.message[0].code === 'I_WC_01') {
            //
            //             let opaqueData = responseData.opaqueData;
            //             _doc.getElementById("dataDescriptor").value = opaqueData.dataDescriptor;
            //             _doc.getElementById("dataValue").value = opaqueData.dataValue;
            //
            //             _doc.getElementById("cardNumber").value = "";
            //             _doc.getElementById("expMonth").value = "";
            //             _doc.getElementById("expYear").value = "";
            //             _doc.getElementById("cardCode").value = "";
            //
            //             //Set form class to success and submit
            //             form.setAttribute('class', form.getAttribute('class') + ' success');
            //             form.submit();
            //
            //         } else {
            //             let messagesDiv = form.getElementsByClassName('messages');
            //             if(messagesDiv.length > 0) {
            //                 messagesDiv[0].innerHTML = '<p class="error">'+messages.message[0].text+'</p>';
            //             }
            //
            //         }
            //     }
            // }
            //
            // return false; //We don't want the form to post any sensitive data back
        }
    }

    // Public
    Rhyme.PaytraceE2EEHandler = {

        /**
         * Create a new PaytraceE2EEHandler instance
         * @param form
         * @param options
         * @returns {PaytraceE2EEHandler}
         */
        create: function (form, options) {
            return new PaytraceE2EEHandler(form, options);
        },

        /**
         * Return a class instance using its ID
         * @param uid
         * @returns {PaytraceE2EEHandler|null}
         */
        getInstanceById: function (uid) {
            return _registered && _registered.hasOwnProperty(uid) ? _registered[uid] : null;
        }
    };

})();