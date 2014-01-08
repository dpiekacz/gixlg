#!/usr/bin/env python
"""
Created by Daniel Piekacz on 2012-01-14.
Last update on 2013-05-03.
Copyright (c) 2013 Daniel Piekacz. All rights reserved.
Copyright (c) 2013 Thomas Mangin. All rights reserved.
Project website: gix.net.pl, e-mail: daniel@piekacz.tel
"""

import sys
import os
import time
import syslog
import string
import socket
import MySQLdb

version = {
    True : socket.AF_INET,
    False : socket.AF_INET6,
}

neighbor_state = { }

def iptoint (ip):
    packed = socket.inet_pton(version['.' in ip], ip)
    value = 0L
    for byte in packed:
	value <<= 8
	value += ord(byte)
    return value

def prefixes ():
	line = ''

	# When the parent dies we are seeing continual newlines, so we only access so many before stopping
	counter = 0

	# currently supported prefix keys
	prefix_keys = ['neighbor','local-ip','family-allowed','route','label','next-hop','med','local-preference','community','extended-community','origin','as-path','aggregator']

	while True:
		try:
			# As any Keyboard Interrupt will force us back here, we are only reading line if we could yield the prefix to the parent un-interrupted.
			if not line:
				line = sys.stdin.readline().strip()
			if line == "":
				counter += 1
				if counter > 100:
					raise StopIteration()
				continue
			counter = 0

			if line == 'shutdown':
				raise StopIteration()

			prefix = dict(zip(prefix_keys,['',]*len(prefix_keys)))
			prefix['time'] = msgtime = time.strftime('%Y-%m-%d %H:%M:%S',time.localtime())

			tokens = line.split(' ')
			#syslog.syslog(syslog.LOG_ALERT, '%s' % (line))

			while len(tokens) >= 2:
				key = tokens.pop(0)
				if key in ('announced','withdrawn'):
					prefix['state'] = key
					key = tokens.pop(0)
					value = tokens.pop(0)
				elif key == 'down':
					prefix['state'] = 'down'
					break
				elif key == 'atomic-aggregate':
					prefix['atomic-aggregate'] = key
					continue
				elif key == 'update':
					value = tokens.pop(0)
					prefix['state'] = key + " " + value
					continue
				elif key in prefix_keys:
					value = tokens.pop(0)
				else:
					syslog.syslog(syslog.LOG_ALERT, 'unknown prefix attributes %s' % (key))

				if value == '[':
					values = []
					while tokens:
						value = tokens.pop(0)
						if value == '(':
							value = tokens.pop(0)
							while value != ')':
								value = tokens.pop(0)
							continue
							value = tokens.pop(0)
						if value == ']': break
						values.append(value)
					if value != ']':
						syslog.syslog(syslog.LOG_ALERT, 'problem with parsing the values of attribute %s' % (key))
						line = ''
						continue
					value = ' '.join(values)

				if value == '(':
					values = []
					while tokens:
						value = tokens.pop(0)
						if value == ')': break
						values.append(value)
					if value != ')':
						syslog.syslog(syslog.LOG_ALERT, 'problem with parsing the values of attribute %s' % (key))
						line = ''
						continue
					value = ' '.join(values)

				prefix[key] = value

			if tokens:
				key = tokens.pop(0)
				if key in ('up','connected'):
					prefix['state'] = key
			yield prefix
#			syslog.syslog(syslog.LOG_ALERT, '%s' % (prefix))
			line = ''
		except KeyboardInterrupt:
			pass

