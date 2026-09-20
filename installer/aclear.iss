; AClear installer — automates README steps 2-6 on the shop PC.
;
; Build (on Windows, after scripts\build-release.ps1 has produced the zip):
;   iscc /DAppVersion=1.0.0 installer\aclear.iss
;
; This packages an already-built release. It does NOT hide the PHP source —
; see the note in README. It exists to turn a 30-45 minute manual setup into
; a wizard, and to stop the .env / database / scheduled-task steps being
; mistyped on site.

#ifndef AppVersion
  #define AppVersion "0.0.0"
#endif

#define AppName "AClear"
#define InstallDir "C:\laragon\www\aclear"

[Setup]
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher=AClear
DefaultDirName={#InstallDir}
DisableDirPage=no
DefaultGroupName={#AppName}
OutputDir=..\dist
OutputBaseFilename=AClear-Setup-v{#AppVersion}
Compression=lzma2/max
SolidCompression=yes
; Creating the database user and the scheduled task both need elevation.
PrivilegesRequired=admin
WizardStyle=modern
DisableWelcomePage=no

[Files]
; The extracted contents of dist\aclear-v{#AppVersion}.zip. Unpack the zip to
; installer\payload\ before running iscc.
Source: "payload\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Icons]
Name: "{group}\AClear"; Filename: "https://aclear.test"
Name: "{group}\Uninstall AClear"; Filename: "{uninstallexe}"

[Code]
var
  PhpPage: TInputFileWizardPage;
  DbPage: TInputQueryWizardPage;
  AdminPage: TInputQueryWizardPage;
  BackupPage: TInputDirWizardPage;

function LaragonPhp: String;
var
  FindRec: TFindRec;
  Best, Candidate: String;
begin
  { Laragon supports several PHP versions side by side (php-8.3.x next to
    php-8.4.x), so take the HIGHEST rather than the first match — enumeration
    order is alphabetical, which would silently prefer 8.3 over 8.4. The user
    confirms the result on the PHP wizard page, because the highest installed
    version still isn't guaranteed to be the one Apache is serving.

    ponytail: string comparison, so a hypothetical php-8.10 would sort below
    php-8.9. Harmless while PHP 8 minors stay single-digit, and the confirmation
    page catches it either way; split on the dots if that ever changes. }
  Best := '';
  if FindFirst('C:\laragon\bin\php\php-*', FindRec) then
  try
    repeat
      if (FindRec.Attributes and FILE_ATTRIBUTE_DIRECTORY) <> 0 then
      begin
        Candidate := 'C:\laragon\bin\php\' + FindRec.Name + '\php.exe';
        if FileExists(Candidate) and (CompareText(FindRec.Name, ExtractFileName(ExtractFileDir(Best))) > 0) then
          Best := Candidate;
      end;
    until not FindNext(FindRec);
  finally
    FindClose(FindRec);
  end;
  Result := Best;
end;

function LaragonMysqldump: String;
var
  FindRec: TFindRec;
begin
  Result := '';
  if FindFirst('C:\laragon\bin\mysql\mysql-*', FindRec) then
  try
    repeat
      if (FindRec.Attributes and FILE_ATTRIBUTE_DIRECTORY) <> 0 then
      begin
        Result := 'C:\laragon\bin\mysql\' + FindRec.Name + '\bin\mysqldump.exe';
        if FileExists(Result) then Exit;
      end;
    until not FindNext(FindRec);
  finally
    FindClose(FindRec);
  end;
  Result := '';
end;

function InitializeSetup: Boolean;
begin
  { Fail loudly here rather than halfway through, when a partial install would
    be harder to clean up than a refused one. }
  if LaragonPhp = '' then
  begin
    MsgBox('Laragon with PHP was not found under C:\laragon.' + #13#10#13#10 +
           'Install Laragon (Apache + PHP 8.4 + MySQL 8) first, then run this installer again.',
           mbCriticalError, MB_OK);
    Result := False;
    Exit;
  end;
  Result := True;
end;

procedure InitializeWizard;
begin
  PhpPage := CreateInputFilePage(wpSelectDir,
    'PHP', 'Which PHP does Apache use?',
    'Laragon can have several PHP versions installed. Setup picked the highest ' +
    'it found — check this matches Laragon > Menu > PHP > Version, because the ' +
    'nightly backup task is tied to this exact path.');
  PhpPage.Add('php.exe:', 'php.exe|php.exe', '.exe');
  PhpPage.Values[0] := LaragonPhp;

  DbPage := CreateInputQueryPage(PhpPage.ID,
    'Database', 'MySQL connection',
    'Setup will create the "aclear" database and a dedicated app user. ' +
    'The root password is used only during installation and is not stored.');
  DbPage.Add('MySQL root password:', True);

  AdminPage := CreateInputQueryPage(DbPage.ID,
    'Administrator account', 'The owner''s login',
    'This is the account the owner signs in with. They should change the ' +
    'password and enable two-factor authentication after the first login.');
  AdminPage.Add('Full name:', False);
  AdminPage.Add('Email:', False);
  AdminPage.Add('Password:', True);

  BackupPage := CreateInputDirPage(AdminPage.ID,
    'Backups', 'Where nightly backups are written',
    'Choose a folder inside the owner''s Google Drive or OneDrive sync folder, ' +
    'so backups leave this PC. A folder only on this PC is not a backup.',
    False, '');
  BackupPage.Add('');
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;
  if CurPageID = PhpPage.ID then
  begin
    if not FileExists(PhpPage.Values[0]) then
    begin
      MsgBox('That php.exe does not exist. Pick the php.exe under C:\laragon\bin\php\.', mbError, MB_OK);
      Result := False;
    end;
  end
  else if CurPageID = AdminPage.ID then
  begin
    if (Trim(AdminPage.Values[0]) = '') or (Pos('@', AdminPage.Values[1]) = 0) or (Length(AdminPage.Values[2]) < 8) then
    begin
      MsgBox('Enter a name, a valid email, and a password of at least 8 characters.', mbError, MB_OK);
      Result := False;
    end;
  end
  else if CurPageID = BackupPage.ID then
  begin
    if Trim(BackupPage.Values[0]) = '' then
    begin
      MsgBox('Choose a backup folder.', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

function RandomPassword: String;
var
  PwFile: String;
  ResultCode: Integer;
begin
  { Delegated to .NET's RNGCryptoServiceProvider rather than Pascal's Random:
    this password guards the database and Inno's Random is not seeded from
    anything unpredictable, so it would be guessable from the install time. }
  PwFile := ExpandConstant('{tmp}\aclear-pw.txt');
  if not Exec(ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe'),
       '-NoProfile -Command "$b=New-Object byte[] 24; ' +
       '[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b); ' +
       '[Convert]::ToBase64String($b).TrimEnd(''='') -replace ''[+/]'',''x'' | ' +
       'Set-Content -NoNewline -Encoding ascii ''' + PwFile + '''"',
       '', SW_HIDE, ewWaitUntilTerminated, ResultCode) or (ResultCode <> 0) then
    RaiseException('Could not generate a database password.');

  if not LoadStringFromFile(PwFile, Result) then
    RaiseException('Could not read the generated database password.');

  DeleteFile(PwFile);
  Result := Trim(Result);

  if Length(Result) < 16 then
    RaiseException('Generated database password was too short.');
end;

procedure RunOrFail(const Exe, Params, ErrorLabel: String);
var
  ResultCode: Integer;
begin
  if not Exec(Exe, Params, ExpandConstant('{app}'), SW_HIDE, ewWaitUntilTerminated, ResultCode) or (ResultCode <> 0) then
    RaiseException(ErrorLabel + ' failed (exit ' + IntToStr(ResultCode) + ').');
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  Php, Mysql, Mysqldump, DbPassword, Env, SqlFile, CnfFile, EnvFile: String;
begin
  if CurStep <> ssPostInstall then Exit;

  Php := PhpPage.Values[0];
  Mysqldump := LaragonMysqldump;
  Mysql := Copy(Mysqldump, 1, Length(Mysqldump) - Length('mysqldump.exe')) + 'mysql.exe';
  DbPassword := RandomPassword;

  { Create the database and an app-only user. Never point .env at root.

    Both the root password and the generated one go in throwaway files rather
    than on the command line, where they would be visible in the process list
    to anyone on the PC — the same reason app:backup uses a defaults file. }
  SqlFile := ExpandConstant('{tmp}\aclear-setup.sql');
  CnfFile := ExpandConstant('{tmp}\aclear-setup.cnf');
  try
    SaveStringToFile(SqlFile,
      'CREATE DATABASE IF NOT EXISTS aclear CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' + #13#10 +
      'CREATE USER IF NOT EXISTS ''aclear''@''127.0.0.1'' IDENTIFIED BY ''' + DbPassword + ''';' + #13#10 +
      'ALTER USER ''aclear''@''127.0.0.1'' IDENTIFIED BY ''' + DbPassword + ''';' + #13#10 +
      'GRANT ALL PRIVILEGES ON aclear.* TO ''aclear''@''127.0.0.1'';' + #13#10 +
      'FLUSH PRIVILEGES;' + #13#10, False);

    SaveStringToFile(CnfFile,
      '[client]' + #13#10 +
      'user=root' + #13#10 +
      'password=' + DbPage.Values[0] + #13#10 +
      'host=127.0.0.1' + #13#10, False);

    RunOrFail(Mysql, '--defaults-extra-file="' + CnfFile + '" -e "source ' + SqlFile + '"', 'Creating the database');
  finally
    DeleteFile(SqlFile);
    DeleteFile(CnfFile);
  end;

  { .env from the production template, with the generated values filled in. }
  EnvFile := ExpandConstant('{app}\.env');
  LoadStringFromFile(ExpandConstant('{app}\.env.production.example'), Env);
  StringChangeEx(Env, 'DB_PASSWORD=', 'DB_PASSWORD=' + DbPassword, True);
  StringChangeEx(Env, 'BACKUP_PATH=', 'BACKUP_PATH=' + BackupPage.Values[0], True);
  StringChangeEx(Env, 'MYSQLDUMP_PATH=', 'MYSQLDUMP_PATH=' + Mysqldump, True);
  SaveStringToFile(EnvFile, Env, False);

  RunOrFail(Php, 'artisan key:generate --force', 'Generating the application key');
  RunOrFail(Php, 'artisan migrate --force', 'Creating the database tables');
  RunOrFail(Php, 'artisan db:seed --force', 'Seeding menus and roles');
  RunOrFail(Php, 'artisan app:create-admin --name="' + AdminPage.Values[0] +
                 '" --email="' + AdminPage.Values[1] +
                 '" --password="' + AdminPage.Values[2] + '"', 'Creating the administrator');
  RunOrFail(Php, 'artisan optimize', 'Building caches');

  { Nightly backup at 7pm, catching up if the PC was off (README step 6). }
  RunOrFail(ExpandConstant('{sys}\schtasks.exe'),
    '/Create /F /SC DAILY /ST 19:00 /TN "AClear Backup" /RL HIGHEST ' +
    '/TR "\"' + Php + '\" artisan app:backup"', 'Registering the backup task');

  MsgBox('AClear is installed.' + #13#10#13#10 +
         'Still to do by hand:' + #13#10 +
         '  1. Laragon > Apache > SSL, to enable HTTPS for aclear.test.' + #13#10 +
         '  2. Set up auto-login and Laragon autostart (README step 5).' + #13#10 +
         '  3. Run the backup task once now and confirm a database.sql appears.' + #13#10 +
         '  4. Do a restore drill before leaving site.',
         mbInformation, MB_OK);
end;
