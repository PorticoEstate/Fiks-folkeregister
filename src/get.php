<?php

	include 'fiks.php';

	/**
	 * Personnr
	 * Lokal test:
	 * http://<host>:8210/get.php?apikey=...EHWE...&id=2005...&test=1&debug=1
	 */

	$debug		 = !empty($_GET['debug']) ? true: false;
	$test		 = !empty($_GET['test']) ? true: false;

	if ($test)
	{
		$id		 = $_GET['id'];
		$apikey	 = $_GET['apikey'];
	}
	else
	{
		$id		 = $_POST['id'];
		$apikey	 = $_POST['apikey'];
	}

	if ($apikey && !empty($id))
	{
		$fix = new fiks($apikey, $debug);
		$returnstring = $fix->get_person( $id);
		print_r($returnstring);
	}