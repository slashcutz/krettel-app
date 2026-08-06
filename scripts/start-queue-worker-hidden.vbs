Set WshShell = CreateObject("WScript.Shell")
WshShell.Run "cmd /c ""C:\laragon\www\krettel-app\scripts\queue-worker.cmd""", 0, False
