<?php
// load configuration and libraries
 require_once('gixlg-cfg.php');
 require_once('gixlg-lib.php');
 require_once('lib/IPv6.php');
 require_once('Image/GraphViz.php');

// check if prefix is set and is valid IPv4 or IPv6 address
 $argument_tmp = trim($_GET['prefix']);
 $dot_loc = strpos($argument_tmp, ".");
 if($dot_loc === false) { $ipver = 6; } else { $ipver = 4; };
 $sla_loc = strpos($argument_tmp, "/");
 if($sla_loc === false) { $ipnet = 0; } else { $ipnet = 1; };
 if (ip_valid($argument_tmp, $ipver, $ipnet) != false) {
  $prefix = $argument_tmp;
 } else {
  die();
 }

// connect to db
 $mid = mysql_connect($gixlg['db_host'], $gixlg['db_user'], $gixlg['db_password']);
 if (!$mid) die();
 $dbs = mysql_select_db($gixlg['db_database'], $mid);
 if (!$dbs) die();

 if ($ipnet == 1) {
  $res = mysql_query("SELECT * FROM `prefixes` WHERE (`prefix`='$prefix')", $mid);
 } else {
  $int_ip = inet_ptoi($prefix);
  $res = mysql_query("SELECT * FROM `prefixes` WHERE (MBRCONTAINS(ip_poly, POINTFROMWKB(POINT($int_ip, 0))) && `length`!=0)", $mid);
 }

 $graph = array('edgesFrom'=>array(),'nodes'=>array(),'attributes'=>array(),'clusters'=>array(),'subgraphs'=>array(),'bgcolor'=>'#e8edff');
 $gv = new Image_GraphViz(true, $graph);
 while ($d = mysql_fetch_assoc($res)) {
// for each record check if we know that neighbor
// and also lookup for nexthop information

  if ($gixlg['mode'] == "rc") {
// route collector mode
   if ($d['type'] == 4) {
    $res_memb = mysql_query("SELECT * FROM `members` WHERE `neighbor`='" . $d['neighbor'] . "'", $mid);
    $d_memb = mysql_fetch_assoc($res_memb);

    $res_node = mysql_query("SELECT * FROM `nodes` WHERE `ip4`='" . $d['neighbor'] . "'", $mid);
    $d_node = mysql_fetch_assoc($res_node);

    $ip_int = ip2long($d['nexthop']);
    $res_next = mysql_query("SELECT * FROM `nexthops` WHERE ($ip_int>=`ip4_start` && $ip_int<=`ip4_end`)", $mid);
    $d_next = mysql_fetch_assoc($res_next);
   } else {
    $res_node = mysql_query("SELECT * FROM `nodes` WHERE `ip6`='" . $d['neighbor'] . "'", $mid);
    $d_node = mysql_fetch_assoc($res_node);

    $ip_int = inet_ptoi($d['nexthop']);
    $res_next = mysql_query("SELECT * FROM `nexthops` WHERE ($ip_int>=`ip6_start` && $ip_int<=`ip6_end`)", $mid);
    $d_next = mysql_fetch_assoc($res_next);
   }

   $gv->addEdge(array($d_node['vendor'] . " " . $d_node['model'] . " " . $d_node['type'] . " " .  $d_node['location'] => $d_next['node'] . " " . $d_next['type'] . " " . $d_next['location']));

   if ($d_next['type'] != "core") {
    $sp_pos = strpos($d['aspath'], " ");
    if (is_int($sp_pos)) {
     $as_path_tmp = substr($d['aspath'], $sp_pos+1, strlen($d['aspath']));
     $as_path = explode(" ", $as_path_tmp);

     $as_s = $d_next['node'] . " " . $d_next['type'] . " " . $d_next['location'];
     foreach ($as_path as &$as) {
      $as_e_tmp = "AS" . $as;
      $as_info_dns = dns_get_record($as_e_tmp . ".asn.cymru.com", DNS_TXT);
      list($as_info['as'], $as_info['country'], $as_info['rir'], $as_info['date'], $as_info['desc']) = explode("|", $as_info_dns[0]['txt']);
      $asinfo = explode(" ", $as_info['desc']);
      $as_e = $as_e_tmp . " " . $asinfo[1];

      $gv->addEdge(array($as_s => $as_e));
      $as_s = $as_e;
     }
    }
   }
  } else {
// looking glass mode
   if ($d['type'] == 4) {
    $res_memb = mysql_query("SELECT * FROM `members` WHERE `neighbor`='" . $d['neighbor'] . "'", $mid);
    $d_memb = mysql_fetch_assoc($res_memb);

    $res_node = mysql_query("SELECT * FROM `nodes` WHERE `ip4`='" . $d['neighbor'] . "'", $mid);
    $d_node = mysql_fetch_assoc($res_node);

    $ip_int = ip2long($d['nexthop']);
    $res_next = mysql_query("SELECT * FROM `nexthops` WHERE ($ip_int>=`ip4_start` && $ip_int<=`ip4_end`)", $mid);
   } else {
    $res_memb = mysql_query("SELECT * FROM `members` WHERE `neighbor`='" . $d['neighbor'] . "'", $mid);
    $d_memb = mysql_fetch_assoc($res_memb);

    $res_node = mysql_query("SELECT * FROM `nodes` WHERE `ip6`='" . $d['neighbor'] . "'", $mid);
    $d_node = mysql_fetch_assoc($res_node);

    $ip_int = inet_ptoi($d['nexthop']);
    $res_next = mysql_query("SELECT * FROM `nexthops` WHERE ($ip_int>=`ip6_start` && $ip_int<=`ip6_end`)", $mid);
   }

   $as_mem_e_tmp = "AS" . $d_memb['asn'];
   $as_mem_info_dns = dns_get_record($as_mem_e_tmp . ".asn.cymru.com", DNS_TXT);
   list($as_mem_info['as'], $as_mem_info['country'], $as_mem_info['rir'], $as_mem_info['date'], $as_mem_info['desc']) = explode("|", $as_mem_info_dns[0]['txt']);
   $asinfo_mem = explode(" ", $as_mem_info['desc']);
   $as_mem_e = $as_mem_e_tmp . " " . $asinfo_mem[1];

   if (mysql_num_rows($res_next)>0) {
    $d_next = mysql_fetch_assoc($res_next);

    $sp_pos = strpos($d['aspath'], " ");
    $as_path_tmp = substr($d['aspath'], $sp_pos+1, strlen($d['aspath']));
    $as_path = explode(" ", $as_path_tmp);

// IX or Normal graph mode
    if ($gixlg['ix_mode']) {
     $gv->addEdge(array(  $d_next['node'] . " " . $d_next['type'] => $as_mem_e));
     $as_s = $as_mem_e;
    } else {
     $gv->addEdge(array($as_mem_e => $d_next['node'] . " " . $d_next['type']));
     $as_s = $d_next['node'] . " " . $d_next['type'];
    }

//    $gv->addEdge(array($d_node['vendor'] => $d_next['node'] . " " . $d_next['type']));
//    $as_s = $d_next['node'] . " " . $d_next['type'];

//    $gv->addEdge(array($d_next['node'] . " " . $d_next['type'] => $d_node['vendor']));
//    $as_s = $d_node['vendor'];

    if (is_int($sp_pos)) {
     foreach ($as_path as &$as) {
      $as_e_tmp = "AS" . $as;
      $as_info_dns = dns_get_record($as_e_tmp . ".asn.cymru.com", DNS_TXT);
      list($as_info['as'], $as_info['country'], $as_info['rir'], $as_info['date'], $as_info['desc']) = explode("|", $as_info_dns[0]['txt']);
      $asinfo = explode(" ", $as_info['desc']);
      $as_e = $as_e_tmp . " " . $asinfo[1];

      $gv->addEdge(array($as_s => $as_e));
      $as_s = $as_e;
     }
    } else {
     $gv->addNode(array($as_s));
    }
   } else {
    $sp_pos = strpos($d['aspath'], " ");
    if (is_int($sp_pos)) {
     $as_path_tmp = substr($d['aspath'], $sp_pos+1, strlen($d['aspath']));
     $as_path = explode(" ", $as_path_tmp);

     $as_s = $as_mem_e;
//     $as_s = $d_node['vendor'];

     foreach ($as_path as &$as) {
      $as_e_tmp = "AS" . $as;
      $as_info_dns = dns_get_record($as_e_tmp . ".asn.cymru.com", DNS_TXT);
      list($as_info['as'], $as_info['country'], $as_info['rir'], $as_info['date'], $as_info['desc']) = explode("|", $as_info_dns[0]['txt']);
      $asinfo = explode(" ", $as_info['desc']);
      $as_e = $as_e_tmp . " " . $asinfo[1];

      $gv->addEdge(array($as_s => $as_e));
      $as_s = $as_e;
     }
    }
   }
  }
 }
 mysql_close($mid);

// Direct function call
 if ($gixlg['graphviz_mode']=='direct') {
  $gv->image('jpg');
 }

// Executing dot parser from cmd line
 if ($gixlg['graphviz_mode']=='cmd') {
  $dot = $gv->parse();
  header("Content-type: image/jpg");
  passthru("echo '$dot' | /usr/local/bin/dot -Tjpg");
 }
?>
