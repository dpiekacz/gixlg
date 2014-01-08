<?php
function time_diff($start, $end="NOW")
{
 $sdate = strtotime($start);
 $edate = strtotime($end);
 $time = $edate - $sdate;

 if($time>=0 && $time<=59) {
  // Seconds
  $timeshift = $time.'s';
 } elseif($time>=60 && $time<=3599) {
  // Minutes + Seconds
  $pmin = ($edate - $sdate) / 60;
  $premin = explode('.', $pmin);

  $presec = $pmin-$premin[0];
  $sec = $presec*60;

  $timeshift = $premin[0].'m'.round($sec,0).'s';
 } elseif($time>=3600 && $time<=86399) {
  // Hours + Minutes
  $phour = ($edate - $sdate) / 3600;
  $prehour = explode('.',$phour);

  $premin = $phour-$prehour[0];
  $min = explode('.',$premin*60);

  $presec = '0.'.$min[1];
  $sec = $presec*60;

  $timeshift = $prehour[0].'h'.$min[0].'m'.round($sec,0).'s';
 } elseif($time>=86400) {
  // Days + Hours + Minutes
  $pday = ($edate - $sdate) / 86400;
  $preday = explode('.',$pday);

  $phour = $pday-$preday[0];
  $prehour = explode('.',$phour*24); 

  $premin = ($phour*24)-$prehour[0];
  $min = explode('.',$premin*60);

  $presec = '0.'.$min[1];
  $sec = $presec*60;

  $timeshift = $preday[0].'d'.$prehour[0].'h'.$min[0].'m'.round($sec,0).'s';
 }
 return $timeshift;
}

function printError($message)
{
 echo "<font color=\"red\"><code><strong>" . $message . "</strong></code></font><br>\n";
}

function safeOutput($string)
{
 return htmlentities(substr($string, 0, 50));
}

function ip_valid($ip, $ver, $net) {
 $val_ip = false;
 $val_prefix = 0;

 if ($ver==4) {
  $slash_pos = strpos($ip, "/");
  if ($slash_pos == false) {
   $ip_address = $ip;
   $val_ip = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
   $val_prefix = 2;
  } else {
   $ip_address = substr($ip, 0, $slash_pos);
   $val_ip = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
   $ip_prefix = (int)substr($ip, $slash_pos+1, strlen($ip));
   if (($ip_prefix>0) && ($ip_prefix<=32)) { $val_prefix = 1; };
  }
  if ($net == true) {
   if (($val_ip!=false) && ($val_prefix==1)) { return $ip_address."/".$ip_prefix; } else { return false; };
  } else {
   if (($val_ip!=false) && ($val_prefix==2)) { return $ip_address; } else { return false; };
  }
 } else {
  $slash_pos = strpos($ip, "/");
  if ($slash_pos == false) {
   $ip_address = $ip;
   $val_ip = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
   $val_prefix = 2;
  } else {
   $ip_address = substr($ip, 0, $slash_pos);
   $val_ip = filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
   $ip_prefix = (int)substr($ip, $slash_pos+1, strlen($ip));
   if (($ip_prefix>0) && ($ip_prefix<=128)) { $val_prefix = 1; };
  }
  if ($net == true) {
   if (($val_ip!=false) && ($val_prefix==1)) { return $ip_address."/".$ip_prefix; } else { return false; };
  } else {
   if (($val_ip!=false) && ($val_prefix==2)) { return $ip_address; } else { return false; };
  }
 }
}
?>
