#!/usr/bin/env bash
# Sets the Wasmer Edge secrets for krettel-app (bash/macOS/Linux).
# Run from the repo root after installing the Wasmer CLI and `wasmer login`.
# NOTE: secrets are set once; to change a value delete it first.

set -e

declare -A secrets=(
  [APP_KEY]="base64:A/zA43K6XN1JzKqCtrpfucXFxEw2guTB6MDSkyYhiMo="
  [PIXELDRAIN_BASE_URL]="https://pixeldrain.net"
  [PIXELDRAIN_API_KEY]="4b6a51a3-a844-4d86-abf3-0d79bfa70aac"
  [PIXELDRAIN_EMAIL]="slashcutz.official@gmail.com"
  [PIXELDRAIN_PASSWORD]="SoNa12@#"
  [PIXELDRAIN_STREAM_MODE]="redirect"
  [TERABOX_EMAIL]="slashcutz.official@gmail.com"
  [TERABOX_PASSWORD]="SoNa12@#"
  [TERABOX_NDUS]="YvA8sBVpeHuieIVVtH156RLl2ej96rqisb4alKbH"
  [TERABOX_REMOTE_DIR]="/Apps/Krettel"
  [TERABOX_WEB_HOST]="https://www.1024terabox.com"
)

for name in "${!secrets[@]}"; do
  echo "Creating secret: $name"
  wasmer app secret create "$name" "${secrets[$name]}"
done
