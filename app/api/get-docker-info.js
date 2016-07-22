'use strict';

const Promise   = require('bluebird');

module.exports = function (docker) {
    var module = {};

    module.handleResponse = function() {

        return new Promise(function(resolve, reject) {

            docker.info(function(err, info) {
                // Either the info or null if not started
                resolve(info);
            });
        });
    };

    return module;
};
