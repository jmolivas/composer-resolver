'use strict';

const exec      = require('child_process').exec;
const Promise   = require('bluebird');


module.exports = {
    handleResponse: function() {

        return new Promise(function(resolve, reject) {

            exec('docker info', function(error, stdout, stderr) {

                var isDockerRunning = false;

                if (null === error
                    && '' === stderr
                    && !stdout.includes('Cannot connect to the Docker daemon')
                    && stdout.includes('Server Version:')
                ) {
                    isDockerRunning = true
                }

                resolve(isDockerRunning);
            });
        });
    }
};
