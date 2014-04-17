#!/bin/sh
#
cd /opt/exabgp/sbin

env exabgp.daemon.daemonize=true \
 exabgp.daemon.pid=/opt/gixlg/exabgp/exabgp.pid \
 exabgp.daemon.user=root \
 exabgp.tcp.bind="" \
 exabgp.tcp.port="179" \
 exabgp.log.enable=true \
 exabgp.log.all=false \
 exabgp.log.destination=/opt/gixlg/exabgp/exabgp.log \
 exabgp.cache.attributes=false \
 exabgp.cache.nexthops=false \
 ./exabgp /opt/gixlg/exabgp/exabgp.conf
