'use strict';

const React         = require('react');
const ipc           = require('electron').ipcRenderer;

var AppComponent = React.createClass({

    getInitialState: function() {
        return {
            dockerAvailable: false
        }
    },

    componentDidMount: function() {
        this.checkForDocker();

        ipc.on('get-docker-version-reply', function (event, arg) {
            console.log(arg);
        });
    },

    checkForDocker: function() {
        ipc.send('get-docker-version', 'dummy');
    },

    render: function() {
        return (
            <div></div>
        )
    }
});

module.exports = AppComponent;
