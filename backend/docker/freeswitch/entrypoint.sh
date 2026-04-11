#!/bin/sh
# Substitute known platform environment variables in FreeSWITCH XML configuration
# files before starting FreeSWITCH. We pass variable names explicitly to
# envsubst so that FreeSWITCH ${channel_variable} patterns are left intact
# and are not accidentally replaced with empty strings.
set -e

CONF_DIR=/etc/freeswitch
REAL_CONF_DIR=$(readlink -f "$CONF_DIR" 2>/dev/null || printf '%s' "$CONF_DIR")

# Auto-detect External IP if not provided
if [ -z "$EXT_RTP_IP" ] || [ "$EXT_RTP_IP" = "auto-nat" ]; then
    export EXT_RTP_IP="auto-nat"
fi
if [ -z "$EXT_SIP_IP" ] || [ "$EXT_SIP_IP" = "auto-nat" ]; then
    export EXT_SIP_IP="auto-nat"
fi

# Only substitute variables that are explicitly passed from the environment.
# Add any new variables here when they are introduced in XML templates.
SUBST_VARS='${FREESWITCH_XML_CURL_ENDPOINT_INTERNAL} ${EXT_RTP_IP} ${EXT_SIP_IP} ${RTP_PORT_RANGE_START} ${RTP_PORT_RANGE_END}'

# Ensure gateway directories exist before FreeSWITCH starts to avoid include errors
mkdir -p /usr/local/freeswitch/conf/sip_profiles/external
mkdir -p /usr/local/freeswitch/conf/sip_profiles/internal

find -L "$REAL_CONF_DIR" -name '*.xml' | while read -r f; do
    envsubst "$SUBST_VARS" < "$f" > "${f}.tmp" && cat "${f}.tmp" > "$f" && rm "${f}.tmp"
done

exec /usr/sbin/freeswitch "$@"
