$ErrorActionPreference = 'Stop'

$ollamaExe = Join-Path $env:LOCALAPPDATA 'Programs\Ollama\ollama.exe'
$projectRoot = Split-Path -Parent $PSScriptRoot
$logDir = Join-Path $projectRoot 'logs'
$outLog = Join-Path $logDir 'ollama-out.log'
$errLog = Join-Path $logDir 'ollama-err.log'

New-Item -ItemType Directory -Force -Path $logDir | Out-Null

function Test-LocalPort {
    param(
        [string]$HostName = '127.0.0.1',
        [int]$Port = 11434,
        [int]$TimeoutMs = 1500
    )

    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $async = $client.BeginConnect($HostName, $Port, $null, $null)
        $connected = $async.AsyncWaitHandle.WaitOne($TimeoutMs, $false)
        if ($connected) {
            $client.EndConnect($async)
        }
        $client.Close()
        return $connected
    } catch {
        return $false
    }
}

if (-not (Test-Path $ollamaExe)) {
    Add-Content -Path $errLog -Value "$(Get-Date -Format s) Ollama executable not found at $ollamaExe"
    exit 1
}

if (Test-LocalPort) {
    Add-Content -Path $outLog -Value "$(Get-Date -Format s) Ollama already running on 127.0.0.1:11434"
    exit 0
}

Start-Process -FilePath $ollamaExe -ArgumentList @('serve') -WindowStyle Hidden -RedirectStandardOutput $outLog -RedirectStandardError $errLog
Add-Content -Path $outLog -Value "$(Get-Date -Format s) Ollama start requested"
