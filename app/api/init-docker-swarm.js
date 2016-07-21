'use strict';

const Promise   = require('bluebird');

module.exports = function (docker) {
    var module = {};

    module.handleResponse = function() {

        return new Promise(function(resolve, reject) {

            require('crypto').randomBytes(30, function(err, buffer) {
                var secret = buffer.toString('hex');

                // Options
                var opts = {
                    ListenAddr: '0.0.0.0:2377', // Default Docker
                    secret: secret
                };

                // random secret
                docker.swarmInit(opts, function(err, result) {

                    // No errors = successfully created swarm
                    if (null === err) {
                        resolve(true);
                        return;
                    }

                    // Swarm already exists
                    if (406 === err.statusCode) {
                        resolve(true);
                    }

                    // Something went wrong
                    // @todo, general log or so?
                    resolve(err);
                });
            });
        });
    };

    return module;
};
