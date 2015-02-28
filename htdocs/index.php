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
 header("Cache-Control: no-cache, must-revalidate");
 header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
 if (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)) header('X-UA-Compatible: IE=edge,chrome=1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo $gixlg['website_title']; ?></title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap-theme.min.css">
    <link rel="stylesheet" href="lib/nhpup_1.1.css">
    <link rel="icon" href="lib/favicon.ico">
    <!--[if lt IE 9]>
     <script type="text/javascript" src="//oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
     <script type="text/javascript" src="//oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body role="document">
 <nav class="navbar navbar-default">
  <div class="container-fluid">
   <div class="navbar-header">
    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
     <span class="sr-only">Toggle navigation</span>
     <span class="icon-bar"></span>
     <span class="icon-bar"></span>
     <span class="icon-bar"></span>
    </button>
    <a class="navbar-brand" href="https://gixtools.net/"><?php echo $gixlg['website_title']; ?></a>
   </div>
  </div>
 </nav>

 <div class="well">
  <form class="form-inline" role="form" method="post" action="./">
   <div class="form-group">
    <?php gixlg_routerlist($router, $gixlg['list_style']); ?>
    <?php gixlg_requestlist($request, $gixlg['list_style']); ?>
    <input type="text" name="argument" placeholder="argument" class="form-control input-sm" value="<?php if (array_key_exists('argument', $_REQUEST)) { echo safeOutput(trim($_REQUEST['argument'])); } ?>" />
    <button type="submit" class="btn btn-default">Execute</button>
   </div>
   <br/><br/>
  </form>

  <div class="container-fluid">
   <?php gixlg_execsqlrequest($router, $request); ?>
  </div>

 </div>

 <footer>
  <p style="text-align:center"><a href="https://gixtools.net/gix/looking-glass/">GIXLG</a> - Copyright &copy; <a href="https://gixtools.net">GIXtools</a></p>
 </footer>

 <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
 <script type="text/javascript" src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
 <script type="text/javascript" src="lib/nhpup_1.1.js"></script>
</body>
</html>
