<?php

/*
 * simpleCalDAV
 *
 * Copyright 2014 Michael Palm <palm.michael@gmx.de>
 *
 * simpleCalDAV is a php library that allows you to connect to a calDAV-server to get events and TODOs from
 * the server, to change them, to delete them, to create new ones, etc.
 * simpleCalDAV was made and tested for connections to the CalDAV-server Baikal 0.2.7. But it should work
 * with any other CalDAV-server too.
 *
 * It contains the following functions:
 *   - simpleCalDAVconnect()
 *   - simpleCalDAVdelete()
 *   - simpleCalDAVcreate()
 *   - simpleCalDAVchange()
 *   - simpleCalDAVgetEventsByTime()
 *   - simpleCalDAVgetTODOsByTime()
 *   - simpleCalDAVgetByUID()
 *
 * All of those functions are realy easy to use, self-explanatory and come with a big innitial comment, which
 * explains all needed arguments and the return values.
 *
 * This library is heavily based on AgenDAV caldav-client-v2.php by Jorge López Pérez <jorge@adobo.org> which
 * again is heavily based on DAViCal caldav-client-v2.php by Andrew McMillan <andrew@mcmillan.net.nz>.
 * Actually, in contrast to caldav-client-v2.php by Jorge López Pérez I hardly added any features. The main
 * point of my work is to make everything straight forward and easy to use. You can use simpleCalDAV whithout
 * a deeper understanding of the calDAV-protocol.
 *
 * Requirements of this library are
 *   - The php extension cURL ( http://www.php.net/manual/en/book.curl.php )
 *   - From Andrew’s Web Libraries: ( https://github.com/andrews-web-libraries/awl )
 *      - XMLDocument.php
 *      - XMLElement.php
 *      - AWLUtilities.php
 */



require_once('caldav-client-v4.php');

/*
 * function simpleCalDAVconnect()
 * Connects to a CalDAV-Server, chooses a specific calendar and checks for errors.
 *
 * Arguments:
 * $url  = URL to the calendar you want to work with. E.g. http://exam.pl/baikal/cal.php/username/calendername/
 * $user = Username to login with
 * $pass = Password to login with
 *
 * Return value:
 * The return value of the function is always an array.
 * The first value of the array is an integer. If everything worked out well the value is 0. Everything above 0
 * indicates an error.
 * If the function finishes without any errors, the second value is a CalDAVClient-object (caldav-client-v4.php),
 * which is needed to perform requests to the server.
 * In the case of an error the second value is the error-message. The third, fourth, fifth and sixth value (if available)
 * are additional information about the request and the server-response.
 */

