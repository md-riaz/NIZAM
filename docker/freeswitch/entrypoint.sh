#!/bin/sh
# Substitute known NIZAM environment variables in FreeSWITCH XML configuration
# files before starting FreeSWITCH.  We pass the variable names explicitly to
# envsubst so that FreeSWITCH's own ${channel_variable} patterns are left
# intact and are not accidentally replaced with empty strings.
set -e

CONF_DIR=/etc/freeswitch

# Only substitute variables that are explicitly passed from the environment.
# Add any new NIZAM_* variables here when they are added to the config files.
SUBST_VARS='${NIZAM_XML_CURL_URL}'

find "$CONF_DIR" -name '*.xml' | while read -r f; do
    envsubst "$SUBST_VARS" < "$f" > "${f}.tmp" && mv "${f}.tmp" "$f"
done

exec /usr/sbin/freeswitch "$@"
