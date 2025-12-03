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

            paytrace.setKeyAjax('https://api.'+(self.#options.sandbox ? 'sandbox.' : '')+'paytrace.com/v3/e2ee/public-key.pem');
            self.#setEvents();
        }

        /**
         * Set events
         */
        #setEvents() {
            let self = this;

            self.#form.addEventListener('submit', function(e) {
                e.preventDefault();
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