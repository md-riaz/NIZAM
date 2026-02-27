FROM debian:bookworm-slim

# Install FreeSWITCH from official repo
RUN apt-get update && apt-get install -y --no-install-recommends \
    gnupg2 wget ca-certificates lsb-release \
    && wget --quiet -O /usr/share/keyrings/signalwire-freeswitch-repo.gpg https://freeswitch.signalwire.com/repo/deb/debian-release/signalwire-freeswitch-repo.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/signalwire-freeswitch-repo.gpg] https://freeswitch.signalwire.com/repo/deb/debian-release/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/freeswitch.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
    freeswitch \
    freeswitch-mod-commands \
    freeswitch-mod-console \
    freeswitch-mod-dptools \
    freeswitch-mod-dialplan-xml \
    freeswitch-mod-event-socket \
    freeswitch-mod-sofia \
    freeswitch-mod-xml-curl \
    freeswitch-mod-loopback \
    freeswitch-mod-voicemail \
    freeswitch-mod-conference \
    freeswitch-mod-sndfile \
    freeswitch-mod-tone-stream \
    freeswitch-mod-local-stream \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy custom configuration
COPY docker/freeswitch/conf /etc/freeswitch

EXPOSE 5060/tcp 5060/udp 5080/tcp 5080/udp 8021/tcp 16384-16484/udp

CMD ["freeswitch", "-nonat", "-nc"]
