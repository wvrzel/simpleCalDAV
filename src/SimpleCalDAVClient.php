<?php

/**
 * SimpleCalDAVClient
 *
 * Copyright 2014 Michael Palm <palm.michael@gmx.de>
 *
 * simpleCalDAV is a php library that allows you to connect to a calDAV-server to get event-, todo-
 * and free/busy-calendar resources from the server, to change them, to delete them, to create new ones, etc.
 * simpleCalDAV was made and tested for connections to the CalDAV-server Baikal 0.2.7. But it should work
 * with any other CalDAV-server too.
 *
 * It contains the following functions:
 *   - connect()
 *   - findCalendars()
 *   - setCalendar()
 *   - create()
 *   - change()
 *   - delete()
 *   - getEvents()
 *   - getTODOs()
 *   - getCustomReport()
 *
 * All of those functions - except the last one - are realy easy to use, self-explanatory and are
 * deliverd with a big innitial comment, which explains all needed arguments and the return values.
 *
 * This library is heavily based on AgenDAV caldav-client-v2.php by Jorge L�pez P�rez <jorge@adobo.org> which
 * again is heavily based on DAViCal caldav-client-v2.php by Andrew McMillan <andrew@mcmillan.net.nz>.
 * Actually, I hardly added any features. The main point of my work is to make everything straight
 * forward and easy to use. You can use simpleCalDAV whithout a deeper understanding of the
 * calDAV-protocol.
 *
 * Requirements of this library are
 *   - The php extension cURL ( http://www.php.net/manual/en/book.curl.php )
 *   - From Andrew�s Web Libraries: ( https://github.com/andrews-web-libraries/awl )
 *      - XMLDocument.php
 *      - XMLElement.php
 *      - AWLUtilities.php
 *
 * @package simpleCalDAV
 */

namespace it\thecsea\simple_caldav_client;


class SimpleCalDAVClient {
	private $client;
    private $url;

	/**
	 * function connect()
	 * Connects to a CalDAV-Server.
	 *
	 * Arguments:
	 * @param string $url URL to the CalDAV-server. E.g. http://exam.pl/baikal/cal.php/username/calendername/
	 * @param string $user Username to login with
	 * @param string $pass Password to login with
	 * @param array $options
	 *
	 * Debugging:
	 * @throws CalDAVException For debugging purposes, just sorround everything with try { ... } catch (Exception $e) { echo $e->__toString(); }
	 */
	function connect ( $url, $user, $pass, $options=[] )
	{

		//  Connect to CalDAV-Server and log in
		$client = new CalDAVClient($url, $user, $pass, $options);

		// Valid CalDAV-Server? Or is it just a WebDAV-Server?
		if( ! $client->isValidCalDAVServer() )
		{

			if( $client->GetHttpResultCode() == '401' ) // unauthorisized
			{
					throw new CalDAVException('Login failed', $client);
			}

			elseif( $client->GetHttpResultCode() == '' ) // can't reach server
			{
					throw new CalDAVException('Can\'t reach server', $client);
			}

			else throw new CalDAVException('Could\'n find a CalDAV-collection under the url', $client);
		}

		// Check for errors
		if( $client->GetHttpResultCode() != '200' ) {
			if( $client->GetHttpResultCode() == '401' ) // unauthorisized
			{
				throw new CalDAVException('Login failed', $client);
			}

			elseif( $client->GetHttpResultCode() == '' ) // can't reach server
			{
				throw new CalDAVException('Can\'t reach server', $client);
			}

			else // Unknown status
			{
				throw new CalDAVException('Recieved unknown HTTP status while checking the connection after establishing it', $client);
			}
		}

		$this->client = $client;
	}

