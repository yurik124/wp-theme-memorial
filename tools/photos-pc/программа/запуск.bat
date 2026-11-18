@echo off
rem Общий запуск для «1 Предпросмотр» и «2 Обработать»: при первом запуске ставит Python-пакеты в папку
rem «программа\python» (рядом, систему не трогает), потом запускает обработку. Параметр: preview или process.
chcp 65001 >nul
setlocal
set "HERE=%~dp0"
set "ROOT=%~dp0.."
set "VENV=%HERE%python"
set "PYTHONIOENCODING=utf-8"
set "PYTHONUTF8=1"
set "U2NET_HOME=%HERE%модель"

if exist "%VENV%\Scripts\python.exe" goto ready

echo Первый запуск: подготовка (5–10 минут, нужен интернет)...
set "PY="
rem модель фона (onnxruntime) есть для Python 3.10–3.13: сначала ищем их
for %%V in (3.12 3.13 3.11 3.10) do if not defined PY py -%%V -c "import sys" >nul 2>nul && set "PY=py -%%V"
if not defined PY python -c "import sys; assert (3, 10) <= sys.version_info < (3, 14)" >nul 2>nul && set "PY=python"
if not defined PY (
	echo.
	echo Не найден Python 3.10–3.13. Пробую установить его через winget...
	winget install -e --id Python.Python.3.12 --scope user --accept-package-agreements --accept-source-agreements
	echo.
	echo Если установка прошла — закройте это окно и запустите файл ещё раз.
	echo Если нет — скачайте Python с https://www.python.org/downloads/ ^(галочка «Add python.exe to PATH»^) и запустите файл ещё раз.
	pause
	exit /b 1
)
%PY% -m venv "%VENV%" || goto fail
"%VENV%\Scripts\python.exe" -m pip install --disable-pip-version-check -q --upgrade pip || goto fail
"%VENV%\Scripts\python.exe" -m pip install --disable-pip-version-check -r "%HERE%requirements.txt" || goto fail
echo Подготовка завершена.
echo.

:ready
if /i "%~1"=="preview" (
	"%VENV%\Scripts\python.exe" "%HERE%memorial_photos.py" "%ROOT%" --preview
) else (
	"%VENV%\Scripts\python.exe" "%HERE%memorial_photos.py" "%ROOT%"
)
echo.
pause
exit /b 0

:fail
echo.
echo Не получилось подготовить программу. Проверьте интернет и запустите ещё раз.
echo Если повторится — удалите папку «программа\python» и попробуйте снова.
pause
exit /b 1