def tosql (mydb,cursor,prefix):
	try:
		mydb.ping(True)

		neighbor_tmp = prefix['neighbor'].split('-')
		if len(neighbor_tmp) == 2:
			neighbor = neighbor_tmp[1]
		else:
			neighbor = neighbor_tmp[0]

		if prefix['state'] == "up":
			neighbor_state[(neighbor)] = "up"
			cursor.execute ("DELETE FROM prefixes WHERE (neighbor = %s)", (neighbor))
			cursor.execute ("UPDATE members SET time='0000-00-00 00:00:00',prefixes=0,status=1,updown=updown+1,lastup=%s WHERE neighbor=%s", (prefix['time'], neighbor))
			return

		if prefix['state'] == "down":
			if neighbor_state.get(neighbor) == "up":
				neighbor_state[(neighbor)] = "down"
				cursor.execute ("DELETE FROM prefixes WHERE (neighbor = %s)", (neighbor))
				cursor.execute ("UPDATE members SET time='0000-00-00 00:00:00',prefixes=0,status=0,updown=updown+1,lastdown=%s WHERE neighbor=%s", (prefix['time'], neighbor))
			return

		if prefix['state'] == "connected":
			return

		if prefix['state'] == "update start":
			cursor.execute ("START TRANSACTION")
			return

		if prefix['state'] == "update end":
			cursor.execute ("COMMIT")
			return

		if prefix['state'] == "announced":
			prefix_tmp = prefix['route']
			x = prefix_tmp.find("/")
			subnet = int(prefix_tmp[x+1:])
			ip_start = iptoint(prefix_tmp[:x])
			prefix['originas'] = int(prefix['as-path'].split(' ')[-1])

			if "." in prefix_tmp:
				ip_type = 4
				ip_end = ip_start + (2**(32-subnet)) - 1
			else:
				ip_type = 6
				ip_end = ip_start + (2**(128-subnet)) - 1

			poly = 'GEOMFROMWKB(POLYGON(LINESTRING(POINT({0}, -1), POINT({1}, -1), POINT({2}, 1), POINT({3}, 1), POINT({4}, -1))))'.format(ip_start, ip_end, ip_end, ip_start, ip_start)

			if (((ip_type == 4) and (subnet >= 8) and (subnet <= 24)) or ((ip_type == 6) and (subnet >= 16) and (subnet <= 48))):
				cursor.execute ("""\
				INSERT INTO iptoasn 
				(
					prefix,
					type,
					length,
					ip_poly,
					originas
				) VALUES 
				('%s',%s,'%s',%s,%s) ON DUPLICATE KEY UPDATE originas=%s""" %
				(
					prefix['route'],
					ip_type,
					subnet,
					poly,
					prefix['originas'],
					prefix['originas']
				))

			cursor.execute ("SELECT '' FROM prefixes WHERE ((neighbor=%s) && (prefix=%s))", (neighbor, prefix['route']))
			if cursor.rowcount == 0:
				cursor.execute ("""\
				INSERT INTO prefixes 
				(
					neighbor,
					type,
					prefix,
					length,
					ip_start,
					ip_end,
					ip_poly,
					aspath,
					nexthop,
					community,
					extended_community,
					origin,
					originas,
					time
				) VALUES 
				('%s',%s,'%s',%s,%s,%s,%s,'%s','%s','%s','%s','%s',%s,'%s')""" %
				(
					neighbor,
					ip_type,
					prefix['route'],
					subnet,
					ip_start,
					ip_end,
					poly,
					prefix['as-path'],
					prefix['next-hop'],
					prefix['community'],
					prefix['extended-community'],
					prefix['origin'],
					prefix['originas'],
					prefix['time']
				))
				cursor.execute ("UPDATE members SET prefixes=prefixes+1,time=%s WHERE neighbor=%s", (prefix['time'], neighbor))
			else:
				cursor.execute ("UPDATE prefixes SET aspath=%s,nexthop=%s,community=%s,extended_community=%s,origin=%s,originas=%s,time=%s WHERE ((neighbor=%s) && (prefix=%s))", (prefix['as-path'], prefix['next-hop'], prefix['community'], prefix['extended-community'], prefix['origin'], prefix['originas'], prefix['time'], neighbor, prefix['route']))
				cursor.execute ("UPDATE members SET time=%s WHERE neighbor=%s", (prefix['time'], neighbor))
			return

		if prefix['state'] == "withdrawn":
			cursor.execute ("SELECT '' FROM prefixes WHERE ((neighbor=%s) && (prefix=%s))", (neighbor, prefix['route']))
			if cursor.rowcount == 0:
				cursor.execute ("UPDATE members SET time=%s WHERE neighbor=%s", (prefix['time'], neighbor))
			else:
				cursor.execute ("DELETE FROM prefixes WHERE ((neighbor = %s) && (prefix = %s))", (neighbor, prefix['route']))
				cursor.execute ("UPDATE members SET prefixes=prefixes-1,time=%s WHERE neighbor=%s", (prefix['time'], neighbor))
			return

		syslog.syslog(syslog.LOG_ALERT, 'unparsed prefix %s' % (str(prefix)))
	except KeyboardInterrupt:
		tosql(mydb,cursor,prefix)


def main ():
	syslog.openlog("ExaBGP")

	host = ""
	database = "gixlg"
	user = "gixlg"
	password = "gixlg"

#	host = sys.argv[1]
#	database = sys.argv[2]
#	user = sys.argv[3]
#	password = sys.argv[4]

	try:
		mydb = MySQLdb.connect (host = host, db = database, user = user, passwd = password, unix_socket='/tmp/mysqld.sock', connect_timeout = 0)
		cursor = mydb.cursor ()
	except MySQLdb.Error, e:
		syslog.syslog("error %d: %s" % (e.args[0], e.args[1]))
		sys.exit(1)

	running = True

	while running:
		try:
			for prefix in prefixes():
				tosql(mydb,cursor,prefix)
			running = False
		except KeyboardInterrupt:
			pass
		except MySQLdb.Error, e:
			syslog.syslog("error %d: %s" % (e.args[0], e.args[1]))
			sys.exit (1)

	try:
		cursor.close ()
		mydb.close ()
	except MySQLdb.Error, e:
		pass

if __name__ == '__main__':
	if len(sys.argv) == 5:
		main ()
	else:
		print "%s <host> <database> <user> <password>" % sys.argv[0]
		sys.exit(1)
