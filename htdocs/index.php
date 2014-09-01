<?php
 require_once('gixlg-cfg.php');
 require_once('gixlg-lib.php');
 require_once('gixlg-core.php');
 require_once('lib/IPv6.php');
 if (!isset($gixlg) || !isset($router) || !isset($request))
 {
  printError('Oops. This installation misses a configuration file (gixlg-cfg.php)');
  die();
 }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo $gixlg['website_title']; ?></title>
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
<script type="text/javascript" src="lib/popup-ioshack.js"></script>
<link href="lib/gixlg.css" rel="stylesheet" type="text/css" />
</head>
<body>
<center>
<form method="post" action="./">
<table id="hor-minimalist-a">
<thead>
<tr>
<th colspan="2"><center><?php echo $gixlg['website_title']; ?></center></th>
</tr>
</thead>
<tfoot>
<tr>
<td colspan="2"><em><a href="http://gixtools.net">GIXLG</a> - Copyright &copy; GIXtools</em></td>
</tr>
</tfoot>
<tbody>
<tr>
 <td align="right" width="50%"><b>router</b><br/><?php gixlg_routerlist($router, $gixlg['list_style']); ?></td>
 <td align="left" width="50%"><b>request</b><br/><?php gixlg_requestlist($request, $gixlg['list_style']); ?></td>
</tr>
<tr>
 <td align="right" width="50%"><b>argument</b><br/><input type="text" name="argument" maxlength="50" value="<?php echo safeOutput(trim($_REQUEST["argument"])); ?>"/></td>
 <td align="left" width="50%"><br/><input type="submit" value="Execute"/></td>
</tr>
<tr><td colspan="2"><?php gixlg_execsqlrequest($router, $request); ?></td></tr>
</tbody>
</table>
</form>
</center>
</body>
</html>
