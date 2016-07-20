'use strict';

// Simple helper to connect to docker locally using dockerode

const Docker = require('dockerode');

// Unix / OS X
var host = 'unix:///var/run/docker.sock';

// Windows
if ('win32' === process.platform) {
    host = 'npipe:////./pipe/docker_engine';
}

// Or environment variable is set
if (process.env.DOCKER_HOST) {
    host = process.env.DOCKER_HOST;
}

var opts = {};

if (host.indexOf('unix://') === 0) {
    opts.socketPath = host.substring(7);
} else {
    var split = /(?:tcp:\/\/)?(.*?):([0-9]+)/g.exec(host);

    if (!split || split.length !== 3) {
        throw new Error('Host should be something like tcp://localhost:1234');
    }

    opts.protocol = 'http';
    opts.host = split[1];
    opts.port = split[2];
}

var docker = new Docker(opts);
module.exports = new Docker(opts);
