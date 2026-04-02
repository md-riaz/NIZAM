#!/bin/sh
# Substitute known platform environment variables in FreeSWITCH XML configuration
# files before starting FreeSWITCH. We pass variable names explicitly to
# envsubst so that FreeSWITCH ${channel_variable} patterns are left intact
# and are not accidentally replaced with empty strings.
set -e

CONF_DIR=/etc/freeswitch
REAL_CONF_DIR=$(readlink -f "$CONF_DIR" 2>/dev/null || printf '%s' "$CONF_DIR")

# Only substitute variables that are explicitly passed from the environment.
# Add any new variables here when they are introduced in XML templates.
SUBST_VARS='${FREESWITCH_XML_CURL_ENDPOINT_INTERNAL}'

find -L "$REAL_CONF_DIR" -name '*.xml' | while read -r f; do
    envsubst "$SUBST_VARS" < "$f" > "${f}.tmp" && mv "${f}.tmp" "$f"
done

exec /usr/sbin/freeswitch "$@"
