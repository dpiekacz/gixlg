#!/usr/bin/env python
"""
Created by Daniel Piekacz on 2012-01-14.
Last update on 2014-02-16.
Copyright (c) 2014 Daniel Piekacz. All rights reserved.
Project website: gix.net.pl, e-mail: daniel@piekacz.tel
"""
import os, sys, time, socket
import json
import MySQLdb
from threading import Thread, RLock

config = {}
config["mysql_host"] = ""
config["mysql_db"] = "gixlg"
config["mysql_user"] = "gixlg"
config["mysql_pass"] = "gixlg"
config["mysql_sock"] = "/tmp/mysqld.sock"
config["mysql_timeout"] = 0
config["mysql_ping"] = True

config["http_enable"] = False
config["http_ip"] = "192.0.2.201"
config["http_port"] = 10001

config["stats_delayed"] = False
config["stats_refresh"] = 2

config["ip2asn"] = False

config["log_file"] = "/tmp/collector.txt"
config["debug"] = False

##
## main code - do not modify the code below the line
##
Running = False

# status | time | lastup | lastdown | prefixes | updown
neighbors = {}

version = {
	True : socket.AF_INET,
	False : socket.AF_INET6,
}

def iptoint(ip):
	packed = socket.inet_pton(version['.' in ip], ip)
	value = 0L
	for byte in packed:
		value <<= 8
		value += ord(byte)
	return value

def NeighborsStatsWorker():
	global Running

	if config["debug"]:
		sys.stdout.write("GIX: stats / start\n")
	while Running:
		try:
			with lock:
				for i in neighbors.keys():
					cursor.execute("UPDATE members SET status=%s,time=%s,lastup=%s,lastdown=%s,prefixes=%s,updown=%s WHERE neighbor=%s", (neighbors[i][0], neighbors[i][1], neighbors[i][2], neighbors[i][3], neighbors[i][4], neighbors[i][5]), i)
			time.sleep(config["stats_refresh"])

		except MySQLdb.Error, e:
			if config["debug"]:
				sys.stdout.write("GIX: stats / MySQLdb exception" + e.args[0] + " - " + e.args[1] + "\n")
				sys.stdout.write(str(prefix) + "\n\n\n")
			Running = False
			os._exit(1)

		except:
			if config["debug"]:
				sys.stdout.write("GIX: stats / exception\n")
			Running = False
			os._exit(1)

