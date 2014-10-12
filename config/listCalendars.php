<?php
/**
 * listCalendars
 *
 * Copyright 2014 Michael Palm <palm.michael@gmx.de>
 *
 * Open this file in a webbrowser to view a list of all accessible calendars
 * on the server and the information related to those calendars. It can be used
 * to determine the calendar-id, needed for SimpleCalDAV.
 * 
 * @package simpleCalDAV
 */

require_once('../SimpleCalDAVClient.php');

if($_POST == null) {
	echo '
<form action="#" method="post">
	<p>This formular can be used to view a list of all accessible calendars on the server and the information related to those calendars. It can be used to determine the calendar-id, needed for SimpleCalDAV.</p>
	<p>Calendar-URL:<br><input name="url" type="text" size="30" maxlength="100"></p>
	<p>Username:<br><input name="user" type="text" size="30" maxlength="100"></p>
	<p>Password:<br><input name="pass" type="text" size="30" maxlength="100"></p>
	<input type="submit" value=" Show! ">
</form>';
}

else {
	$client = new SimpleCalDAVClient();

	try {
		$client->connect($_POST['url'], $_POST['user'], $_POST['pass']);
		
		$calendars = $client->findCalendars();
		
		echo'
<table>';
		
		$i = 0;
		foreach($calendars as $cal) {
			$i++;
			
			echo '
	<tr> <td></td> <td><strong>Calendar #'.$i.'</strong></td> </tr>
	<tr> <td>URL:</td> <td>'.$cal->getURL().'</td> </tr>
	<tr> <td>Display Name:</td> <td>'.$cal->getDisplayName().'</td> </tr>
	<tr> <td>Calendar ID:</td> <td>'.$cal->getCalendarID().'</td> </tr>
	<tr> <td>CTAG:</td> <td>'.$cal->getCTag().'</td> </tr>
	<tr> <td>RBG-Color:</td> <td>'.$cal->getRBGcolor().'</td> </tr>
	<tr> <td>Order:</td> <td>'.$cal->getOrder().'</td> </tr>
	<tr> <td></td> <td></td> </tr>';
		}
		
		echo '
</table>';
	}
	
	catch (Exception $e) {
		echo $e->__toString();
	}
}

?>