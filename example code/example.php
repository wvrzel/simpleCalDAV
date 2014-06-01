<?php

require_once('../simpleCalDAV.php');

$firstNewEvent = 'BEGIN:VCALENDAR
PRODID:-//SomeExampleStuff//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20140403T091024Z
LAST-MODIFIED:20140403T091044Z
DTSTAMP:20140416T091044Z
UID:ExampleUID1
SUMMARY:ExampleEvent1
DTSTART;TZID=Europe/Berlin:20140418T120000
DTEND;TZID=Europe/Berlin:20140418T130000
LOCATION:ExamplePlace1
DESCRIPTION:ExampleDescription1
END:VEVENT
END:VCALENDAR';

$secondNewEvent = 'BEGIN:VCALENDAR
PRODID:-//SomeExampleStuff//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20140403T091024Z
LAST-MODIFIED:20140403T091044Z
DTSTAMP:20140416T091044Z
UID:ExampleUID2
SUMMARY:ExampleEvent2
DTSTART;TZID=Europe/Berlin:20140419T120000
DTEND;TZID=Europe/Berlin:20140419T130000
LOCATION:ExamplePlace2
DESCRIPTION:ExampleDescription2
END:VEVENT
END:VCALENDAR';

$changedFirstEvent = 'BEGIN:VCALENDAR
PRODID:-//SomeExampleStuff//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:20140403T091024Z
LAST-MODIFIED:20140403T091044Z
DTSTAMP:20140416T091044Z
UID:ExampleUID1
SUMMARY:ExampleEvent1
DTSTART;TZID=Europe/Berlin:20140418T090000
DTEND;TZID=Europe/Berlin:20140418T100000
LOCATION:ExamplePlace1
DESCRIPTION:ExampleDescription1
END:VEVENT
END:VCALENDAR';


$ret = simpleCalDAVconnect('http://yourServer/baikal/cal.php/calendars/yourUser/yourCalendar', 'username', 'password');

if ($ret[0] == 0) // Everything worked well
{
	$client = $ret[1];
	
	$ret = simpleCalDAVcreate($client, $firstNewEvent); // Creates $firstNewEvent on the server and returns array(0, 'etag of first event').
	$etagOfFirstEvent = $ret[1];
	$ret = simpleCalDAVcreate($client, $secondNewEvent); // Creates $secondNewEvent on the server and returns array(0, 'etag of second event').
	$etagOfSecondEvent = $ret[1];
	
	$ret = simpleCalDAVgetByUID ($client, 'ExampleUID1'); // Returns array(0, array(array( 'href'=>'something.ics', 'data'=>$firstNewEvent, 'etag'=>'etag of first event'))).
	
	$ret = simpleCalDAVgetEventsByTime($client, '20140418T103000Z', '20140419T200000Z');
	/* Returns array(0, array(
	 * 							array( 'href'=>'something.ics', 'data'=>$firstNewEvent, 'etag'=>'etag of first event'),
	 *							array( 'href'=>'someOtherThing.ics', 'data'=>$secondNewEvent, 'etag'=>'etag of second event'))).
	 */
	
	$ret = simpleCalDAVchange($client, $changedFirstEvent, $etagOfFirstEvent); // Change the first event on the server from $firstNewEvent to $changedFirstEvent
	$etagOfFirstEvent = $ret[1];											   // and return array(0, 'new etag of first event').
	
	$ret = simpleCalDAVgetByUID ($client, 'ExampleUID1');
	// Returns array(0, array( 'href'=>'something.ics', 'data'=>$changedFirstEvent, 'etag'=>'new etag of first event')).
	
	$ret = simpleCalDAVgetEventsByTime($client, '20140418T103000Z', '20140419T200000Z');
	// Returns array(0, array(array( 'href'=>'someOtherThing.ics', 'data'=>$secondNewEvent, 'etag'=>'etag of second event'))).
	
	$ret = simpleCalDAVdelete($client, 'ExampleUID2', $etagOfSecondEvent); // Deletes the second event from the server and returns array(0).
	
	$ret = simpleCalDAVgetEventsByTime($client, '20140418T103000Z', '20140419T200000Z'); // Returns array(0, array()).
}

else
{
	echo '<pre>';
	echo $ret[1].'<br><br>';
	echo 'Request-Header:<br>'.$ret[2].'<br><br>';
	echo 'Request-Body:<br>'.htmlentities($ret[3]).'<br><br>';
	echo 'Resonse-Header:<br>'.$ret[4].'<br><br>';
	echo 'XML-Response-Body:<br>'.htmlentities($ret[5]);
	echo '</pre>';
}

?>