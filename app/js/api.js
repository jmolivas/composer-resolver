'use strict';

const ipc       = electronRequire('electron').ipcRenderer;
const Promise   = require('bluebird');
const shortid   = require('shortid');

var listeners = {};
var resolvers = {};


function request(name, payload) {

    // Create an ID for that request
    var requestId = shortid.generate();

    // Send the data
    ipc.send('composer-resolver-api-' + name + '-request', {
        requestId: requestId,
        payload: payload
    });

    // Make sure the listener is only registered once per api name
    if (undefined === listeners[name]) {
        listeners[name] = ipc.on('composer-resolver-api-' + name + '-response', function (event, response) {
            if (undefined !== resolvers[response.requestId]) {
                resolvers[response.requestId].call(this, response.payload);
            }
        });
    }

    return new Promise(function (resolve, reject) {
        resolvers[requestId] = resolve;
    });
}


module.exports = {
    request: request
};
