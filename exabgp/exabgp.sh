#!/bin/sh
#
cd /opt/exabgp/sbin

env exabgp.daemon.daemonize=true \
 exabgp.daemon.user=exabgp \
 exabgp.log.enable=true \
 exabgp.log.all=false \
 ./exabgp /opt/gixlg/exabgp/exabgp.conf
