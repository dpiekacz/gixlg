#!/usr/bin/python

import socket

def iptoint (ip):
  packed = socket.inet_pton(version['.' in ip], ip)
  value = 0L
  for byte in packed:
    value <<= 8
    value += ord(byte)
  return value

version = {
  True : socket.AF_INET,
  False : socket.AF_INET6,
}
        
nexthops = { }
nexthops[1] = {'ipv4':'62.179.20.0/30', 'ipv6':'2001:730:27ff:500::3eb3:1402/64', 'node':'UPC', 'type':'Transit', 'location':'Zurich'}
nexthops[2] = {'ipv4':'212.25.27.188/30', 'ipv6':'2a00:c38::/64', 'node':'NTS', 'type':'Transit', 'location':'Zurich'}
nexthops[3] = {'ipv4':'149.6.177.44/30', 'ipv6':'2001:978:2:3::1a:0/112', 'node':'Cogent', 'type':'Transit', 'location':'Glattbrugg'}
nexthops[5] = {'ipv4':'212.25.27.44/32', 'ipv6':'2001:8e0:0:ffff::9/128', 'node':'ghayda.glb', 'type':'Core', 'location':'Glattbrugg'}
nexthops[6] = {'ipv4':'212.25.27.39/32', 'ipv6':'2001:8e0:0:ffff::2/128', 'node':'zelja.zh', 'type':'Core', 'location':'Zurich'}
nexthops[7] = {'ipv4':'62.192.25.214/30', 'ipv6':'2001:920:0:1::150/127', 'node':'Colt', 'type':'Transit', 'location':'Zurich'}
nexthops[8] = {'ipv4':'212.25.27.43/32', 'ipv6':'2001:8e0:0:ffff::43/128', 'node':'zaina.zh', 'type':'Core', 'location':'Zurich'}
nexthops[9] = {'ipv4':'212.25.27.34/32', 'ipv6':'2001:8e0:0:ffff::34/128', 'node':'gauri.glb', 'type':'Core', 'location':'Glattbrugg'}

for i in nexthops:
  prefix_tmp = nexthops[i]['ipv4']
  x = prefix_tmp.find("/")
  subnet = int(prefix_tmp[x+1:])
  ip_start = iptoint(prefix_tmp[:x])
  ip_end = ip_start + (2**(32-subnet)) - 1

  prefix_tmp = nexthops[i]['ipv6']
  x = prefix_tmp.find("/")
  subnet = int(prefix_tmp[x+1:])
  ip6_start = iptoint(prefix_tmp[:x])
  ip6_end = ip6_start + (2**(128-subnet)) - 1

  print "INSERT INTO `gixlg`.`nexthops` (`ip4_start`, `ip4_end`, `ip6_start`, `ip6_end`, `ip4_net`, `ip6_net`, `node`, `type`, `location`) VALUES (",ip_start,",",ip_end,",",ip6_start,",",ip6_end,",'"+nexthops[i]['ipv4']+"','"+nexthops[i]['ipv6']+"','"+nexthops[i]['node']+"','"+nexthops[i]['type']+"','"+nexthops[i]['location']+"');"
