##############################################
# Stage 1: Build FreeSWITCH and dependencies
#
# Tracks what FusionPBX and FS PBX do for their own Debian installs: build from
# source on a current Debian, pin the dependencies that have bitten them, and
# keep the module selection explicit.
#
# Debian 12 (bookworm) rather than 11 (bullseye): FreeSWITCH 1.11.3 dropped
# bullseye. Bookworm is also what both reference projects target, and unlike
# trixie it can still build spandsp from source — trixie needs a package from
# SignalWire's authenticated repository, which would put a personal access token
# in the build.
##############################################
FROM debian:bookworm AS builder

ENV DEBIAN_FRONTEND=noninteractive

# Build dependencies. Mirrors FusionPBX's Debian list, with the bookworm names
# where they differ from bullseye (libtiff-dev, libvpx7) and PCRE2 added: 1.11
# moved the regex engine from PCRE to PCRE2.
RUN set -eux; \
    apt-get update && apt-get install -y \
    autoconf automake devscripts g++ git-core libncurses5-dev libtool libtool-bin make libjpeg-dev \
    pkg-config flac libgdbm-dev libdb-dev gettext equivs dpkg-dev libpq-dev \
    liblua5.2-dev libtiff-dev libcurl4-openssl-dev libsqlite3-dev libpcre2-dev \
    libspeexdsp-dev libspeex-dev libldns-dev libedit-dev libopus-dev libmemcached-dev \
    libshout3-dev libmpg123-dev libmp3lame-dev yasm nasm libsndfile1-dev libuv1-dev libvpx-dev \
    libavformat-dev libswscale-dev libvlc-dev \
    cmake uuid-dev libssl-dev lsb-release wget ca-certificates \
    python3-distutils swig4.0 sqlite3 unzip \
    && rm -rf /var/lib/apt/lists/*

# libks is built from its default branch, which is what both FusionPBX and
# FS PBX do. A pin would be reproducible, but a pin old enough to predate the
# FreeSWITCH version being built is the more likely way to break: libks is
# SignalWire's own support library and moves with the switch.
RUN set -eux; \
    cd /usr/src \
    && git clone https://github.com/signalwire/libks.git libks \
    && cd libks \
    && cmake . \
    && make -j$(nproc) \
    && make install \
    && ldconfig

# sofia-sip 1.13.18, the version FusionPBX pins for Debian. Debian's own package
# is far older and lacks symbols 1.11 expects.
ARG SOFIA_SIP_VERSION=1.13.18
RUN set -eux; \
    cd /usr/src \
    && wget "https://github.com/freeswitch/sofia-sip/archive/refs/tags/v${SOFIA_SIP_VERSION}.zip" \
    && unzip "v${SOFIA_SIP_VERSION}.zip" \
    && cd "sofia-sip-${SOFIA_SIP_VERSION}" \
    && sh autogen.sh \
    && ./configure CFLAGS="-g -ggdb" --with-pic --with-glib=no --without-doxygen --disable-stun \
    && make -j$(nproc) \
    && make install \
    && ldconfig

# spandsp has no usable release, so everyone pins a commit.
#
# Not the commit FusionPBX and FS PBX pin: theirs is spandsp 3.0.0, and
# FreeSWITCH 1.11.3's configure requires >= 3.1.1 — it stops with "no usable
# spandsp" against the older one. 3.1.1 is on spandsp's master branch; this is
# the commit that set that version.
ARG SPANDSP_COMMIT=8f1e1646bdec99eac5fd2cd92c35563f736b9b89
RUN set -eux; \
    cd /usr/src \
    && git clone https://github.com/freeswitch/spandsp.git spandsp \
    && cd spandsp \
    && git reset --hard "$SPANDSP_COMMIT" \
    && sh autogen.sh \
    && ./configure CFLAGS="-g -ggdb" --with-pic \
    && make -j$(nproc) \
    && make install \
    && ldconfig

# FreeSWITCH itself. The release tarball keeps its unusual `.-release` infix.
ARG FREESWITCH_VERSION=1.11.3
RUN set -eux; \
    cd /usr/src \
    && wget "https://files.freeswitch.org/releases/freeswitch/freeswitch-${FREESWITCH_VERSION}.-release.tar.gz" \
    && tar -zxf "freeswitch-${FREESWITCH_VERSION}.-release.tar.gz" \
    && cd "freeswitch-${FREESWITCH_VERSION}.-release" \
    && sed -i modules.conf -e s:'applications/mod_signalwire:#applications/mod_signalwire:' \
    && sed -i modules.conf -e s:'endpoints/mod_skinny:#endpoints/mod_skinny:' \
    && sed -i modules.conf -e s:'endpoints/mod_verto:#endpoints/mod_verto:' \
    && sed -i modules.conf -e s:'#applications/mod_callcenter:applications/mod_callcenter:' \
    && sed -i modules.conf -e s:'#formats/mod_shout:formats/mod_shout:' \
    && sed -i modules.conf -e s:'#applications/mod_curl:applications/mod_curl:' \
    && sed -i modules.conf -e s:'#xml_int/mod_xml_curl:xml_int/mod_xml_curl:' \
    && sed -i modules.conf -e s:'#xml_int/mod_xml_cdr:xml_int/mod_xml_cdr:' \
    # The call detail spool and the recorder are load-bearing here, so the build
    # fails rather than quietly producing an image without them.
    && grep -qx 'xml_int/mod_xml_cdr' modules.conf \
    && grep -qx 'formats/mod_sndfile' modules.conf \
    && ./configure CFLAGS="-g -ggdb" --with-pic \
       --with-openssl --enable-core-pgsql-support \
    && make -j$(nproc) \
    && make install \
    && make cd-sounds-install \
    && make cd-moh-install

##############################################
# Stage 2: Runtime image
##############################################
FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

# Runtime shared libraries. The ABI-versioned names are bookworm's, which differ
# from bullseye's throughout (libssl3, libtiff6, libvpx7, and the ffmpeg 5.x
# set). A name that does not exist fails the build here rather than producing an
# image that cannot start.
#
# odbc-postgresql provides the driver the entrypoint checks for before starting
# FreeSWITCH; unixodbc alone does not pull it in.
RUN set -eux; \
    apt-get update && apt-get install -y --no-install-recommends \
    libuuid1 zlib1g libjpeg62-turbo libsqlite3-0 libcurl4 libpcre2-8-0 \
    libspeexdsp1 libspeex1 libldns3 libedit2 libtiff6 libopus0 libsndfile1 \
    libavformat59 libavcodec59 libavutil57 libswscale6 libswresample4 \
    liblua5.2-0 libpq5 unixodbc odbc-postgresql libxml2 libssl3 \
    libncurses6 libmpg123-0 libmp3lame0 libshout3 libmemcached11 \
    libvpx7 libuv1 \
    gettext-base \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Ensure linker finds /usr/local/lib
ENV LD_LIBRARY_PATH=/usr/local/lib

# Copy built FreeSWITCH and libraries from builder
COPY --from=builder /usr/local/freeswitch /usr/local/freeswitch
COPY --from=builder /usr/local/lib/ /usr/local/lib/

# Create unprivileged freeswitch user, link binaries, refresh ldconfig
RUN set -eux; \
    groupadd -r freeswitch && useradd -r -g freeswitch -d /usr/local/freeswitch -s /usr/sbin/nologin freeswitch \
    && ln -sf /usr/local/freeswitch/bin/freeswitch /usr/sbin/freeswitch \
    && ln -sf /usr/local/freeswitch/bin/fs_cli /usr/bin/fs_cli \
    && ln -sf /usr/local/freeswitch/conf /etc/freeswitch \
    && chown -R freeswitch:freeswitch /usr/local/freeswitch \
    && ldconfig

# Copy custom configuration (overrides defaults)
COPY --chown=freeswitch:freeswitch docker/freeswitch/conf /usr/local/freeswitch/conf

# Copy custom scripts
COPY docker/freeswitch/scripts /usr/local/freeswitch/scripts
RUN chown -R freeswitch:freeswitch /usr/local/freeswitch/scripts

# Copy and wire up entrypoint (runs envsubst then execs freeswitch)
COPY docker/freeswitch/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Log directory.
RUN mkdir -p /var/log/freeswitch /var/lib/freeswitch \
    && chown -R freeswitch:freeswitch /var/log/freeswitch /var/lib/freeswitch

# The recordings tree is shared with the application, which runs as a different
# user: the application creates the per-organization and per-date directory and
# FreeSWITCH writes the audio into it. They need a group in common, so both
# images create one at a fixed GID and put their own user in it. The two images
# come from different distributions and neither's own user IDs line up, which is
# why this is an explicit shared group rather than a matching UID.
#
# Docker seeds a fresh named volume from whatever the image has at the mount
# point, so the ownership and mode have to be set here — a volume with no image
# content behind it is created root-owned, and neither side can write.
#
# 2775 is group-writable and setgid, so the directories the application creates
# inherit this group and stay writable by FreeSWITCH.
ARG RECORDINGS_GID=2000
RUN set -eux; \
    groupadd -g "${RECORDINGS_GID}" recordings; \
    usermod -aG recordings freeswitch; \
    mkdir -p /var/lib/freeswitch/recordings; \
    chown freeswitch:recordings /var/lib/freeswitch/recordings; \
    chmod 2775 /var/lib/freeswitch/recordings

# Drop to unprivileged user
USER freeswitch

EXPOSE 5060/tcp 5060/udp 5080/tcp 5080/udp 7443/tcp 8021/tcp 16384-16484/udp

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["-nonat", "-nf"]
