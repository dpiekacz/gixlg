<?php
// website title
$gixlg['website_title'] = 'GIX Looking Glass';
$gixlg['mode'] = 'lg';
$gixlg['ix_mode'] = false;
$gixlg['ignore_default_routes'] = true;

// database auth parameters
$gixlg['db_host'] = 'localhost';
$gixlg['db_user'] = 'gixlg';
$gixlg['db_password'] = 'gixlg';
$gixlg['db_database'] = 'gixlg';

// specify the look you wish lists of routers and requests
// to have here: ('radio'/'select')
$gixlg['list_style'] = 'select';

// true = graph can extend without limit, false = graph is fixed to 1000px width
$gixlg['flex_image_size'] = true;

// method of generation the graphviz maps
// direct - will call graphviz:image function directly
// cmd - will call dot processor by cmd line
$gixlg['graphviz_mode'] = 'direct';

// default values for all routers, used if there is no more specific setting
$router['default']['ignore_argc'] = FALSE;

// your routers
$router[10]['title'] = 'GIX route collector IPv4/6';
$router[10]['address'] = '127.0.0.1';
$router[10]['service'] = 'sql';

// requests definitions.
$request[10]['title'] = 'show ip bgp summary';
$request[10]['command'] = 'show ip bgp summary';
$request[10]['handler'] = 'sql bgpd';
$request[10]['argc'] = 0;
$request[10]['ip'] = 0;
$request[10]['net'] = 0;

$request[20]['title'] = 'show ip bgp [arg NETv4 or NETv6]';
$request[20]['command'] = 'show ip bgp';
$request[20]['handler'] = 'sql bgpd';
$request[20]['argc'] = 1;
$request[20]['ip'] = 0;
$request[20]['net'] = 0;

$request[30]['title'] = 'show ip bgp regexp [arg ASN] - kind of :)';
$request[30]['command'] = 'show ip bgp regexp';
$request[30]['handler'] = 'sql bgpd';
$request[30]['argc'] = 1;
$request[30]['ip'] = 0;
$request[30]['net'] = 0;
?>
