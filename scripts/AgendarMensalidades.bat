@echo off
set PHP=C:\xampp\php\php.exe
set PROJECT=C:\xampp\htdocs\clube

cd /d "%PROJECT%"
"%PHP%" "%PROJECT%\cron\gerar_mensalidades.php"