def GIXcollector():
	global Running

	if config["debug"]:
		sys.stdout.write("GIX: collector / start\n")
	while Running:
		try:
			line = sys.stdin.readline().strip()
			if line == "":
				counter += 1
				if counter > 1000:
					break
				continue
			counter = 0

			if config["debug"]:
				sys.stdout.write("EXABGP: " + line + "\n")

			prefix_json = json.loads(line)
			prefix_keys = prefix_json.keys()

			prefix = {}
			prefix["time"] = time.strftime("%Y-%m-%d %H:%M:%S",time.localtime())

			# check update received from exabgp
			if "exabgp" in prefix_keys:

				# check if this is neighbor status change or prefix update
				if "neighbor" in prefix_keys:
					prefix_neighbor_keys = prefix_json["neighbor"].keys()

					# neighbor status change: connected, up or down
					if "state" in prefix_neighbor_keys and "ip" in prefix_neighbor_keys:
						prefix["state"] = prefix_json["neighbor"]["state"]
						prefix["neighbor"] = prefix_json["neighbor"]["ip"]

					# prefix update
					if "update" in prefix_neighbor_keys and "ip" in prefix_neighbor_keys:
						prefix["neighbor"] = prefix_json["neighbor"]["ip"]
						prefix_neighbor_update_keys = prefix_json["neighbor"]["update"].keys()

						if "." in prefix["neighbor"]:
							prefix["ip_type"] = 4
						else:
							prefix["ip_type"] = 6

						prefix["route"] = {}
						prefix["subnet"] = {}
						prefix["ip_start"] = {}
						prefix["ip_end"] = {}
						prefix["poly"] = {}

						# prefix withdrawal
						if "withdraw" in prefix_neighbor_update_keys:
							prefix["state"] = "withdraw"
							prefix_neighbor_update_withdraw_keys = prefix_json["neighbor"]["update"]["withdraw"].keys()
							family = prefix_neighbor_update_withdraw_keys[0]

							i = 0
							for route in prefix_json["neighbor"]["update"]["withdraw"][family].keys():
								prefix["route"][i] = route
								i += 1

						# prefix announcement
						elif "announce" in prefix_neighbor_update_keys:
							prefix["state"] = "announce"
							prefix_neighbor_update_announce_keys = prefix_json["neighbor"]["update"]["announce"].keys()
							prefix["next-hop"] = prefix_neighbor_update_announce_keys[0]

							i = 0
							for route in prefix_json["neighbor"]["update"]["announce"][prefix["next-hop"]].keys():
								prefix["route"][i] = route

								x = route.find("/")

								prefix["subnet"][i] = int(route[x+1:])
								prefix["ip_start"][i] = iptoint(route[:x])

								if prefix["ip_type"] == 4:
									prefix["ip_end"][i] = prefix["ip_start"][i] + (2**(32 - prefix["subnet"][i])) - 1
								else:
									prefix["ip_end"][i] = prefix["ip_start"][i] + (2**(128 - prefix["subnet"][i])) - 1
								prefix["poly"][i] = "GEOMFROMWKB(POLYGON(LINESTRING(POINT({0}, -1), POINT({1}, -1), POINT({2}, 1), POINT({3}, 1), POINT({4}, -1))))".format(prefix["ip_start"][i], prefix["ip_end"][i], prefix["ip_end"][i], prefix["ip_start"][i], prefix["ip_start"][i])

								i += 1

							if "attribute" in prefix_neighbor_update_keys:
								prefix_neighbor_update_attribute_keys = prefix_json["neighbor"]["update"]["attribute"].keys()

								if "origin" in prefix_neighbor_update_attribute_keys:
									prefix["origin"] = prefix_json["neighbor"]["update"]["attribute"]["origin"]
								else:
									prefix["origin"] = ""

								if "atomic-aggregate" in prefix_neighbor_update_attribute_keys:
									prefix["atomic-aggregate"] = prefix_json["neighbor"]["update"]["attribute"]["atomic-aggregate"]
								else:
									prefix["atomic-aggregate"] = ""

								if "aggregator" in prefix_neighbor_update_attribute_keys:
									prefix["aggregator"] = prefix_json["neighbor"]["update"]["attribute"]["aggregator"]
								else:
									prefix["aggregator"] = ""

								if "community" in prefix_neighbor_update_attribute_keys:
									community_tmp = prefix_json["neighbor"]["update"]["attribute"]["community"]
									prefix["community"] = ""

									for i in range(0, len(community_tmp)):
										prefix["community"] += str(community_tmp[i][0]) + ":" + str(community_tmp[i][1])

										if i < (len(community_tmp) - 1):
											prefix["community"] += " "
								else:
									prefix["community"] = ""

								if "extended-community" in prefix_neighbor_update_attribute_keys:
									extended_community_tmp = prefix_json["neighbor"]["update"]["attribute"]["extended-community"]
									prefix["extended-community"] = ""

									for i in range(0, len(extended_community_tmp)):
										if (extended_community_tmp[i][0] == 0x03) or (extended_community_tmp[i][0] == 0x43):
											prefix["extended-community"] += "rte-type:"
											prefix["extended-community"] += str(extended_community_tmp[i][2]) + "." + str(extended_community_tmp[i][3]) + "." + str(extended_community_tmp[i][4]) + "." + str(extended_community_tmp[i][5])
											prefix["extended-community"] += ":" + str(extended_community_tmp[i][6]) + ":" + str(extended_community_tmp[i][7])

										elif ((extended_community_tmp[i][0] == 0x00) or (extended_community_tmp[i][0] == 0x01) or (extended_community_tmp[i][0] == 0x02)) and (extended_community_tmp[i][1] == 0x02):
											prefix["extended-community"] += "target:"
											if (extended_community_tmp[i][0] == 0x00):
												prefix["extended-community"] += str(extended_community_tmp[i][2] * 256 + extended_community_tmp[i][3])
												prefix["extended-community"] += ":" + str(extended_community_tmp[i][4] * 16777216 + extended_community_tmp[i][5] * 65536 + extended_community_tmp[i][6] * 256 + extended_community_tmp[i][7])
											elif (extended_community_tmp[i][0] == 0x01):
												prefix["extended-community"] += str(extended_community_tmp[i][2]) + "." + str(extended_community_tmp[i][3]) + "." + str(extended_community_tmp[i][4]) + "." + str(extended_community_tmp[i][5])
												prefix["extended-community"] += ":" + str(extended_community_tmp[i][6] * 256 + extended_community_tmp[i][7])
											else:
												prefix["extended-community"] += str(extended_community_tmp[i][2] * 16777216 + extended_community_tmp[i][3] * 65536 + extended_community_tmp[i][4] * 256 + extended_community_tmp[i][5]) + "L"
												prefix["extended-community"] += ":" + str(extended_community_tmp[i][6] * 256 + extended_community_tmp[i][7])

										elif ((extended_community_tmp[i][0] == 0x00) or (extended_community_tmp[i][0] == 0x01) or (extended_community_tmp[i][0] == 0x02)) and (extended_community_tmp[i][1] == 0x03):
											prefix["extended-community"] += "origin:"
											if (extended_community_tmp[i][0] == 0x00):
												prefix["extended-community"] += str(extended_community_tmp[i][2] * 256 + extended_community_tmp[i][3])
												prefix["extended-community"] += ":" + str(extended_community_tmp[i][4] * 16777216 + extended_community_tmp[i][5] * 65536 + extended_community_tmp[i][6] * 256 + extended_community_tmp[i][7])
											elif (extended_community_tmp[i][0] == 0x01):
												prefix["extended-community"] += str(extended_community_tmp[i][2]) + "." + str(extended_community_tmp[i][3]) + "." + str(extended_community_tmp[i][4]) + "." + str(extended_community_tmp[i][5])
												prefix["extended-community"] += ":" + str(extended_community_tmp[i][6] * 256 + extended_community_tmp[i][7])
											else:
												prefix["extended-community"] += str(extended_community_tmp[i][2] * 16777216 + extended_community_tmp[i][3] * 65536 + extended_community_tmp[i][4] * 256 + extended_community_tmp[i][5]) + "L"
												prefix["extended-community"] += ":" + str(extended_community_tmp[i][6] * 256 + extended_community_tmp[i][7])

										if i < (len(extended_community_tmp) - 1):
											prefix["extended-community"] += " "
								else:
									prefix["extended-community"] = ""

								if "as-path" in prefix_neighbor_update_attribute_keys:
									as_path_tmp = prefix_json["neighbor"]["update"]["attribute"]["as-path"]
									prefix["as-path"] = ""

									for i in range(0, len(as_path_tmp)):
										prefix["as-path"] += str(as_path_tmp[i])

										if i < (len(as_path_tmp) - 1):
											prefix["as-path"] += " "
										else:
											prefix["originas"] = str(as_path_tmp[i])
								else:
									prefix["as-path"] = ""

								if "as-set" in prefix_neighbor_update_attribute_keys:
									as_set_tmp = prefix_json["neighbor"]["update"]["attribute"]["as-set"]
									prefix["as-set"] = ""

									for i in range(0, len(as_set_tmp)):
										prefix["as-set"] += str(as_set_tmp[i])

										if i < (len(as_set_tmp) - 1):
											prefix["as-set"] += " "
								else:
									prefix["as-set"] = ""

								if "med" in prefix_neighbor_update_attribute_keys:
									prefix["med"] = prefix_json["neighbor"]["update"]["attribute"]["med"]
								else:
									prefix["med"] = ""
						else:
							# if not announce and not withdraw then state is unknown
							prefix["state"] = "unknown"
				# process shutdown notification
				elif "notification" in prefix_keys:
					prefix["state"] = "shutdown"
				# if not neighbor update and not exabgp notification then state is unknown
				else:
					prefix["state"] = "unknown"
			# unknown state received
			else:
				prefix["state"] = "unknown"

			if config["mysql_ping"]:
				mydb.ping(True)

			neighbor = prefix["neighbor"]
			if prefix["state"] == "connected":
				if config["debug"]:
					sys.stdout.write("GIX: " + prefix["state"] + ", neighbor: " + neighbor + "\n")

				if config["stats_delayed"]:
					with lock:
						if neighbor not in neighbors.keys():
							neighbors[neighbor] = [0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, 0]
						else:
							neighbors[neighbor] = [0, '0000-00-00 00:00:00', neighbors[neighbor][2], neighbors[neighbor][3], 0, neighbors[neighbor][5]]

			elif prefix["state"] == "up":
				if config["debug"]:
					sys.stdout.write("GIX: " + prefix["state"] + ", neighbor: " + neighbor + "\n")

				cursor.execute("DELETE FROM prefixes WHERE (neighbor = %s)", (neighbor))
				if config["stats_delayed"]:
					with lock:
						if neighbor not in neighbors.keys():
							neighbors[neighbor] = [1, '0000-00-00 00:00:00', prefix["time"], '0000-00-00 00:00:00', 0, 1]
						else:
							neighbors[neighbor] = [1, '0000-00-00 00:00:00', prefix["time"], neighbors[neighbor][3], 0, neighbors[neighbor][5] + 1]
				else:
					cursor.execute("UPDATE members SET time='0000-00-00 00:00:00',prefixes=0,status=1,updown=updown+1,lastup=%s WHERE neighbor=%s", (prefix["time"], neighbor))

			elif prefix["state"] == "down":
				if config["debug"]:
					sys.stdout.write("GIX: " + prefix["state"] + ", neighbor: " + neighbor + "\n")

				cursor.execute("DELETE FROM prefixes WHERE (neighbor = %s)", (neighbor))
				if config["stats_delayed"]:
					with lock:
						if neighbor not in neighbors.keys():
							neighbors[neighbor] = [0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', prefix["time"], 0, 1]
						else:
							neighbors[neighbor] = [0, '0000-00-00 00:00:00', neighbors[neighbor][2], prefix["time"], 0, neighbors[neighbor][5] + 1]
				else:
					cursor.execute("UPDATE members SET time='0000-00-00 00:00:00',prefixes=0,status=0,updown=updown+1,lastdown=%s WHERE neighbor=%s", (prefix["time"], neighbor))

			elif prefix["state"] == "announce":
				if config["debug"]:
					sys.stdout.write("GIX: " + prefix["state"] + ", neighbor: " + neighbor + ", prefixes: ")

				for i in range(0, len(prefix["route"])):
					if config["debug"]:
						sys.stdout.write(prefix["route"][i])
						if i < (len(prefix["route"]) - 1):
							sys.stdout.write(",")

					if config["ip2asn"]:
						if (((prefix["ip_type"] == 4) and (prefix["subnet"][i] >= 8) and (prefix["subnet"][i] <= 24)) or ((prefix["ip_type"] == 6) and (prefix["subnet"][i] >= 16) and (prefix["subnet"][i] <= 48))):
							cursor.execute("""\
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
								prefix["route"][i],
								prefix["ip_type"],
								prefix["subnet"][i],
								prefix["poly"][i],
								prefix["originas"],
								prefix["originas"]
							))

					cursor.execute("SELECT '' FROM prefixes WHERE ((neighbor=%s) && (prefix=%s))", (neighbor, prefix["route"][i]))
					if cursor.rowcount == 0:
						cursor.execute("""\
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
							prefix["ip_type"],
							prefix["route"][i],
							prefix["subnet"][i],
							prefix["ip_start"][i],
							prefix["ip_end"][i],
							prefix["poly"][i],
							prefix["as-path"],
							prefix["next-hop"],
							prefix["community"],
							prefix["extended-community"],
							prefix["origin"],
							prefix["originas"],
							prefix["time"]
						))
						if config["stats_delayed"]:
							with lock:
								neighbors[neighbor] = [neighbors[neighbor][0], prefix["time"], neighbors[neighbor][2], neighbors[neighbor][3], neighbors[neighbor][4] + 1, neighbors[neighbor][5]]
						else:
							cursor.execute("UPDATE members SET prefixes=prefixes+1,time=%s WHERE neighbor=%s", (prefix["time"], neighbor))
					else:
						cursor.execute("UPDATE prefixes SET aspath=%s,nexthop=%s,community=%s,extended_community=%s,origin=%s,originas=%s,time=%s WHERE ((neighbor=%s) && (prefix=%s))", (prefix["as-path"], prefix["next-hop"], prefix["community"], prefix["extended-community"], prefix["origin"], prefix["originas"], prefix["time"], neighbor, prefix["route"][i]))
						if config["stats_delayed"]:
							with lock:
								neighbors[neighbor] = [neighbors[neighbor][0], prefix["time"], neighbors[neighbor][2], neighbors[neighbor][3], neighbors[neighbor][4], neighbors[neighbor][5]]
						else:
							cursor.execute("UPDATE members SET time=%s WHERE neighbor=%s", (prefix["time"], neighbor))

				if config["debug"]:
					sys.stdout.write(", family: inet" + str(prefix["ip_type"]) +
							", origin: " + str(prefix["origin"]) +
							", next-hop: " + str(prefix["next-hop"]) +
							", as-path: " + str(prefix["as-path"]) +
							", originas: " + str(prefix["originas"]) +
							", as-set: " + str(prefix["as-set"]) +
							", community: " + str(prefix["community"]) +
							", extended-community: " + str(prefix["extended-community"]) +
							", med: " + str(prefix["med"]) +
							", aggregator: " + str(prefix["aggregator"]) +
							", atomic-aggregate: " + str(prefix["atomic-aggregate"]))
					sys.stdout.write("\n")

			elif prefix["state"] == "withdraw":
				if config["debug"]:
					sys.stdout.write("GIX: " + prefix["state"] + ", neighbor: " + neighbor + ", prefixes: ")

				for i in range(0, len(prefix["route"])):
					if config["debug"]:
						sys.stdout.write(prefix["route"][i])
						if i < (len(prefix["route"]) - 1):
							sys.stdout.write(",")

					cursor.execute("SELECT '' FROM prefixes WHERE ((neighbor=%s) && (prefix=%s))", (neighbor, prefix["route"][i]))
					if cursor.rowcount == 0:
						if config["stats_delayed"]:
							with lock:
								neighbors[neighbor] = [neighbors[neighbor][0], prefix["time"], neighbors[neighbor][2], neighbors[neighbor][3], neighbors[neighbor][4], neighbors[neighbor][5]]
						else:
							cursor.execute("UPDATE members SET time=%s WHERE neighbor=%s", (prefix["time"], neighbor))
					else:
						cursor.execute("DELETE FROM prefixes WHERE ((neighbor = %s) && (prefix = %s))", (neighbor, prefix["route"][i]))
						if config["stats_delayed"]:
							with lock:
								neighbors[neighbor] = [neighbors[neighbor][0], prefix["time"], neighbors[neighbor][2], neighbors[neighbor][3], neighbors[neighbor][4] - 1, neighbors[neighbor][5]]
						else:
							cursor.execute("UPDATE members SET prefixes=prefixes-1,time=%s WHERE neighbor=%s", (prefix["time"], neighbor))
				if config["debug"]:
					sys.stdout.write("\n")
			elif prefix["state"] == "shutdown":
				if config["debug"]:
					sys.stdout.write("GIX: " + prefix["state"] + ", neighbor: " + neighbor)
			else:
				if config["debug"]:
					sys.stdout.write("GIX: collector / unknown ExaBGP update\n")

			if config["debug"]:
				sys.stdout.write("\n")

		except MySQLdb.Error, e:
			if config["debug"]:
				sys.stdout.write("GIX: collector / MySQLdb exception" + e.args[0] + " - " + e.args[1] + "\n")
				sys.stdout.write(str(prefix) + "\n\n\n")
			Running = False
			os._exit(1)

		except:
			if config["debug"]:
				sys.stdout.write("GIX: collector / exception\n")
				sys.stdout.write(str(prefix) + "\n\n\n")
			Running = False
			os._exit(1)

