const electron        = require('electron');
const {app}           = electron;
const {BrowserWindow} = electron;
const ipc             = electron.ipcMain;

let win;

function createWindow() {
    win = new BrowserWindow({width: 1024, height: 768});
    win.loadURL(`file://${__dirname}/index.html`);

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

// Docker version
ipc.on('get-docker-version-request', function (event, arg) {
    event.sender.send('get-docker-version-reply', 'wanted!');
});
