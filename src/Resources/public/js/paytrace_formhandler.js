(function(_win, _doc, jQuery, MooTools){

    _win.RhymeAcceptJsHandler = _win.RhymeAcceptJsHandler || {

        handleSubmit: function (clientKey, apiLoginId, form) {
            let authData = {};
            authData.clientKey = clientKey;
            authData.apiLoginID = apiLoginId;

            let cardData = {};
            cardData.cardNumber = _doc.getElementById("cardNumber").value;
            cardData.month = _doc.getElementById("expMonth").value;
            cardData.year = _doc.getElementById("expYear").value;
            cardData.cardCode = _doc.getElementById("cardCode").value;
            cardData.fullName = _doc.getElementById("fullName").value;
            cardData.zip = _doc.getElementById("zip").value;

            let secureData = {};
            secureData.authData = authData;
            secureData.cardData = cardData;

            if(_win.Accept) {

                //Set form class to processing
                form.setAttribute('class', form.getAttribute('class') + ' processing');

                Accept.dispatchData(secureData, handleResponse);

                //Handle response and clear all sensitive form fields
                function handleResponse (responseData) {
                    //Remove form processing class
                    form.setAttribute('class', form.getAttribute('class').replace(/ ?processing/, ''));

                    let messages = responseData.messages;
                    if(messages.resultCode === 'Ok' && messages.message[0].code === 'I_WC_01') {

                        let opaqueData = responseData.opaqueData;
                        _doc.getElementById("dataDescriptor").value = opaqueData.dataDescriptor;
                        _doc.getElementById("dataValue").value = opaqueData.dataValue;

                        _doc.getElementById("cardNumber").value = "";
                        _doc.getElementById("expMonth").value = "";
                        _doc.getElementById("expYear").value = "";
                        _doc.getElementById("cardCode").value = "";

                        //Set form class to success and submit
                        form.setAttribute('class', form.getAttribute('class') + ' success');
                        form.submit();

                    } else {
                        let messagesDiv = form.getElementsByClassName('messages');
                        if(messagesDiv.length > 0) {
                            messagesDiv[0].innerHTML = '<p class="error">'+messages.message[0].text+'</p>';
                        }

                    }
                }
            }

            return false; //We don't want the form to post any sensitive data back
        }
    }

})(window, document, window.jQuery, window.MooTools);