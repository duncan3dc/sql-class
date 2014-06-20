<?php

spl_autoload_register(function($class) {
	if(substr($class,0,19) != "duncan3dc\\SqlClass\\") {
		return;
	}
	$filename = str_replace("duncan3dc\\SqlClass\\","src/",$class) . ".php";
	if(file_exists($filename)) {
		require($filename);
	}
});
