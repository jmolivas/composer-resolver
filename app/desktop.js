const electron        = require('electron');
const docker          = require(__dirname + '/docker.js');
const {app}           = electron;
const {BrowserWindow} = electron;
const ipc             = electron.ipcMain;

let win;

function createWindow() {
    win = new BrowserWindow({width: 1024, height: 768});
    win.loadURL(`file://${__dirname}/index.html`);
    win.openDevTools();

    win.on('closed', function() {
        win = null;
    });
}

app.on('ready', createWindow);
app.on('window-all-closed', function() {
    // On macOS it is common for applications and their menu bar
    // to stay active until the user quits explicitly with Cmd + Q
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('activate', function() {
    // On macOS it's common to re-create a window in the app when the
    // dock icon is clicked and there are no other windows open.
    if (win === null) {
        createWindow();
    }
});

// Run our little process APIs
var apis = {
    'get-docker-info': require(__dirname + '/api/get-docker-info.js')(docker)
};

for (var name in apis) {
    if (!apis.hasOwnProperty(name)) {
        continue;
    }

    var api = apis[name];
    ipc.on('composer-resolver-api-' + name + '-request', function (event, request) {
        api.handleResponse(request.payload).then(function(response) {
            event.sender.send('composer-resolver-api-' + name + '-response', {
                requestId: request.requestId,
                payload: response
            });
        });
    });
}

