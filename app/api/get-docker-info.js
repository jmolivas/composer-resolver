'use strict';

const Promise   = require('bluebird');

module.exports = function (docker) {
    var module = {};

    module.handleResponse = function() {

        return new Promise(function(resolve, reject) {

            docker.info(function(err, info) {
                resolve(info);
            });
        });
    };

    return module;
};
