#!/bin/sh
# Validate required FreeSWITCH runtime paths before startup. Bind-mounted
# config files are treated as read-only so container startup fails loudly
# instead of trying to rewrite host-owned XML in place.
set -e

CONF_DIR=/etc/freeswitch
REAL_CONF_DIR=$(readlink -f "$CONF_DIR" 2>/dev/null || printf '%s' "$CONF_DIR")
RUNTIME_SIP_PROFILE_DIR=/usr/local/freeswitch/db/sip_profiles
RUNTIME_EXTERNAL_GATEWAY_DIR="$RUNTIME_SIP_PROFILE_DIR/external"
XML_CDR_DIR="${FREESWITCH_XML_CDR_LOG_DIR:-/var/log/freeswitch/xml_cdr}"

fatal() {
    printf 'FATAL: %s\n' "$1" >&2
    exit 1
}

# Auto-detect External IP if not provided
if [ -z "$EXT_RTP_IP" ] || [ "$EXT_RTP_IP" = "auto-nat" ]; then
    export EXT_RTP_IP="auto-nat"
fi
if [ -z "$EXT_SIP_IP" ] || [ "$EXT_SIP_IP" = "auto-nat" ]; then
    export EXT_SIP_IP="auto-nat"
fi

[ -d "$REAL_CONF_DIR/autoload_configs" ] || fatal "Missing FreeSWITCH autoload config directory: $REAL_CONF_DIR/autoload_configs"
[ -r "$REAL_CONF_DIR/autoload_configs/sofia.conf.xml" ] || fatal "Missing or unreadable Sofia config: $REAL_CONF_DIR/autoload_configs/sofia.conf.xml"
[ -r "$REAL_CONF_DIR/autoload_configs/xml_curl.conf.xml" ] || fatal "Missing or unreadable XML CURL config: $REAL_CONF_DIR/autoload_configs/xml_curl.conf.xml"
[ -r "$REAL_CONF_DIR/autoload_configs/switch.conf.xml" ] || fatal "Missing or unreadable switch config: $REAL_CONF_DIR/autoload_configs/switch.conf.xml"
[ -d "$RUNTIME_SIP_PROFILE_DIR" ] || fatal "Missing runtime SIP profile directory: $RUNTIME_SIP_PROFILE_DIR"
[ -w "$RUNTIME_SIP_PROFILE_DIR" ] || fatal "Runtime SIP profile directory is not writable: $RUNTIME_SIP_PROFILE_DIR"
[ -d "$RUNTIME_EXTERNAL_GATEWAY_DIR" ] || fatal "Missing external gateway include directory: $RUNTIME_EXTERNAL_GATEWAY_DIR"
[ -w "$RUNTIME_EXTERNAL_GATEWAY_DIR" ] || fatal "External gateway include directory is not writable: $RUNTIME_EXTERNAL_GATEWAY_DIR"
[ -d "$XML_CDR_DIR" ] || fatal "Missing XML CDR directory: $XML_CDR_DIR"
[ -w "$XML_CDR_DIR" ] || fatal "XML CDR directory is not writable: $XML_CDR_DIR"
[ -r "$RUNTIME_SIP_PROFILE_DIR/internal.xml" ] || fatal "Missing required Sofia profile: $RUNTIME_SIP_PROFILE_DIR/internal.xml"
[ -r "$RUNTIME_SIP_PROFILE_DIR/external.xml" ] || fatal "Missing required Sofia profile: $RUNTIME_SIP_PROFILE_DIR/external.xml"

exec /usr/sbin/freeswitch "$@"
