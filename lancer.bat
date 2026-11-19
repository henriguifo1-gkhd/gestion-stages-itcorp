@echo off
setlocal EnableExtensions
title IT CORP - Gestion des stages - Lancement

rem ============================================================
rem  Lanceur de l'application IT CORP - Gestion des stages
rem  Placez ce fichier dans le dossier "gestion-stages" (celui qui
rem  contient README.md, app, config, public et sql), puis double-cliquez.
rem  Si WAMP n'est pas installe dans C:\wamp64, changez la ligne suivante.
rem ============================================================
set "WAMP=C:\wamp64"

if not exist "%WAMP%\wampmanager.exe" goto wamp_absent

rem --- Adresse de l'application, deduite de l'emplacement de ce fichier ---
set "CHEMIN=%~dp0"
call set "REL=%%CHEMIN:%WAMP%\www\=%%"
if /i "%REL%"=="%CHEMIN%" goto hors_www
set "REL=%REL:\=/%"
set "URL=http://localhost/%REL%public/"

echo.
echo  IT CORP - Gestion des stages
echo  ----------------------------------------
echo  Demarrage de WAMP (Apache + MySQL)...

rem --- Demarrer WAMP s'il n'est pas deja lance ---
tasklist /fi "imagename eq wampmanager.exe" | find /i "wampmanager.exe" >nul
if errorlevel 1 start "" "%WAMP%\wampmanager.exe"

rem --- Attendre que MySQL (3306) et Apache (80) repondent : 2 minutes maximum ---
set /a essais=0
:attente
netstat -an | find ":3306 " >nul
if errorlevel 1 goto pas_pret
netstat -an | find ":80 " >nul
if errorlevel 1 goto pas_pret
goto pret

:pas_pret
set /a essais+=1
if %essais% GEQ 60 goto echec
timeout /t 2 /nobreak >nul
goto attente

:pret
echo  Serveurs prets. Ouverture de l'application...
timeout /t 2 /nobreak >nul
start "" "%URL%"
echo.
echo  Adresse : %URL%
echo  Compte de demonstration : admin@itcorp.co
echo.
timeout /t 4 >nul
exit /b 0

:wamp_absent
echo.
echo  WAMP est introuvable dans %WAMP%.
echo  Ouvrez ce fichier avec le Bloc-notes et modifiez la ligne : set "WAMP=..."
echo  pour indiquer le dossier ou WAMP est installe.
echo.
pause
exit /b 1

:hors_www
echo.
echo  Ce fichier doit se trouver dans le dossier du projet, a l'interieur de
echo  %WAMP%\www
echo  Dossier actuel : %CHEMIN%
echo  Deplacez le dossier du projet dans %WAMP%\www puis relancez ce fichier.
echo.
pause
exit /b 1

:echec
echo.
echo  MySQL ou Apache ne repond pas apres 2 minutes.
echo  Verifiez l'icone WAMP pres de l'horloge : elle doit etre verte.
echo  Si elle est orange ou rouge, faites un clic gauche dessus puis
echo  "Demarrer tous les services".
echo.
pause
exit /b 1
