##############################################
# Stage 1: Build FreeSWITCH and dependencies
# Adapted from FusionPBX source-release.sh
##############################################
FROM debian:bullseye AS builder

ENV DEBIAN_FRONTEND=noninteractive

# Install build dependencies (mirrored from FusionPBX source-release.sh)
RUN set -eux; \
    apt-get update && apt-get install -y \
    autoconf automake devscripts g++ git-core libncurses5-dev libtool make libjpeg-dev \
    pkg-config flac libgdbm-dev libdb-dev gettext equivs dpkg-dev libpq-dev \
    liblua5.2-dev libtiff5-dev libcurl4-openssl-dev libsqlite3-dev libpcre3-dev \
    libspeexdsp-dev libspeex-dev libldns-dev libedit-dev libopus-dev libmemcached-dev \
    libshout3-dev libmpg123-dev libmp3lame-dev yasm nasm libsndfile1-dev libuv1-dev libvpx-dev \
    libavformat-dev libswscale-dev libvlc-dev \
    cmake uuid-dev libssl-dev lsb-release wget ca-certificates \
    python3-distutils mlocate libvpx6 swig4.0 sqlite3 unzip \
    && rm -rf /var/lib/apt/lists/*

# Build libks (full clone — FusionPBX approach, no tags, no shallow)
RUN set -eux; \
    cd /usr/src \
    && git clone https://github.com/signalwire/libks.git libks \
    && cd libks \
    && cmake . \
    && make -j$(nproc) \
    && make install \
    && ldconfig

# Build sofia-sip (pinned tag via zip download)
RUN set -eux; \
    cd /usr/src \
    && wget https://github.com/freeswitch/sofia-sip/archive/refs/tags/v1.13.17.zip \
    && unzip v1.13.17.zip \
    && cd sofia-sip-1.13.17 \
    && sh autogen.sh \
    && ./configure CFLAGS="-g -ggdb" --with-pic --with-glib=no --without-doxygen --disable-stun \
    && make -j$(nproc) \
    && make install \
    && ldconfig

# Build spandsp (pinned to FusionPBX's known-good commit)
RUN set -eux; \
    cd /usr/src \
    && git clone https://github.com/freeswitch/spandsp.git spandsp \
    && cd spandsp \
    && git reset --hard 0d2e6ac65e0e8f53d652665a743015a88bf048d4 \
    && sh autogen.sh \
    && ./configure CFLAGS="-g -ggdb" --with-pic \
    && make -j$(nproc) \
    && make install \
    && ldconfig

# Build FreeSWITCH 1.10.12 from source
RUN set -eux; \
    cd /usr/src \
    && wget https://files.freeswitch.org/releases/freeswitch/freeswitch-1.10.12.-release.tar.gz \
    && ls -la freeswitch-1.10.12.-release.tar.gz \
    && tar -zxf freeswitch-1.10.12.-release.tar.gz \
    && ls -la \
    && cd freeswitch-1.10.12.-release \
    && sed -i modules.conf -e s:'applications/mod_signalwire:#applications/mod_signalwire:' \
    && sed -i modules.conf -e s:'endpoints/mod_skinny:#endpoints/mod_skinny:' \
    && sed -i modules.conf -e s:'endpoints/mod_verto:#endpoints/mod_verto:' \
    && sed -i modules.conf -e s:'#applications/mod_callcenter:applications/mod_callcenter:' \
    && sed -i modules.conf -e s:'#formats/mod_shout:formats/mod_shout:' \
    && ./configure CFLAGS="-g -ggdb" --with-pic \
       --with-openssl --enable-core-pgsql-support \
    && make -j$(nproc) \
    && make install \
    && make cd-sounds-install \
    && make cd-moh-install

##############################################
# Stage 2: Runtime image
##############################################
FROM debian:bullseye-slim

ENV DEBIAN_FRONTEND=noninteractive

# Install runtime dependencies
RUN set -eux; \
    apt-get update && apt-get install -y --no-install-recommends \
    libuuid1 zlib1g libjpeg62-turbo libsqlite3-0 libcurl4 libpcre3 \
    libspeexdsp1 libspeex1 libldns3 libedit2 libtiff5 libopus0 libsndfile1 \
    libavformat58 libavcodec58 libavutil56 libswscale5 libswresample3 \
    liblua5.2-0 libpq5 unixodbc libxml2 libssl1.1 \
    libncurses6 libmpg123-0 libmp3lame0 libshout3 libmemcached11 \
    libvpx6 libuv1 \
    ca-certificates \
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
COPY docker/freeswitch/conf /etc/freeswitch
RUN chown -R freeswitch:freeswitch /etc/freeswitch

# Drop to unprivileged user
USER freeswitch

EXPOSE 5060/tcp 5060/udp 5080/tcp 5080/udp 8021/tcp 16384-16484/udp

CMD ["freeswitch", "-nonat", "-nf"]
