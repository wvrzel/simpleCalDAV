<?php

require_once('../SimpleCalDAVClient.php');

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
	/*
	 * To establish a connection and to choose a calendar on the server, use
	 * connect()
	 * findCalendars()
	 * setCalendar()
	 */
	
	$client->connect('http://yourServer/baikal/cal.php/calendars/yourUser/yourCalendar', 'username', 'password');
	
	$arrayOfCalendars = $client->findCalendars(); // Returns an array of all accessible calendars on the server.
	
	$client->setCalendar($arrayOfCalendars["myCalendarID"]); // Here: Use the calendar ID of your choice. If you don't know which calendar ID to use, try config/listCalendars.php
	
	/*
	 * You can create calendar objects (e.g. events, todos,...) on the server with create().
	 * Just pass a string with the iCalendar-data which should be saved on the server.
	 * The function returns a CalDAVObject (see CalDAVObject.php) with the stored information about the new object on the server
	 */
	
	$firstNewEventOnServer = $client->create($firstNewEvent); // Creates $firstNewEvent on the server and a CalDAVObject representing the event.
	$secondNewEventOnServer = $client->create($secondNewEvent); // Creates $firstNewEvent on the server and a CalDAVObject representing the event.

	/*
	 * You can getEvents with getEvents()
	 */
	$client->getEvents('20140418T103000Z', '20140419T200000Z'); // Returns array($firstNewEventOnServer, $secondNewEventOnServer);

	/*
	 * An CalDAVObject $o has three attributes
	 * $o->href: Link to the object on the server
	 * $o->data: The iCalendar-data describing the object
	 * $o->etag: see CalDAVObject.php
	 * 
	 * $o->href and $o->etag can be used to change or to delete the object.
	 * $o->data can be processed further on, e.g. printed
	 */
	
	$firstNewEventOnServer = $client->change($firstNewEventOnServer->getHref(),$changedFirstEvent, $firstNewEventOnServer->getEtag());
	// Change the first event on the server from $firstNewEvent to $changedFirstEvent
	// and overwrite $firstNewEventOnServer with the new representation of the changed event on the server.

	$events = $client->getEvents('20140418T103000Z', '20140419T200000Z'); // Returns array($secondNewEventOnServer);

	echo $events[0]->data; // Prints $secondNewEvent. See CalDAVObject.php
	
	$client->delete($secondNewEventOnServer->getHref(), $secondNewEventOnServer->getEtag()); // Deletes the second new event from the server.

	$client->getEvents('20140418T103000Z', '20140419T200000Z'); // Returns an empty array
}

catch (Exception $e) {
	echo $e->__toString();
}

?>