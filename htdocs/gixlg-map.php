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
 $mid = mysqli_connect($gixlg['db_host'], $gixlg['db_user'], $gixlg['db_password'], $gixlg['db_database']);
 if (mysqli_connect_errno()) {
  printError("Could not connect: " . mysqli_connect_error());
  exit;
 };

 if ($ipnet == 1) {
  $res = mysqli_query($mid, "SELECT * FROM `prefixes` WHERE (`prefix`='$prefix')");
 } else {
  $int_ip = inet_ptoi($prefix);
  $res = mysqli_query($mid, "SELECT * FROM `prefixes` WHERE (MBRINTERSECTS(ip_poly, POINTFROMWKB(POINT($int_ip, 0))))");
 }

 $graph = array('edgesFrom'=>array(),'nodes'=>array(),'attributes'=>array(),'clusters'=>array(),'subgraphs'=>array(),'bgcolor'=>'#ffffff');
 $gv = new Image_GraphViz(true, $graph);
 while ($d = mysqli_fetch_assoc($res)) {
// for each record check if we know that neighbor
// and also lookup for nexthop information

  if ($gixlg['mode'] == "rc") {
// route collector mode
   if ($d['type'] == 4) {
    $res_node = mysqli_query($mid, "SELECT * FROM `nodes` WHERE `ip4`='" . $d['neighbor'] . "'");
    $d_node = mysqli_fetch_assoc($res_node);

    $ip_int = ip2long($d['nexthop']);
    $res_next = mysqli_query($mid, "SELECT * FROM `nexthops` WHERE ($ip_int>=`ip4_start` && $ip_int<=`ip4_end`)");
    $d_next = mysqli_fetch_assoc($res_next);
   } else {
    $res_node = mysqli_query($mid, "SELECT * FROM `nodes` WHERE `ip6`='" . $d['neighbor'] . "'");
    $d_node = mysqli_fetch_assoc($res_node);

    $ip_int = inet_ptoi($d['nexthop']);
    $res_next = mysqli_query($mid, "SELECT * FROM `nexthops` WHERE ($ip_int>=`ip6_start` && $ip_int<=`ip6_end`)");
    $d_next = mysqli_fetch_assoc($res_next);
   }

   $gv->addEdge(array($d_node['vendor'] . " " . $d_node['model'] . " " . $d_node['type'] . " " .  $d_node['location'] => $d_next['node'] . " " . $d_next['type'] . " " . $d_next['location']));

   if ($d_next['type'] != "core") {
    $as_path = explode(" ", $d['aspath']);
    if ($as_path && $as_path[0] == $d_next['asn']) {
     array_shift($as_path);
    }
    if ($as_path) {
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
    $res_memb = mysqli_query($mid, "SELECT * FROM `members` WHERE `neighbor`='" . $d['neighbor'] . "' AND `type` = 4");
    $d_memb = mysqli_fetch_assoc($res_memb);

    $res_node = mysqli_query($mid, "SELECT * FROM `nodes` WHERE `ip4`='" . $d['neighbor'] . "'");
    $d_node = mysqli_fetch_assoc($res_node);

    $ip_int = ip2long($d['nexthop']);
    $res_next = mysqli_query($mid, "SELECT * FROM `nexthops` WHERE ($ip_int>=`ip4_start` && $ip_int<=`ip4_end`)");
   } else {
    $res_memb = mysqli_query($mid, "SELECT * FROM `members` WHERE `neighbor`='" . $d['neighbor'] . "' AND `type` = 6");
    $d_memb = mysqli_fetch_assoc($res_memb);

    $res_node = mysqli_query($mid, "SELECT * FROM `nodes` WHERE `ip6`='" . $d['neighbor'] . "'");
    $d_node = mysqli_fetch_assoc($res_node);

    $ip_int = inet_ptoi($d['nexthop']);
    $res_next = mysqli_query($mid, "SELECT * FROM `nexthops` WHERE ($ip_int>=`ip6_start` && $ip_int<=`ip6_end`)");
   }

   $as_mem_e_tmp = "AS" . $d_memb['asn'];
   $as_mem_info_dns = dns_get_record($as_mem_e_tmp . ".asn.cymru.com", DNS_TXT);
   list($as_mem_info['as'], $as_mem_info['country'], $as_mem_info['rir'], $as_mem_info['date'], $as_mem_info['desc']) = explode("|", $as_mem_info_dns[0]['txt']);
   $asinfo_mem = explode(" ", $as_mem_info['desc']);
   $as_mem_e = $as_mem_e_tmp . " " . $asinfo_mem[1];

   if (mysqli_num_rows($res_next)>0) {
    $d_next = mysqli_fetch_assoc($res_next);

    $as_path = explode(" ", $d['aspath']);
    if ($as_path && $as_path[0] == $d_next['asn']) {
     array_shift($as_path);
    }

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

    if ($as_path) {
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
    $as_path = explode(" ", $d['aspath']);
    if ($as_path && $as_path[0] == $d_next['asn']) {
     array_shift($as_path);
    }
    if ($as_path) {

     $as_s = $as_mem_e;
     // $as_s = $d_node['vendor'];

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
 mysqli_close($mid);

 switch ($gixlg['graphviz_mode']) {
  // Direct function call
  case "direct":
   $gv->image('jpg');
   break;

  // Executing dot parser from cmd line
  case "cmd":
   $dot = $gv->parse();
   header("Content-type: image/jpg");
   passthru("echo '$dot' | /usr/local/bin/dot -Tjpg");
   break;

  case "cmd_svg":
   $dot = $gv->parse();
   header("Content-type: image/svg+xml");
   passthru("echo '$dot' | /usr/local/bin/dot -Tsvg");
   break;
 }
?>
