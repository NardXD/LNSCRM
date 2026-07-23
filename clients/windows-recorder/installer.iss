; Inno Setup script for Windows Recorder
; Prerequisite (from this folder): npm run installer:prepare
;   - dist\windows-recorder.exe  = headless queue sync (Task Scheduler / CLI)
;   - installer-output-electron\ItsWorkPlaceRecorderUI.exe = Electron UI (login, recording, lists)
; Then compile this script with Inno Setup.

#define AppName "ItsWorkPlace Recorder"
#define AppVersion "0.1.0"
#define AppPublisher "ItsWorkPlaceCRM"
#define AppExeSync "windows-recorder.exe"
#define AppExeUi "ItsWorkPlaceRecorderUI.exe"

[Setup]
AppId={{A20F90D6-8A21-4A1F-84CF-55BEE76AF0D7}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
DefaultDirName={autopf}\ItsWorkPlaceRecorder
DefaultGroupName=ItsWorkPlace Recorder
OutputDir=installer-output
OutputBaseFilename=itsworkplace-recorder-setup
Compression=lzma
SolidCompression=yes

[Files]
Source: "dist\{#AppExeSync}"; DestDir: "{app}"; Flags: ignoreversion
Source: "installer-output-electron\{#AppExeUi}"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
; Main app = UI (logout, hourly capture, today list)
Name: "{group}\{#AppName}"; Filename: "{app}\{#AppExeUi}"
Name: "{autodesktop}\{#AppName}"; Filename: "{app}\{#AppExeUi}"; Tasks: desktopicon
Name: "{group}\{#AppName} (sync queue only)"; Filename: "{app}\{#AppExeSync}"

[Tasks]
Name: "desktopicon"; Description: "Create a desktop icon"; GroupDescription: "Additional icons:"
