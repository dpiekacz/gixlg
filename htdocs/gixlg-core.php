<?php
function gixlg_routerlist($router, $type)
{
 if ($type == "select") echo "<select class=\"form-control\" name=\"routerid\">";
 while (list($id, $attribute) = each($router))
 if (strcmp($id, "default") && !empty($attribute["address"]))
 {
  if ($type == "select") echo "<option value=\"{$id}\"";
  if ($type == "radio") echo "<div class=\"radio\"><input type=\"radio\" name=\"routerid\" value=\"{$id}\"";
  if (array_key_exists("routerid", $_REQUEST) && ($_REQUEST["routerid"] == $id))
  {
   if ($type == "select") echo " selected=\"selected\"";
   if ($type == "radio") echo " checked=\"checked\"";
  }
  echo ">";
  echo $attribute["title"] ? $attribute["title"] : $attribute["address"];
  if ($type == "select") echo "</option>\n";
  if ($type == "radio") echo "</div>\n";
 }
 if ($type == "select") echo "</select>\n";
}

function gixlg_requestlist($request, $type)
{
 if ($type == "select") echo "<select class=\"form-control\" name=\"requestid\">";
 while (list($id, $attribute) = each($request))
 if (!empty($attribute["command"]) && !empty($attribute["handler"]) && isset($attribute["argc"]))
 {
  if ($type == "select") echo "<option value=\"{$id}\"";
  if ($type == "radio") echo "<div class=\"radio\"><input type=\"radio\" name=\"requestid\" value=\"{$id}\"";
  if (array_key_exists("requestid", $_REQUEST) && ($_REQUEST["requestid"] == $id))
  {
   if ($type == "select") echo " selected=\"selected\"";
   if ($type == "radio") echo " checked=\"checked\"";
  }
  echo ">";
  echo $attribute["title"] ? $attribute["title"] : $attribute["command"];
  if ($type == "select") echo "</option>\n";
  if ($type == "radio") echo "</div>\n";
 }
 if ($type == "select") echo "</select>\n";
}

