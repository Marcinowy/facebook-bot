<?php
include_once("/var/www/lib/webpages.php");
include_once(__DIR__."/class.php");

$options = getopt("i:m:");
$fb=new Facebook("email","password");
$login=$fb->Login();
if (!$login["success"]) {
	echo $login["error"];
	exit;
}
echo "successful login\n";

for (;;) {
	$fb->SendMessage($login["cookies"],$options["i"],$options["m"]);
	sleep(1);
}
?>