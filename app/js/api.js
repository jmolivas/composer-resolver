'use strict';

const ipc       = electronRequire('electron').ipcRenderer;
const Promise   = require('bluebird');
const shortid   = require('shortid');

var resolvers = {};

ipc.on('composer-resolver-api-response', function (event, response) {
    if (undefined !== resolvers[response.endpoint]
        && undefined !== resolvers[response.endpoint][response.requestId]
    ) {
        resolvers[response.endpoint][response.requestId].call(this, response.payload);
    }
});


function request(endpoint, payload) {

    // Create an ID for that request
    var requestId = shortid.generate();
    // Send the data
    ipc.send('composer-resolver-api-request', {
        requestId: requestId,
        endpoint: endpoint,
        payload: payload
    });

    // Init resolvers for endpoint if undefined
    if (undefined === resolvers[endpoint]) {
        resolvers[endpoint] = {};
    }

    return new Promise(function (resolve, reject) {
        resolvers[endpoint][requestId] = resolve;
    });
}


module.exports = {
    request: request
};
