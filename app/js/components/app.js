'use strict';

const React         = require('react');
const ipc           = electronRequire('electron').ipcRenderer;

var AppComponent = React.createClass({

    getInitialState: function() {
        return {
            dockerIsRunning: false
        }
    },

    componentDidMount: function() {
        var self = this;
        this.checkForDocker();

        ipc.on('get-docker-running-reply', function (event, arg) {
            self.setState({dockerIsRunning: arg});
        });
    },

    checkForDocker: function() {
        ipc.send('get-docker-running-request');
    },

    render: function() {

        var label = this.state.dockerIsRunning ? 'Yes' : 'No';

        return (
            <div>
                <p>Docker is available and running on your system: {label}</p>
                <button onClick={this.checkForDocker}>Check for Docker again.</button>
            </div>
        )
    }
});

module.exports = AppComponent;
