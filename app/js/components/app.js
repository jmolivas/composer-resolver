'use strict';

const React         = require('react');
const api           = require('../api.js');

var AppComponent = React.createClass({

    getInitialState: function() {
        return {
            dockerIsRunning: false,
            composerJson: '',
            showDrop: false
        }
    },

    componentDidMount: function() {
        this.checkForDocker();
    },

    checkForDocker: function() {
        api.request('is-docker-running')
            .then(function (result) {
                this.setState({dockerIsRunning: result});
            }.bind(this));
    },

    handleDragEnter: function(e) {
        e.preventDefault();
        this.setState({showDrop: true});
    },

    handleDragEnd: function(e) {
        e.preventDefault();
        this.setState({showDrop: false});
    },

    handleDrop: function(e) {
        e.preventDefault();
        this.setState({showDrop: false});

        var fileReader = new FileReader();

        fileReader.onload = function(file) {
            this.setState({composerJson: file.target.result})
        }.bind(this);

        fileReader.readAsText(e.dataTransfer.files[0]);
    },

    handleComposerJsonInput: function(e) {
        this.setState({composerJson: e.target.value});
    },

    render: function() {

        var label = this.state.dockerIsRunning ? 'Yes' : 'No';
        var classes = ['app'];

        if (this.state.showDrop) {
            classes.push('show-drop');
        }

        return (
            <div className={classes.join(' ')} onDragOver={this.handleDragEnter} onDragLeave={this.handleDragEnd} onDrop={this.handleDrop}>
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
                                    <label htmlFor="composerJson">Enter your composer.json here.</label>
                                    <textarea ref="composerJson" className="form-control" id="composerJson" onChange={this.handleComposerJsonInput} value={this.state.composerJson} />
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
