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
                <div className="container-fluid">
                    <div className="row">
                        <div className="col-md-12">
                            <h3>Tests</h3>
                            <div className="panel-group" id="panel-978313">
                                <div className="panel panel-default">
                                    <div className="panel-heading">
                                        <a className="panel-title collapsed" data-toggle="collapse" data-parent="#panel-978313" href="#panel-element-936251">Collapsible Group Item #1</a>
                                    </div>
                                    <div id="panel-element-936251" className="panel-collapse collapse">
                                        <div className="panel-body">
                                            Anim pariatur cliche...
                                        </div>
                                    </div>
                                </div>
                                <div className="panel panel-default">
                                    <div className="panel-heading">
                                        <a className="panel-title" data-toggle="collapse" data-parent="#panel-978313" href="#panel-element-385081">Collapsible Group Item #2</a>
                                    </div>
                                    <div id="panel-element-385081" className="panel-collapse collapse in">
                                        <div className="panel-body">
                                            Anim pariatur cliche...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="row">
                        <div className="col-md-12">
                            <h3>composer.json</h3>
                            <form role="form">
                                <div className="form-group">
                                    <label for="composerJson">Enter your composer.json here.</label>
                                    <textarea className="form-control" id="composerJson" />
                                </div>
                                <button type="submit" className="btn btn-default">Submit</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        )
    }
});

module.exports = AppComponent;