if __name__ == "__main__":
	if len(sys.argv) >= 2:
		if sys.argv[1] == "exabgp":
			try:
				if config["debug"]:
					if sys.argv[2] != "":
						sys.stdout = open(config["log_file"] + "_" + sys.argv[2], "w")
					else:
						sys.stdout = open(config["log_file"], "w")
					sys.stdout.write("GIX: main / start\n")

				mydb = MySQLdb.connect(host = config["mysql_host"], db = config["mysql_db"], user = config["mysql_user"], passwd = config["mysql_pass"], unix_socket = config["mysql_sock"], connect_timeout = config["mysql_timeout"])
				cursor = mydb.cursor()

				lock = RLock()
				Running = True

				if config["stats_delayed"]:
					statsd = Thread(target = NeighborsStatsWorker)
					statsd.daemon = True
					statsd.start()

				collectord = Thread(target = GIXcollector)
				collectord.daemon = True
				collectord.start()

				if config["debug"]:
					sys.stdout.write("GIX: main / all threads started\n")

				while Running:
					if config["debug"]:
						sys.stdout.write("GIX: main / 1s loop\n")
					time.sleep(1)

			except MySQLdb.Error, e:
				if config["debug"]:
					sys.stdout.write("GIX: main / MySQLdb exception" + e.args[0] + " - " + e.args[1] + "\n")
				Running = False
				sys.exit(1)

			except KeyboardInterrupt:
				if config["debug"]:
					sys.stdout.write("GIX: main / keyboard interrupt\n")
				Running = False
				os._exit(1)

			except:
				if config["debug"]:
					sys.stdout.write("GIX: main / exception\n")
				Running = False
				os._exit(1)
	else:
		print "The code is not design to run as a standalone process and can be used only as `process parsed-route-backend` in ExaBGP."
		print "an example: run %s exabgp [log file suffix]" % sys.argv[0]
		sys.exit(2)
