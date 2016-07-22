'use strict';

const React         = require('react');
const semver        = require('semver');
const api           = require('../api.js');

var AppComponent = React.createClass({

    getInitialState: function() {
        return {
            dockerInfo: {},
            error: null,
            swarmIsRunning: false,
            composerJson: '',
            showDrop: false
        }
    },

    componentDidMount: function() {
        var self = this;

        api.request('get-docker-info')
            .then(function (info) {
                self.setState({dockerInfo: info});

                if (self.isOneTestFailing(self.getTests(info))) {
                    throw new Error('At least one test is failing.');
                }

                return null;
            })
            .then(function () {
                return api.request('init-docker-swarm');
            })
            .then(function (initDockerSwarmResult) {
                if (true === initDockerSwarmResult) {
                    self.setState({swarmIsRunning: true});
                    return null;
                }

                throw new Error('At least one test is failing.');
            })
            .catch(function(err) {
                self.setState({error: err.message});
            });
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

        // Disable dragging if there's errors
        if (this.state.oneTestFailing) {
            e.stopPropagation();
            return false;
        }

        var fileReader = new FileReader();

        fileReader.onload = function(file) {
            this.setState({composerJson: file.target.result})
        }.bind(this);

        fileReader.readAsText(e.dataTransfer.files[0]);
    },

    handleComposerJsonInput: function(e) {
        this.setState({composerJson: e.target.value});
    },

    getTests: function(dockerInfo) {
        var tests = [];

        // Docker running?
        tests.push({
            title:  'Docker is up and running',
            descr:  'Please make sure Docker is installed and up and running!',
            value:  null === dockerInfo ? 'down' : 'up',
            result: null !== dockerInfo ? 'success' : 'error'
        });

        // If Docker is not running we can abort immediately
        if (null === dockerInfo) {

            return tests;
        }

        // Docker version
        var serverVersion = dockerInfo.ServerVersion ? dockerInfo.ServerVersion : '';

        tests.push({
            title:  'Version-Check',
            descr:  'Please make sure you run at least version 1.12.0-rc1 of Docker!',
            value:  serverVersion,
            result: semver.valid(serverVersion) && semver.gt(serverVersion, '1.12.0-rc1') ? 'success' : 'error'
        });

        // Memory limit
        tests.push({
            title:  'RAM Limit',
            descr:  'Docker itself can be limited to a certain amount of RAM. Make sure you give Composer Resolver at least 2 GB!',
            value:  dockerInfo.MemoryLimit ? ((dockerInfo.MemTotal / 1073741824).toFixed(2) + ' GB') : 'unlimited',
            result: !dockerInfo.MemoryLimit || 2147483648 <= dockerInfo.MemTotal ? 'success' : 'error'
        });

        // Swarm is running
        tests.push({
            title:  'Swarm is running?',
            descr:  'The Composer Resolver uses the Swarm features of Docker 1.12. The Docker Swarm must be up!',
            value:  this.state.swarmIsRunning ? 'up' : 'down',
            result: this.state.swarmIsRunning ? 'success' : 'error'
        });

        return tests;
    },

    isOneTestFailing: function(tests) {
        var failing = false;

        tests.forEach(function(test) {
            if ('error' === test.result) {
                failing = true;
            }
        });

        return failing;
    },

    render: function() {

        var classes = ['app'];

        if (this.state.showDrop) {
            classes.push('show-drop');
        }

        return (
            <div className={classes.join(' ')} onDragOver={this.handleDragEnter} onDragLeave={this.handleDragEnd} onDrop={this.handleDrop}>
                <div className="container-fluid">
                    <div className="row">
                        <div className="col-md-12">
                            <h3>Tests</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Test</th>
                                        <th>Description</th>
                                        <th>Value</th>
                                        <th>Result</th>
                                    </tr>
                                </thead>
                                <tbody>
                                {this.getTests(this.state.dockerInfo).map(function(test, i) {
                                    return <TestRow key={i} test={test} />
                                })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div className={"row" + (null !== this.state.error ? ' error' : '')}>
                        <div className="col-md-12">
                            <h3>composer.json</h3>

                            <div className={"error-message" + (null === this.state.error ? ' hide' : '')}>
                                <p>Cannot work properly due the following error:</p>
                                <p>{this.state.error}</p>
                            </div>
                            <form role="form" className={"form" + (null !== this.state.error ? ' hide' : '')}>
                                <div className="form-group">
                                    <label htmlFor="composerJson">Enter your composer.json here (you can also just drag n drop!).</label>
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

var TestRow = React.createClass({

    render: function() {
        return (
            <tr className={"test " + this.props.test.result}>
                <td className="title">{this.props.test.title}</td>
                <td className="descr">{this.props.test.descr}</td>
                <td className="value">{this.props.test.value}</td>
                <td className="result">{this.props.test.result}</td>
            </tr>
        )
    }
});

module.exports = AppComponent;