	/**
	 * function findCalendars()
	 *
	 * Requests a list of all accessible calendars on the server
	 *
	 * Return value:
	 * @return CalDAVCalendar[] an array of CalDAVCalendar-Objects (see CalDAVCalendar.php), representing all calendars accessible by the current principal (user).
	 *
	 * Debugging:
	 * @throws CalDAVException
	 * For debugging purposes, just sorround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
	function findCalendars()
	{
		if(!isset($this->client)) throw new Exception('No connection. Try connect().');

		return $this->client->FindCalendars(true);
	}

	/**
	 * function setCalendar()
	 *
	 * Sets the actual calendar to work with
     * @param CalDAVCalendar $calendar
     *
	 * Debugging:
	 * @throws CalDAVException
	 * For debugging purposes, just sorround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
	function setCalendar ( CalDAVCalendar $calendar )
	{
		if(!isset($this->client)) throw new Exception('No connection. Try connect().');

		$this->client->SetCalendar($this->client->first_url_part.$calendar->getURL());

        // Is there a '/' at the end of the calendar_url?
		if ( ! preg_match( '#^.*?/$#', $this->client->calendar_url, $matches ) ) { $this->url = $this->client->calendar_url.'/'; }
		else { $this->url = $this->client->calendar_url; }
	}

	/**
	 * function create()
	 * Creates a new calendar resource on the CalDAV-Server (event, todo, etc.).
	 *
	 * Arguments:
	 * @param string $cal iCalendar-data of the resource you want to create.
	 *           	Notice: The iCalendar-data contains the unique ID which specifies where the event is being saved.
	 *
	 * Return value:
	 * @return CalDAVObject An CalDAVObject-representation (see CalDAVObject.php) of your created resource
	 *
	 * Debugging:
	 * @throws CalDAVException
	 * For debugging purposes, just sorround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
	function create ( $cal )
	{
		// Connection and calendar set?
		if(!isset($this->client)) throw new Exception('No connection. Try connect().');
		if(!isset($this->client->calendar_url)) throw new Exception('No calendar selected. Try findCalendars() and setCalendar().');

		// Parse $cal for UID
		if (! preg_match( '#^UID:(.*?)\r?\n?$#m', $cal, $matches ) ) { throw new Exception('Can\'t find UID in $cal'); }
		else { $uid = $matches[1]; }

		// Does $this->url.$uid.'.ics' already exist?
		$result = $this->client->GetEntryByHref( $this->url.$uid.'.ics' );
		if ( $this->client->GetHttpResultCode() == '200' ) { throw new CalDAVException($this->url.$uid.'.ics already exists. UID not unique?', $this->client); }
		else if ( $this->client->GetHttpResultCode() == '404' );
		else throw new CalDAVException('Recieved unknown HTTP status', $this->client);

		// Put it!
		$newEtag = $this->client->DoPUTRequest( $this->url.$uid.'.ics', $cal );

		// PUT-request successfull?
		if ( $this->client->GetHttpResultCode() != '201' )
		{
			if ( $this->client->GetHttpResultCode() == '204' ) // $url.$uid.'.ics' already existed on server
			{
				throw new CalDAVException( $this->url.$uid.'.ics already existed. Entry has been overwritten.', $this->client);
			}

			else // Unknown status
			{
				throw new CalDAVException('Recieved unknown HTTP status', $this->client);
			}
		}

		return new CalDAVObject($this->url.$uid.'.ics', $cal, $newEtag);
	}

	/**
	 * function change()
	 * Changes a calendar resource (event, todo, etc.) on the CalDAV-Server.
	 *
	 * Arguments:
	 * @param string $href See CalDAVObject.php
	 * @param string $new_data The new iCalendar-data that should be used to overwrite the old one.
	 * @param string $etag See CalDAVObject.php
	 *
	 * Return value:
	 * @return CalDAVObject An CalDAVObject-representation (see CalDAVObject.php) of your changed resource
	 *
	 * Debugging:
	 * @throws CalDAVException
	 * For debugging purposes, just sorround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
	function change ( $href, $new_data, $etag )
	{
		// Connection and calendar set?
		if(!isset($this->client)) throw new Exception('No connection. Try connect().');
		if(!isset($this->client->calendar_url)) throw new Exception('No calendar selected. Try findCalendars() and setCalendar().');

		// Does $href exist?
		$result = $this->client->GetEntryByHref($href);
		if ( $this->client->GetHttpResultCode() == '200' );
		else if ( $this->client->GetHttpResultCode() == '404' ) throw new CalDAVException('Can\'t find '.$href.' on the server', $this->client);
		else throw new CalDAVException('Recieved unknown HTTP status', $this->client);

		// $etag correct?
		if($result[0]['etag'] != $etag) { throw new CalDAVException('Wrong entity tag. The entity seems to have changed.', $this->client); }

		// Put it!
		$newEtag = $this->client->DoPUTRequest( $href, $new_data, $etag );

		// PUT-request successfull?
		if ( $this->client->GetHttpResultCode() != '204' && $this->client->GetHttpResultCode() != '200' )
		{
			throw new CalDAVException('Recieved unknown HTTP status', $this->client);
		}

		return new CalDAVObject($href, $new_data, $newEtag);
	}

	/**
	 * function delete()
	 * Delets an event or a TODO from the CalDAV-Server.
	 *
	 * Arguments:
	 * @param string $href See CalDAVObject.php
	 * @param string|null $etag See CalDAVObject.php
	 *
	 * Debugging:
	 * @throws CalDAVException
	 * For debugging purposes, just sorround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
	function delete ( $href, $etag = null )
	{
		// Connection and calendar set?
		if(!isset($this->client)) throw new Exception('No connection. Try connect().');
		if(!isset($this->client->calendar_url)) throw new Exception('No calendar selected. Try findCalendars() and setCalendar().');

		// Does $href exist?
		$result = $this->client->GetEntryByHref($href);
		if(count($result) == 0) throw new CalDAVException('Can\'t find '.$href.'on server', $this->client);

		if(isset($etag) and !empty($etag)) {
			// $etag correct?
			if($result[0]['etag'] != $etag) { throw new CalDAVException('Wrong entity tag. The entity seems to have changed.', $this->client); }
		}

		// Do the deletion
		if(isset($etag) and !empty($etag)) {
			$this->client->DoDELETERequest($href, $etag);
		} else {
			$this->client->DoDELETERequest($href);
		}

		// Deletion successfull?
		if ( $this->client->GetHttpResultCode() != '200' and $this->client->GetHttpResultCode() != '204' )
		{
			throw new CalDAVException('Recieved unknown HTTP status', $this->client);
		}
	}

	/**
	 * function getEvents()
	 * Gets a all events from the CalDAV-Server which lie in a defined time interval.
	 *
	 * Arguments:
	 * @param string|null $start The starting point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
	 *           		GMT. If omitted the value is set to -infinity.
	 * @param string|null $end The end point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
	 *           		GMT. If omitted the value is set to +infinity.
	 *
	 * Return value:
	 * @return CalDAVObject[] an array of CalDAVObjects (See CalDAVObject.php), representing the found events.
	 *
	 * Debugging:
	 * @throws CalDAVException
	 * For debugging purposes, just sorround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
	function getEvents ( $start = null, $end = null )
	{
		// Connection and calendar set?
		if(!isset($this->client)) throw new Exception('No connection. Try connect().');
		if(!isset($this->client->calendar_url)) throw new Exception('No calendar selected. Try findCalendars() and setCalendar().');

		// Are $start and $end in the correct format?
		if ( ( isset($start) and ! preg_match( '#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $start, $matches ) )
		  or ( isset($end) and ! preg_match( '#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $end, $matches ) ) )
		{ trigger_error('$start or $end are in the wrong format. They must have the format yyyymmddThhmmssZ and should be in GMT', E_USER_ERROR); }

		// Get it!
		$results = $this->client->GetEvents( $start, $end );

		// GET-request successfull?
		if ( $this->client->GetHttpResultCode() != '207' )
		{
			throw new CalDAVException('Recieved unknown HTTP status', $this->client);
		}

		// Reformat
		$report = array();
		foreach($results as $event) $report[] = new CalDAVObject($this->url.$event['href'], $event['data'], $event['etag']);

		return $report;
	}

	/**
	 * function getTODOs()
	 * Gets a all TODOs from the CalDAV-Server which lie in a defined time interval and match the
	 * given criteria.
	 *
	 * Arguments:
	 * @param string $start The starting point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
	 *              	GMT. If omitted the value is set to -infinity.
	 * @param string $end The end point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
	 *              	GMT. If omitted the value is set to +infinity.
	 * @param bool|null $completed Filter for completed tasks (true) or for uncompleted tasks (false). If omitted, the function will return both.
	 * @param bool|null $cancelled Filter for cancelled tasks (true) or for uncancelled tasks (false). If omitted, the function will return both.
	 *
	 * Return value:
	 * @return CalDAVObject[] an array of CalDAVObjects (See CalDAVObject.php), representing the found TODOs.
	 *
	 * Debugging:
	 * @throws CalDAVException
	 * For debugging purposes, just sorround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
	function getTODOs ( $start = null, $end = null, $completed = null, $cancelled = null )
	{
		// Connection and calendar set?
		if(!isset($this->client)) throw new Exception('No connection. Try connect().');
		if(!isset($this->client->calendar_url)) throw new Exception('No calendar selected. Try findCalendars() and setCalendar().');

		// Are $start and $end in the correct format?
		if ( ( isset($start) and ! preg_match( '#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $start, $matches ) )
		  or ( isset($end) and ! preg_match( '#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $end, $matches ) ) )
		{ trigger_error('$start or $end are in the wrong format. They must have the format yyyymmddThhmmssZ and should be in GMT', E_USER_ERROR); }

		// Get it!
		$results = $this->client->GetTodos( $start, $end, $completed, $cancelled );

		// GET-request successfull?
		if ( $this->client->GetHttpResultCode() != '207' )
		{
			throw new CalDAVException('Recieved unknown HTTP status', $this->client);
		}

		// Reformat
		$report = array();
		foreach($results as $event) $report[] = new CalDAVObject($this->url.$event['href'], $event['data'], $event['etag']);

		return $report;
	}

	/**
	 * function getCustomReport()
     * Sends a custom request to the server
	 * (Sends a REPORT-request with a custom <C:filter>-tag)
	 *
     * You can either write the filterXML yourself or build an CalDAVFilter-object (see CalDAVFilter.php).
     *
	 * See http://www.rfcreader.com/#rfc4791_line1524 for more information about how to write filters on your own.
	 *
	 * Arguments:
	 * @param string $filterXML The stuff, you want to send encapsulated in the <C:filter>-tag.
	 *
	 * Return value:
	 * @return CalDAVObject[] an array of CalDAVObjects (See CalDAVObject.php), representing the found calendar resources.
	 *
	 * Debugging:
	 * @throws CalDAVException
	 * For debugging purposes, just sorround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
	function getCustomReport ( $filterXML )
	{
		// Connection and calendar set?
		if(!isset($this->client)) throw new Exception('No connection. Try connect().');
		if(!isset($this->client->calendar_url)) throw new Exception('No calendar selected. Try findCalendars() and setCalendar().');

		// Get report!
		$this->client->SetDepth('1');

		// Get it!
		$results = $this->client->DoCalendarQuery('<C:filter>'.$filterXML.'</C:filter>');

		// GET-request successfull?
		if ( $this->client->GetHttpResultCode() != '207' )
		{
			throw new CalDAVException('Recieved unknown HTTP status', $this->client);
		}

		// Reformat
		$report = array();
		foreach($results as $event) $report[] = new CalDAVObject($this->url.$event['href'], $event['data'], $event['etag']);

		return $report;
	}
}

?>
