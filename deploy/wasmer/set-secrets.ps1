# Sets the Wasmer Edge secrets for krettel-app.
# Run from the repo root after installing the Wasmer CLI (winget install wasmer)
# and logging in (wasmer login).
#
# NOTE: secrets are set once. To CHANGE a value later you must delete and
# recreate it: `wasmer app secret delete <name>` then re-run the create below.

$secrets = @{
    APP_KEY              = "base64:A/zA43K6XN1JzKqCtrpfucXFxEw2guTB6MDSkyYhiMo="
    PIXELDRAIN_BASE_URL  = "https://pixeldrain.net"
    PIXELDRAIN_API_KEY   = "4b6a51a3-a844-4d86-abf3-0d79bfa70aac"
    PIXELDRAIN_EMAIL     = "slashcutz.official@gmail.com"
    PIXELDRAIN_PASSWORD  = "SoNa12@#"
    PIXELDRAIN_STREAM_MODE = "redirect"
    R2_ACCESS_KEY_ID     = "b6c6bc75524a6bf167e3bae6600749b1"
    R2_SECRET_ACCESS_KEY = "0f9048fa7087dbb509ccdbab75fb9c586bd53961421c9f2f532fecf873e8bcee"
    TERABOX_EMAIL        = "slashcutz.official@gmail.com"
    TERABOX_PASSWORD     = "SoNa12@#"
    TERABOX_NDUS         = "YvA8sBVpeHuieIVVtH156RLl2ej96rqisb4alKbH"
    TERABOX_REMOTE_DIR   = "/Apps/Krettel"
    TERABOX_WEB_HOST     = "https://www.1024terabox.com"
}

foreach ($name in $secrets.Keys) {
    Write-Host "Creating secret: $name"
    wasmer app secret create $name $secrets[$name]
    if (-not $?) { Write-Warning "Failed to create $name" }
}
