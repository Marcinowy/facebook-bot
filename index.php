<?php

include 'includes/autoload.php';
include_once('/var/www/lib/webpages.php');

use facebook as Facebook;

$options = getopt('i:m:');
$fb = new Facebook('email', 'pass');
$login = $fb->Login();
if (!$login['success']) {
	echo $login['error'];
	exit;
}
echo 'successful login' . "\n";

for (;;) {
	$fb->SendMessage($options['i'], $options['m']);
	sleep(1);
}
?>