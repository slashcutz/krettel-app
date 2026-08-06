#!/usr/bin/env bash
# =============================================================================
# Krettel — safely store TeraBox credentials for oracle-deploy.sh
#
# Run once on the Oracle VM, BEFORE scripts/oracle-deploy.sh. Prompts for the
# values with input hidden, writes them to a root-only file so they never
# appear in bash history or in the process list (no -e / --preserve-env).
#
#     sudo bash scripts/terabox-creds.sh
#     sudo bash scripts/oracle-deploy.sh        # reads /root/terabox.env automatically
# =============================================================================

set -euo pipefail

CREDS="/root/terabox.env"

log()  { echo -e "\n\033[1;32m[krettel]\033[0m $*"; }
warn() { echo -e "\n\033[1;33m[krettel!]\033[0m $*"; }

if [ "$(id -u)" -ne 0 ]; then
    warn "Run with sudo:  sudo bash scripts/terabox-creds.sh"
    exit 1
fi

log "Enter TeraBox credentials (input is hidden)."
read -r    -p "  TERABOX_EMAIL:     " EMAIL
read -r -s -p "  TERABOX_PASSWORD:  " PASSWORD; echo
read -r -s -p "  TERABOX_NDUS:      " NDUS;     echo

if [ -z "${EMAIL}" ] || [ -z "${PASSWORD}" ]; then
    warn "TERABOX_EMAIL and TERABOX_PASSWORD are required."
    exit 1
fi

umask 077
: > "${CREDS}"
# %q shell-quotes each value so passwords with spaces/special chars survive sourcing.
printf 'TERABOX_EMAIL=%q\n'    "${EMAIL}"    >> "${CREDS}"
printf 'TERABOX_PASSWORD=%q\n' "${PASSWORD}" >> "${CREDS}"
printf 'TERABOX_NDUS=%q\n'     "${NDUS}"     >> "${CREDS}"
chmod 600 "${CREDS}"

log "Saved to ${CREDS} (root-only, not in shell history)."
echo "Now deploy — the script reads this file automatically:"
echo "    sudo bash scripts/oracle-deploy.sh"
echo
echo "To change credentials later: re-run this script (or edit ${CREDS}), then:"
echo "    sudo systemctl restart krettel-worker php8.3-fpm"
