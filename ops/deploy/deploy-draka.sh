#!/bin/sh
# Candidate forced-command entry point. Install only after the owner's VPS audit.
set -eu
[ "$#" -eq 0 ] || exit 64
exec /usr/bin/env -i \
    PATH=/usr/local/bin:/usr/bin:/bin \
    HOME=/home/draka-deploy \
    SSH_ORIGINAL_COMMAND="${SSH_ORIGINAL_COMMAND-}" \
    /usr/bin/python3 -I /usr/local/lib/draka-deploy/deploy.py