function simpleCalDAVconnect ( $url, $user, $pass )
{
	// Check for missing arguments
	if ( ! ( isset($url) && isset($user) && isset($pass) ) ) { return array(1, 'Missing arguments'); }

	//  Connect to CalDAV-Server and log in
	$client = new CalDAVClient ($url, $user, $pass);
	
	// Check for errors
	if ( ! $client->CheckValidCalDAV() )
	{
		if ( $client->GetHttpResultCode() == '401' ) // unauthorisized
		{
			return array(2, 'Login failed', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
		}
			
		elseif ( $client->GetHttpResultCode() == '' ) // can't reach server
		{
			return array(3, 'Can\'t reach server', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
		}
		
		else // Unknown status
		{
			return array(4, 'Recieved unknown HTTP status while checking the connection after establishing it', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
		}
	}
	
	// set url to our calendar (CalDAV-protocol supports multiple calendars)
	$client->SetCalendar($url);
	
	// Is valid calendar?
	if ( count( $client->GetCalendarDetailsByURL($url) ) == 0 )
	{
		return array(5, 'Can\'t find the calendar on the CalDAV-server', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
	}
	
	return array(0, $client);
}

/*
 * function simpleCalDAVdelete()
 * Delets an event or a TODO from the CalDAV-Server.
 *
 * Arguments:
 * $client = CalDAVClient-object from the simpleCalDAVconnect() function.
 * $uid    = Unique ID of the event or the TODO you want to delete. Every iCalendar-entity has a unique ID which identifies it.
 *           The unique ID is stored in the iCalendar-data. On Baikal 0.2.7 servers the iCalendar-entity is stored
 *           in a file named $uid.'.ics', where $uid is the unique ID of the entity. You also get this filename
 *           incl. the unique ID as return value of the simpleCalDAVgetEventsByTime- / simpleCalDAVgetTODOsByTime- and the
 *           simpleCalDAVgetEntityByUID-function. It's stored in the 'href'-value.
 * $etag   = Entity tag of the event you want to delete. The entity tag is a unique identifier, not only of an event
 *           like the unique ID, but of serveral versions of the same event. This means that an event with one unique
 *           ID can have many different entity tags, depending on the content of the event. One version of an event,
 *           i. e. one special description in combination with one special starting time, created at one specific time,
 *           etc., has exactly on unique entity tag.
 *           The  assignment of the entity tag ensures, that you know what you are deleting. It ensures, that no one
 *           changed the event between your viewing of the event and your delete-request. Assigning an entity tag
 *           provides you of accidently destroying the work of others.
 *           Where to get such a neat thing like an entity tag? Everytime you do a create-, change- or a get-request,
 *           you get one in return.
 *
 * Return value:
 * The return value of the function is always an array.
 * The first value of the array is an integer. If everything worked out well the value is 0. Everything above 0
 * indicates an error.
 * If the function finishes without any errors, there is no second value.
 * In the case of an error the second value is the error-message. The third, fourth, fifth and sixth value (if available)
 * are additional information about the request and the server-response.
 */

function simpleCalDAVdelete ( $client, $uid, $etag )
{
	// Check for missing arguments
	if ( ! ( isset($client) && isset($uid) ) ) { return array(1, 'Missing arguments'); }
	
	// Is there a '/' at the end of the url?
	if ( ! preg_match( '#^.*?/$#', $client->calendar_url, $matches ) ) { $url = $client->calendar_url.'/'; }
	else { $url = $client->calendar_url; }
	
	// Are $uid and $etag correct? (For some reason Baikal 0.2.7 just ignores IF-match in delete requests)
	$result = $client->GetEntryByHref( $url.$uid.'.ics' );
	if ( count($result) == 0 ) { return array(2, 'Wrong unique ID. Can\'t find unique ID on server.', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse()); }
	elseif( $result[0]['etag'] != $etag ) { return array(3, 'Wrong entity tag. The entity seems to have changed.', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse()); }

	// Do the deletion
	$client->DoDELETERequest( $url.$uid.'.ics', $etag );
	
	// Deletion successfull?
	if ( $client->GetHttpResultCode() != '200' and $client->GetHttpResultCode() != '204' )
	{
		return array(4, 'Recieved unknown HTTP status', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
	}
	
	return array(0);
}

/*
 * function simpleCalDAVcreate()
 * Creates a new event on the CalDAV-Server.
 *
 * Arguments:
 * $client = CalDAVClient-object from the simpleCalDAVconnect() function.
 * $cal    = iCalendar-data of the event you want to create.
 *           Notice: The iCalendar-data contains the unique ID which specifies where the event is saved.
 *
 * Return value:
 * The return value of the function is always an array.
 * The first value of the array is an integer. If everything worked out well the value is 0. Everything above 0
 * indicates an error.
 * If the function finishes without any errors, the second value is the entity tag of the new event. For
 * additional information about entity tags, check out the initial comment of the simpleCalDAVdelete()- or the
 * simpleCalDAVchange()-function.
 * In the case of an error the second value is the error-message. The third, fourth, fifth and sixth value (if available)
 * are additional information about the request and the server-response.
 */

function simpleCalDAVcreate ( $client, $cal )
{
	// Check for missing arguments
	if ( ! ( isset($client) && isset($cal) ) ) { return array(1, 'Missing arguments'); }
	
	// Parse $cal for UID
	if (! preg_match( '#^UID:(.*?)$#m', $cal, $matches ) ) { return array(2, 'Can\'t find UID in $cal'); }
	else { $uid = $matches[1]; }
	
	// Is there a '/' at the end of the calendar_url?
	if ( ! preg_match( '#^.*?/$#', $client->calendar_url, $matches ) ) { $url = $client->calendar_url.'/'; }
	else { $url = $client->calendar_url; }
	
	// Is $uid already taken?
	$result = $client->GetEntryByHref( $url.$uid.'.ics' );
	if ( count($result) != 0 ) { return array(3, 'Unique ID already exists.', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse()); }
	
	// Put it!
	$newEtag = $client->DoPUTRequest( $url.$uid.'.ics', $cal );
	
	// PUT-request successfull?
	if ( $client->GetHttpResultCode() != '201' )
	{
		if ( $client->GetHttpResultCode() == '204' ) // Uid already exists on server
		{
			return array(4, ':-/ You shouldn\'t get here. Uid already exists. Event has been overwritten.', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
		}
		
		else // Unknown status
		{
			return array(5, 'Recieved unknown HTTP status', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
		}
	}
	
	return array(0, $newEtag);
}

/*
 * function simpleCalDAVchange()
 * Changes an event on the CalDAV-Server.
 *
 * Arguments:
 * $client = CalDAVClient-object from the simpleCalDAVconnect() function.
 * $cal    = The new iCalendar-data that should be used to overwrite the old one.
 *           Notice: The new iCalendar-data contains the unique ID of the event which is going to be changed. For
 *           additional information about unique IDs, check out the initial comment of the simpleCalDAVdelete()-
 *           function.
 * $etag   = Entity tag of the event you want to change. The entity tag is a unique identifier, not only of an event
 *           like the unique ID, but of serveral versions of the same event. This means that an event with one unique
 *           ID can have many different entity tags, depending on the content of the event. One version of an event,
 *           i. e. one special description in combination with one special starting time, created at one specific time,
 *           etc., has exactly on unique entity tag.
 *           The  assignment of the entity tag ensures, that you know what you are changing. It ensures, that no one
 *           changed the event between your viewing of the event and your change-request. Assigning an entity tag
 *           provides you of accidently destroying the work of others.
 *           Where to get such a neat thing like an entity tag? Everytime you do a create-, change- or a get-request,
 *           you get one in return.
 *
 * Return value:
 * The return value of the function is always an array.
 * The first value of the array is an integer. If everything worked out well the value is 0. Everything above 0
 * indicates an error.
 * If the function finishes without any errors, the second value is the new entity tag of the changed event.
 * In the case of an error the second value is the error-message. The third, fourth, fifth and sixth value (if available)
 * are additional information about the request and the server-response.
 */

function simpleCalDAVchange ( $client, $cal, $etag )
{
	// Check for missing arguments
	if ( ! ( isset($client) && isset($cal) && isset($etag) ) ) { return array(1, 'Missing arguments'); }
	
	// Parse $cal for UID
	if (! preg_match( '#^UID:(.*?)$#m', $cal, $matches ) ) { return array(2, 'Can\'t find UID in $cal'); }
	else { $uid = $matches[1]; }
	
	// Is there a '/' at the end of the url?
	if ( ! preg_match( '#^.*?/$#', $client->calendar_url, $matches ) ) { $url = $client->calendar_url.'/'; }
	else { $url = $client->calendar_url; }
	
	// Are $uid and $etag correct?
	$result = $client->GetEntryByHref( $url.$uid.'.ics' );
	if ( count($result) == 0 ) { return array(3, 'Wrong unique ID. Can\'t find unique ID on server.', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse()); }
	elseif( $result[0]['etag'] != $etag ) { return array(4, 'Wrong entity tag. The entity seems to have changed.', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse()); }
	
	// Put it!
	$newEtag = $client->DoPUTRequest( $url.$uid.'.ics', $cal, $etag );
	
	// PUT-request successfull?
	if ( $client->GetHttpResultCode() != '204' )
	{
		if ( $client->GetHttpResultCode() == '412' ) // wrong entity tag
		{
			return array(5, 'You shouldn\'t get here... strange :-/.', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
		}
		
		else // Unknown status
		{
			return array(6, 'Recieved unknown HTTP status', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
		}
	}
	
	return array(0, $newEtag);
}

/*
 * function simpleCalDAVgetEventsByTime()
 * Gets a all events from the CalDAV-Server which lie in a defined time interval.
 *
 * Arguments:
 * $client = CalDAVClient-object from the simpleCalDAVconnect() function.
 * $start  = The starting point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
 *           GMT. If omitted the value is set to -infinity.
 * $finish = The end point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
 *           GMT. If omitted the value is set to +infinity.
 *
 * Return value:
 * The return value of the function is always an array.
 * The first value of the array is an integer. If everything worked out well the value is 0. Everything above 0
 * indicates an error.
 * If the function finishes without any errors, the second value is an array of arrays, stuffed with information
 * about the events found on the server. Each value of the array contained in the second value of the return
 * array describes one found event. Each of those arrays that describes an event has a 'href'-value, an
 * 'data'-value and an 'etag'-value. The 'href'-value contains the filename of the file in which the event is
 * saved on the server. On Baikal 0.2.7 servers the iCalendar-event is stored in a file named $uid.'.ics', where
 * $uid is the unique ID of the event. For additional information about unique IDs, check out the initial comment
 * of the simpleCalDAVdelete()-function. The 'data'-value contains the iCalendar-data of the event. The
 * 'etag'-value contains the entity tag of the event. For additional information about entity tags, check out the
 * initial comment of the simpleCalDAVdelete()- or the simpleCalDAVchange()-function.
 * In the case of an error the second value of the return array is the error-message. The third, fourth, fifth and
 * sixth value (if available) are additional information about the request and the server-response.
 */

function simpleCalDAVgetEventsByTime ( $client, $start = null, $finish = null )
{
	// Check for missing arguments
	if ( ! isset($client) ) { return array(1, 'Missing arguments'); }
	
	// Are $start and $finish in the correct format?
	if ( ( isset($start) and ! preg_match( '#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $start, $matches ) )
	  or ( isset($finish) and ! preg_match( '#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $finish, $matches ) ) )
	{ return array(2, '$start or $finish are in the wrong format. They must have the format yyyymmddThhmmssZ and should be in GMT'); }
	
	// Get it!
	$results = $client->GetEvents( $start, $finish );
	
	// GET-request successfull?
	if ( $client->GetHttpResultCode() != '207' )
	{
		return array(4, 'Recieved unknown HTTP status', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
	}
	
	return array(0, $results);
}

/*
 * function simpleCalDAVgetTODOsByTime()
 * Gets a all TODOs from the CalDAV-Server which lie in a defined time interval.
 *
 * Arguments:
 * $client    = CalDAVClient-object from the simpleCalDAVconnect() function.
 * $start     = The starting point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
 *              GMT. If omitted the value is set to -infinity.
 * $finish    = The end point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
 *              GMT. If omitted the value is set to +infinity.
 * $completed = Boolean whether to include completed tasks.
 * $cancelled = Boolean whether to include cancelled tasks.
 *
 * Return value:
 * The return value of the function is always an array.
 * The first value of the array is an integer. If everything worked out well the value is 0. Everything above 0
 * indicates an error.
 * If the function finishes without any errors, the second value is an array of arrays, stuffed with information
 * about the TODOs found on the server. Each value of the array contained in the second value of the return
 * array describes one found TODO. Each of those arrays that describes an TODO has a 'href'-value, an
 * 'data'-value and an 'etag'-value. The 'href'-value contains the filename of the file in which the TODO is
 * saved on the server. On Baikal 0.2.7 servers the iCalendar-TODOs is stored in a file named $uid.'.ics', where
 * $uid is the unique ID of the TODO. For additional information about unique IDs, check out the initial comment
 * of the simpleCalDAVdelete()-function. The 'data'-value contains the iCalendar-data of the TODO. The
 * 'etag'-value contains the entity tag of the TODO. For additional information about entity tags, check out the
 * initial comment of the simpleCalDAVdelete()- or the simpleCalDAVchange()-function.
 * In the case of an error the second value of the return array is the error-message. The third, fourth, fifth and
 * sixth value (if available) are additional information about the request and the server-response.
 */
function simpleCalDAVgetTODOsByTime ( $client, $start = null, $finish = null, $completed = false, $cancelled = false )
{
	// Check for missing arguments
	if ( ! isset($client) ) { return array(1, 'Missing arguments'); }
	
	// Are $start and $finish in the correct format?
	if ( ( isset($start) and ! preg_match( '#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $start, $matches ) )
	  or ( isset($finish) and ! preg_match( '#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $finish, $matches ) ) )
	{ return array(2, '$start or $finish are in the wrong format. They must have the format yyyymmddThhmmssZ and should be in GMT'); }
	
	// Are $completed and $cancelled booleans?
	if ( gettype($completed) != "boolean" or gettype($cancelled) != "boolean" )
	{ return array(3, '$completed or $cancelled are in the wrong format. They must be booleans'); }
	
	// Get it!
	$results = $client->GetTodos( $start, $finish, $completed, $cancelled );
	
	// GET-request successfull?
	if ( $client->GetHttpResultCode() != '207' )
	{
		return array(4, 'Recieved unknown HTTP status', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
	}
	
	return array(0, $results);
}

/*
 * function simpleCalDAVgetByUID()
 * Gets one or more events or TODOs from the CalDAV-Server by searching for unique IDs which begin with a certain string.
 *
 * Arguments:
 * $client = CalDAVClient-object from the simpleCalDAVconnect() function.
 * $query  = A string. The query.
 *
 * Return value:
 * The return value of the function is always an array.
 * The first value of the array is an integer. If everything worked out well the value is 0. Everything above 0
 * indicates an error.
 * If the function finishes without any errors, the second value is an array of arrays, stuffed with information
 * about the events / TODOs found on the server. Each value of the array contained in the second value of the return
 * array describes one found event / TODO. Each of those arrays that describes an event / TODO has a 'href'-value, an
 * 'data'-value and an 'etag'-value. The 'href'-value contains the filename of the file in which the event / TODO is
 * saved on the server. On Baikal 0.2.7 servers the iCalendar-events / -TODOs are stored in a file named $uid.'.ics', where
 * $uid is the unique ID of the event / TODO. The 'data'-value contains the iCalendar-data of the event / TODO. The
 * 'etag'-value contains the event / TODO tag of the event / TODO. For additional information about event / TODO tags, check out the
 * initial comment of the simpleCalDAVdelete()- or the simpleCalDAVchange()-function.
 * In the case of an error the second value of the return array is the error-message. The third, fourth, fifth and
 * sixth value (if available) are additional information about the request and the server-response.
 */
 
function simpleCalDAVgetByUID ( $client, $query )
{
	// Check for missing arguments
	if ( ! isset($query) ) { return array(1, 'Missing argument'); }
	
	/* stupid, stupid thing!
	if ( gettype($query) == "array" ) // $query array
	{
		// First of all, try it with CalendarMultiget()
	
		$event_hrefs = array();
		
		// Is there a '/' at the end of the url?
		if ( ! preg_match( '#^.*?/$#', $client->base_url, $matches ) ) { $url = $client->base_url.'/'; }
		else { $url = $client->base_url; }
	
		foreach ( $query as $q )
		{
			// Is $query in the correct format?
			if ( gettype($q) != "string" ) { return array(2, '$query is of the wrong type'); }
			
			// convert unique ID --> href
			$event_hrefs[] = $url.$q.'.ics';
		}
		
		// Get it!
		$results = $client->CalendarMultiget( $event_hrefs );
		
		// Did it work?
		if ( $client->GetHttpResultCode() == '404' )
		{
			// No... so let's use GetEntryByUid() on every one of them
			
			foreach ( $query as $q )
			{
				$result = $client->GetEntryByUid( $query );
			
				// request successfull?
				if ( $client->GetHttpResultCode() != '207' )
				{
					return array(3, 'Recieved unknown HTTP status', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
				}
				
				$results = array_merge($results, $result);
			}
		}
	}
	
	else // $query is not an array
	{*/
		// Is $query in the correct format?
		if ( gettype($query) != "string" ) { return array(2, '$query is of the wrong type'); }
		
		// Get it!
		$results = $client->GetEntryByUid( $query );
	//}

	// request successfull?
	if ( $client->GetHttpResultCode() != '207' )
	{
		return array(4, 'Recieved unknown HTTP status', $client->GetHttpRequest(), $client->GetBody(), $client->GetResponseHeaders(), $client->GetXmlResponse());
	}
	
	return array(0, $results);
}

?>