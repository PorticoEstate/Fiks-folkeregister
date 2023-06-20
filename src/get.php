<?php

	include 'fiks.php';

	$test = true;

	/**
	 * Personnr	
	 */
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
		$fix = new fiks($apikey, $debug = true);
		$returnstring = $fix->get_person( $id);
		print_r($returnstring);
	}