function gixlg_execsqlrequest($router, $request)
{
 global $gixlg;

 if (!isset($_REQUEST["routerid"])) {
  return;
 } else {
  if (!is_numeric($_REQUEST["routerid"])) {
   printError("RouterID argument is not valid.");
   return;
  } else {
   $routerid = $_REQUEST["routerid"];
  }
 }

 if (!isset($router[$routerid]["address"]) || !ip_valid($router[$routerid]["address"], 4, 0)) {
  printError("Router IP address is not valid. Check your config file.");
  return;
 }

 if (!isset($_REQUEST["requestid"]) || !is_numeric($_REQUEST["requestid"])) {
  printError("RequestID is not valid.");
  return;
 } else $requestid = $_REQUEST["requestid"];

 if (!isset($request[$requestid]["argc"]) || !is_numeric($request[$requestid]["argc"])) {
  printError("Request definition need argc option to be set. Check your config file.");
  return;
 }
 $handler = $request[$requestid]["handler"];

// check if request is allowed for this router
 if (empty($handler) || strpos($handler, $router[$routerid]["service"]) === false) {
  printError("This request is not permitted for this router by administrator.");
  return;
 }

 if ($request[$requestid]["argc"] > 0)
 {
  if (trim($_REQUEST["argument"]) == '')
  {
   $router_defined = isset($router[$routerid]["ignore_argc"]);
   $router_permits = $router[$routerid]["ignore_argc"] == 1;
   $default_defined = isset($router["default"]["ignore_argc"]);
   $default_permits = $router["default"]["ignore_argc"] == 1;
   $final_permits =
   (!$router_defined && $default_defined && $default_permits) ||
   ($router_defined && $router_permits);
   if (!$final_permits)
   {
    printError("Argument is required for this command.");
    return;
   }
  } else {
   switch ($requestid) {
    case 20:
     $argument_tmp = trim($_REQUEST["argument"]);
     $dot_loc = strpos($argument_tmp, ".");
     if($dot_loc === false) { $ipver = 6; } else { $ipver = 4; };
     $sla_loc = strpos($argument_tmp, "/");
     if($sla_loc === false) { $ipnet = 0; } else { $ipnet = 1; };
     if (ip_valid($argument_tmp, $ipver, $ipnet) != false) {
      $argument = $argument_tmp;
     } else{
      printError("A valid IP address or network is required as an argument.");
      return;
    }

/*
     $argument_tmp4 = ip_valid(trim($_REQUEST["argument"]), 4, $request[$requestid]["net"]);
     $argument_tmp6 = ip_valid(trim($_REQUEST["argument"]), 6, $request[$requestid]["net"]);
     if ($argument_tmp4!=false) {
      $argument = $argument_tmp4;
     } else {
      if ($argument_tmp6!=false) {
       $argument = $argument_tmp6;
      } else {
       printError("A valid IP address or network is required as an argument.");
       return;
      }
     }
*/

     break;
    case 30:
     if (preg_match('/[^0-9\* ]/', trim($_REQUEST["argument"]))) {
      printError("A valid ASN type argument is required.<br>Currently supported types of query: 'ASN', 'ASN ASN', '* ASN', 'ASN *' and '* ASN *' and other combinations of the above.");
      return;
     } else {
      $argument_tmp = trim($_REQUEST["argument"]);
      if (is_numeric($argument_tmp)) {
       $argument = $argument_tmp;
      } else {
       $argument = str_replace("*", "%", $argument_tmp);
      }
     }
     break;
    default:
     printError("Argument is not valid.");
     break;
   }
  }
 }

 $mid = mysqli_connect($gixlg['db_host'], $gixlg['db_user'], $gixlg['db_password'], $gixlg['db_database']);
 if (mysqli_connect_errno()) {
  printError("Could not connect: " . mysqli_connect_error());
  return;
 };

  switch ($requestid) {
   case 10:
    $res = mysqli_query($mid, "SELECT * FROM `members` ORDER BY `type`,(neighbor+0),`neighbor`");
    $nr = mysqli_num_rows($res);
?>
<table class="table table-striped table-bordered table-hover table-condensed small">
<thead>
<tr>
<th>Node</th>
<th>Location</th>
<th>Country</th>
<th>RIR</th>
<th>AS name</th>
<th>ASN</th>
<th>Neighbor IP</th>
<th>IPv4/6</th>
<th>State</th>
<th>PfxRcd</th>
<th>Up/Down</th>
<th>Last update</th>
<th>Up since/last</th>
<th>Down since/last</th>
</tr>
</thead>
<tfoot>
<tr><td colspan="14">Total number of neighbors <?php echo $nr; ?></td></tr>
</tfoot>
<tbody>
<?php
    while ($d = mysqli_fetch_assoc($res)) {
     $as_info_dns = dns_get_record("AS" . $d['asn'] . ".asn.cymru.com", DNS_TXT);
     list($as_info['as'], $as_info['country'], $as_info['rir'], $as_info['date'], $as_info['desc']) = explode("|", $as_info_dns[0]['txt']);
     $asinfo = explode(" ", $as_info['desc']);

     if ($d['type'] == '4') {
      $res_node = mysqli_query($mid, "SELECT * FROM `nodes` WHERE `ip4`='" . $d['neighbor'] . "'");
     } else {
      $res_node = mysqli_query($mid, "SELECT * FROM `nodes` WHERE `ip6`='" . $d['neighbor'] . "'");
     }
     $d_node = mysqli_fetch_assoc($res_node);

     echo "<tr>";
     echo "<td>" . $d_node['vendor'] . " " . $d_node['model'] . "</td>";
     echo "<td>" . $d_node['location'] . "</td>";
     echo "<td>" . $as_info['country'] . "</td>";
     echo "<td>" . strtoupper($as_info['rir']) . "</td>";
     echo "<td>" . $asinfo[1] . "</td>";
     echo "<td>" . $d['asn'] . "</td>";
     echo "<td>" . $d['neighbor'] . "</td>";
     echo "<td>" . $d['type'] . "</td>";
     if ($d['status']==1) { echo "<td>up</td>"; }
     else { echo "<td>down</td>"; };
     echo "<td>" . $d['prefixes'] . "</td>";
     echo "<td>" . $d['updown'] . "</td>";

     if ($d['time']=='0000-00-00 00:00:00') { echo "<td>never</td>"; }
     else { echo "<td>" . time_diff($d['time']) . "</td>"; };

     if ($d['lastup']=='0000-00-00 00:00:00') { echo "<td>never</td>"; }
     else {
      if ($d['status']==0) { echo "<td>" . $d['lastup'] . "</td>"; }
      else { echo "<td>" . time_diff($d['lastup']) . "</td>"; };
     }

     if ($d['lastdown']=='0000-00-00 00:00:00') { echo "<td>never</td>"; }
     else {
      if ($d['status']==1) { echo "<td>" . $d['lastdown'] . "</td>"; }
      else { echo "<td>" . time_diff($d['lastdown']) . "</td>"; };
     }
     echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
    break;
   case 20:
    if ($ipnet == 1) {
     $res = mysqli_query($mid, "SELECT * FROM `prefixes` WHERE (`prefix`='$argument') ORDER BY LENGTH(aspath),`neighbor`,(neighbor+0),`neighbor`");
    } else {
     $int_ip = inet_ptoi($argument);
     if ($gixlg['ignore_default_routes']) {
      $res = mysqli_query($mid, "SELECT * FROM `prefixes` WHERE (MBRINTERSECTS(ip_poly, POINTFROMWKB(POINT($int_ip, 0))) && (`prefix`!='::/0') and (`prefix`!='0.0.0.0/0')) ORDER BY LENGTH(aspath),`neighbor`,(neighbor+0),`neighbor`");
     } else {
      $res = mysqli_query($mid, "SELECT * FROM `prefixes` WHERE (MBRINTERSECTS(ip_poly, POINTFROMWKB(POINT($int_ip, 0)))) ORDER BY LENGTH(aspath),`neighbor`,(neighbor+0),`neighbor`");
     }
    }
    $nr = mysqli_num_rows($res);
?>
<table class="table table-striped table-bordered table-hover table-condensed small">
<thead>
<tr>
<th>Node</th>
<th>Location</th>
<th>Type</th>
<th>Network</th>
<th>IPv4/6</th>
<th>Neighbor IP</th>
<?php if ($gixlg['mode'] == 'rc') { ?>
<th>Next hop</th>
<th>Dest. node</th>
<th>Dest. type</th>
<th>Dest. loc.</th>
<?php } ?>
<th>AS path</th>
<th>Last seen</th>
</tr>
</thead>
<tfoot>
<tr>
<td colspan="<?php if ($gixlg['mode'] == 'rc') { echo "12"; } else { echo "8"; }; ?>">Total number of prefixes <?php echo $nr; ?>
<?php
 if ($nr > 0) {
  if ($gixlg['flex_image_size']) {
   echo "<br/><img src=\"gixlg-map.php?prefix=" . $argument . "\" alt=\"gixlg-map\"/>";
  } else {
   echo "<br/><img width=\"1200\" src=\"gixlg-map.php?prefix=" . $argument . "\" alt=\"gixlg-map\"/>";
  }
 }
?>
</td>
</tr>
</tfoot>
<tbody>
<?php
    while ($d = mysqli_fetch_assoc($res)) {
     if ($d['type'] == '4') {
      $res_node = mysqli_query($mid, "SELECT * FROM `nodes` WHERE `ip4`='" . $d['neighbor'] . "'");
     } else {
      $res_node = mysqli_query($mid, "SELECT * FROM `nodes` WHERE `ip6`='" . $d['neighbor'] . "'");
     }
     $d_node = mysqli_fetch_assoc($res_node);

     $attr1="";
     $attr2="";
     if ($gixlg['mode'] == 'rc') {
      $ip_int = inet_ptoi($d['nexthop']);
      $res_nexthop = mysqli_query($mid, "SELECT * FROM `nexthops` WHERE (($ip_int>=`ip4_start` && $ip_int<=`ip4_end`) || ($ip_int>=`ip6_start` && $ip_int<=`ip6_end`))");
      $d_nexthop = mysqli_fetch_assoc($res_nexthop);
      $res_member = mysqli_query($mid, "SELECT * FROM `members` WHERE `neighbor`='" . $d['neighbor'] . "'");
      if (mysqli_num_rows($res_member)==1) {
       $d_member = mysqli_fetch_assoc($res_member);
       if (($d['neighbor']==$d['nexthop']) && ($d_member['asn']==$d['aspath'])) { $attr1="<b>"; $attr2="</b>"; }
      }
     } else {
      $res_member = mysqli_query($mid, "SELECT * FROM `members` WHERE `neighbor`='" . $d['neighbor'] . "'");
      if (mysqli_num_rows($res_member)==1) {
       $d_member = mysqli_fetch_assoc($res_member);
       if ($d_member['asn']==$d['aspath']) { $attr1="<b>"; $attr2="</b>"; }
      }
     }
     echo "<tr onmouseover=\"nhpup.popup('Community: " . $d['community'] . "&lt;br/&gt;Extended community: " . $d['extended_community'] . "&lt;br/&gt;Origin: " . $d['origin'] . "&lt;br/&gt;Nexthop: " . $d['nexthop'] . "', {'class': 'pup', 'width': 700})\">";
     echo "<td>" . $attr1 . $d_node['vendor'] . " " . $d_node['model'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d_node['location'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d_node['type'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d['prefix'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d['type'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d['neighbor'] . $attr2 . "</td>";
     if ($gixlg['mode'] == 'rc') {
      echo "<td>" . $attr1 . $d['nexthop'] . $attr2 . "</td>";
      echo "<td>" . $attr1 . $d_nexthop['node'] . $attr2 . "</td>";
      echo "<td>" . $attr1 . $d_nexthop['type'] . $attr2 . "</td>";
      echo "<td>" . $attr1 . $d_nexthop['location'] . $attr2 . "</td>";
     }
     echo "<td>" . $attr1 . $d['aspath'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d['time'] . $attr2 . "</td>";
     echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
    break;
   case 30:
    $res = mysqli_query($mid, "SELECT * FROM `prefixes` WHERE (`aspath` LIKE '$argument') ORDER BY `type`,(prefix+0),prefix,LENGTH(aspath),(nexthop+0),nexthop");
    $nr = mysqli_num_rows($res);
    if ($nr > 2000) {
     printError("Number of prefixes is greater then 2000.");
     return;
    }
?>
<table class="table table-striped table-bordered table-hover table-condensed small">
<thead>
<tr>
<th>Node</th>
<th>Type</th>
<th>Location</th>
<th>Network</th>
<th>IPv4/6</th>
<th>Neighbor IP</th>
<?php if ($gixlg['mode'] == 'rc') { ?>
<th>Next hop</th>
<th>Dest. node</th>
<th>Dest. type</th>
<th>Dest. loc.</th>
<?php } ?>
<th>AS path</th>
<th>Last seen</th>
</tr>
</thead>
<tfoot>
<tr>
<td colspan="<?php if ($gixlg['mode'] == 'rc') { echo "12"; } else { echo "8"; }; ?>">Total number of prefixes <?php echo $nr; ?></td>
</tr>
</tfoot>
<tbody>
<?php
    while ($d = mysqli_fetch_assoc($res)) {
     if ($d['type'] == '4') {
      $res_node = mysqli_query($mid, "SELECT * FROM `nodes` WHERE `ip4`='" . $d['neighbor'] . "'");
     } else {
      $res_node = mysqli_query($mid, "SELECT * FROM `nodes` WHERE `ip6`='" . $d['neighbor'] . "'");
     }
     $d_node = mysqli_fetch_assoc($res_node);

     $attr1="";
     $attr2="";
     $d_nexthop = array();
     $d_member = array();
     if ($gixlg['mode'] == 'rc') {
      $ip_int = inet_ptoi($d['nexthop']);
      $res_nexthop = mysqli_query($mid, "SELECT * FROM `nexthops` WHERE (($ip_int>=`ip4_start` && $ip_int<=`ip4_end`) || ($ip_int>=`ip6_start` && $ip_int<=`ip6_end`))");
      if (mysqli_num_rows($res_nexthop)==1) {
       $d_nexthop = mysqli_fetch_assoc($res_nexthop);
      }
      $res_member = mysqli_query($mid, "SELECT * FROM `members` WHERE `neighbor`='" . $d['neighbor'] . "'");
      if (mysqli_num_rows($res_member)==1) {
       $d_member = mysqli_fetch_assoc($res_member);
       if (($d['neighbor']==$d['nexthop']) && ($d_member['asn']==$d['aspath'])) { $attr1="<b>"; $attr2="</b>"; }
      }
     } else {
      $res_member = mysqli_query($mid, "SELECT * FROM `members` WHERE `neighbor`='" . $d['neighbor'] . "'");
      if (mysqli_num_rows($res_member)==1) {
       $d_member = mysqli_fetch_assoc($res_member);
       if ($d_member['asn']==$d['aspath']) { $attr1="<b>"; $attr2="</b>"; }
      }
     }
     echo "<tr onmouseover=\"nhpup.popup('Community: " . $d['community'] . "&lt;br/&gt;Extended community: " . $d['extended_community'] . "&lt;br/&gt;Origin: " . $d['origin'] . "', {'class': 'pup', 'width': 700})\">";
     echo "<td>" . $attr1 . $d_node['vendor'] . " " . $d_node['model'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d_node['type'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d_node['location'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d['prefix'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d['type'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d['neighbor'] . $attr2 . "</td>";
     if ($gixlg['mode'] == 'rc') {
      echo "<td>" . $attr1 . $d['nexthop'] . $attr2 . "</td>";
      if (isset($d_nexthop['node'])) { echo "<td>" . $attr1 . $d_nexthop['node'] . $attr2 . "</td>"; } else { echo "<td></td>"; };
      if (isset($d_nexthop['type'])) { echo "<td>" . $attr1 . $d_nexthop['type'] . $attr2 . "</td>"; } else { echo "<td></td>"; };
      if (isset($d_nexthop['location'])) { echo "<td>" . $attr1 . $d_nexthop['location'] . $attr2 . "</td>"; } else { echo "<td></td>"; };
     }
     echo "<td>" . $attr1 . $d['aspath'] . $attr2 . "</td>";
     echo "<td>" . $attr1 . $d['time'] . $attr2 . "</td>";
     echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
    break;
   default:
    printError("Request not supported by router.");
    break;
  }

 mysqli_close($mid);
}
?>
