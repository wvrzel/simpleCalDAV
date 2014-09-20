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

$client = new SimpleCalDAVClient();

try {
	$client->connect('http://yourServer/baikal/cal.php/calendars/yourUser/yourCalendar', 'username', 'password');
	
	$calendars = $client->findCalendars(); // Returns an array of all accessible calendars on the server.
	
	// Search for the right calendar
	foreach($calendars as $calendar) if($calendar->getCalendarID == 'myCalendar') { $myCalendar = $calendar; break; }
	if(!isset($myCalendar)) die('Could not accsess my calendar.');
	
	$client->setCalendar($myCalendar);
	
	$firstNewEventOnServer = $client->create($firstNewEvent); // Creates $firstNewEvent on the server and a CalDAVObject representing the event.
	$secondNewEventOnServer = $client->create($secondNewEvent); // Creates $firstNewEvent on the server and a CalDAVObject representing the event.

	$client->getEvents('20140418T103000Z', '20140419T200000Z'); // Returns array($firstNewEventOnServer, $secondNewEventOnServer);

	$firstNewEventOnServer = $client->change($firstNewEventOnServer->getHref(),$changedFirstEvent, $firstNewEventOnServer->getEtag());
	// Change the first event on the server from $firstNewEvent to $changedFirstEvent
	// and overwrite $firstNewEventOnServer with the new representation of the changed event on the server.

	$client->getEvents('20140418T103000Z', '20140419T200000Z'); // Returns array($secondNewEventOnServer);

	$client->delete($secondNewEventOnServer->getHref(), $secondNewEventOnServer->getEtag()); // Deletes the second new event from the server.

	$client->getEvents('20140418T103000Z', '20140419T200000Z'); // Returns an empty array
}

catch (Exception $e) {
	echo $e->__toString();
}

?>