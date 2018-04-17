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
 *   - getCalendar()
 *   - create()
 *   - change()
 *   - delete()
 *   - getEvents()
 *   - getAllEvents()
 *   - getTODOs()
 *   - getAllTODOs()
 *   - getCustomReport()
 *
 * All of those functions - except the last one - are realy easy to use, self-explanatory and are
 * deliverd with a big innitial comment, which explains all needed arguments and the return values.
 *
 * This library is heavily based on AgenDAV caldav-client-v2.php by Jorge López Pérez <jorge@adobo.org> which
 * again is heavily based on DAViCal caldav-client-v2.php by Andrew McMillan <andrew@mcmillan.net.nz>.
 * Actually, I hardly added any features. The main point of my work is to make everything straight
 * forward and easy to use. You can use simpleCalDAV whithout a deeper understanding of the
 * calDAV-protocol.
 *
 * Requirements of this library are
 *   - The php extension cURL ( http://www.php.net/manual/en/book.curl.php )
 *   - From Andrew’s Web Libraries: ( https://github.com/andrews-web-libraries/awl )
 *      - XMLDocument.php
 *      - XMLElement.php
 *      - AWLUtilities.php
 *
 * @package simpleCalDAV
 */



require_once('CalDAVClient.php');
require_once('CalDAVException.php');
require_once('CalDAVFilter.php');
require_once('CalDAVObject.php');

class SimpleCalDAVClient {

    /** @var CalDAVCalendar|null $calendar The currently set calendar. */
    private $calendar;
	/** @var CalDAVClient $client */
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
	 *
	 * Debugging:
	 * @throws CalDAVException
	 * For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); }
	 */
	public function connect ( $url, $user, $pass )
	{

		//  Connect to CalDAV-Server and log in
		$client = new CalDAVClient($url, $user, $pass);

		// Valid CalDAV-Server? Or is it just a WebDAV-Server?
		if( ! $client->isValidCalDAVServer() )
		{

			if( $client->GetHttpResultCode() == '401' ) // unauthorized
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
			if( $client->GetHttpResultCode() == '401' ) // unauthorized
			{
				throw new CalDAVException('Login failed', $client);
			}

			elseif( $client->GetHttpResultCode() == '' ) // can't reach server
			{
				throw new CalDAVException('Can\'t reach server', $client);
			}

			else // Unknown status
			{
				throw new CalDAVException('Received unknown HTTP status while checking the connection after establishing it', $client);
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
	 * @throws Exception
	 * For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
    public function findCalendars()
	{
		$this->checkClient();
		
		return $this->client->FindCalendars(true);
	}
	
	/**
	 * function setCalendar()
	 *
	 * Sets the current calendar to work with
	 *
	 * Arguments:
	 * @param CalDAVCalendar $calendar Calendar to be set as current calendar.
	 *
	 * Debugging:
	 * @throws Exception
	 * For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
    public function setCalendar ( CalDAVCalendar $calendar )
	{
		if (!isset($calendar)) return;

		$this->checkClient();
		
        $this->calendar = $calendar;
		$this->client->SetCalendar($this->client->first_url_part.$calendar->getURL());
        
        // Add trailing slash to url if not already existing.
        $this->url = preg_replace('#(?<!/)$#', '/', $this->client->calendar_url);
	}
	
	/**
	 * function create()
	 * Creates a new calendar resource on the CalDAV-Server (event, todo, etc.).
	 *
	 * Arguments:
	 * @param string $cal iCalendar-data of the resource you want to create.
	 *           	      Notice: The iCalendar-data contains the unique ID which specifies where the event is being saved.
	 *
	 * Return value:
	 * @return CalDAVObject An CalDAVObject-representation (see CalDAVObject.php) of your created resource
	 *
	 * Debugging:
     * @throws Exception
	 * @throws CalDAVException
	 * For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
    public function create ( $cal )
	{
		// Connection and calendar set?
        $this->checkCalendar();
		
		// Parse $cal for UID
		if (! preg_match( '#^UID:(.*?)\r?\n?$#m', $cal, $matches ) ) { throw new Exception('Can\'t find UID in $cal'); }
		else { $uid = $matches[1]; }
	
		// Does $this->url.$uid.'.ics' already exist?
		$result = $this->client->GetEntryByHref( $this->url.$uid.'.ics' );
		if ( $this->client->GetHttpResultCode() == '200' ) { throw new CalDAVException($this->url.$uid.'.ics already exists. UID not unique?', $this->client); }
		else if ( $this->client->GetHttpResultCode() == '404' );
		else throw new CalDAVException('Received unknown HTTP status', $this->client);
		
		// Put it!
		$newEtag = $this->client->DoPUTRequest( $this->url.$uid.'.ics', $cal );

		// PUT-request successful?
		if ( $this->client->GetHttpResultCode() != '201' )
		{
			if ( $this->client->GetHttpResultCode() == '204' ) // $url.$uid.'.ics' already existed on server
			{
				throw new CalDAVException( $this->url.$uid.'.ics already existed. Entry has been overwritten.', $this->client);
			}
	
			else // Unknown status
			{
				throw new CalDAVException('Received unknown HTTP status', $this->client);
			}
		}
	
		return new CalDAVObject($this->url.$uid.'.ics', $cal, $newEtag, $this->calendar);
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
	 * @throws Exception
	 * @throws CalDAVException
	 * For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
    public function change ( $href, $new_data, $etag )
	{
		// Connection and calendar set?
        $this->checkCalendar();
	
		// Does $href exist?
		$result = $this->client->GetEntryByHref($href);
		if ( $this->client->GetHttpResultCode() == '200' );
		else if ( $this->client->GetHttpResultCode() == '404' ) throw new CalDAVException('Can\'t find '.$href.' on the server', $this->client);
		else throw new CalDAVException('Received unknown HTTP status', $this->client);
		
		// $etag correct?
		if($result[0]['etag'] != $etag) { throw new CalDAVException('Wrong entity tag. The entity seems to have changed.', $this->client); }
	
		// Put it!
		$newEtag = $this->client->DoPUTRequest( $href, $new_data, $etag );
		
		// PUT-request successful?
		if ( $this->client->GetHttpResultCode() != '204' && $this->client->GetHttpResultCode() != '200' )
		{
			throw new CalDAVException('Received unknown HTTP status', $this->client);
		}
		
		return new CalDAVObject($href, $new_data, $newEtag, $this->calendar);
	}
	
	/**
	 * function delete()
	 * Deletes an event or a TODO from the CalDAV-Server.
	 *
	 * Arguments:
	 * @param string $href See CalDAVObject.php
	 * @param string $etag See CalDAVObject.php
	 *
	 * Debugging:
	 * @throws Exception
	 * @throws CalDAVException
	 * For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
    public function delete ( $href, $etag )
	{
		// Connection and calendar set?
        $this->checkCalendar();
	
		// Does $href exist?
		$result = $this->client->GetEntryByHref($href);
		if(count($result) == 0) throw new CalDAVException('Can\'t find '.$href.'on server', $this->client);
		
		// $etag correct?
		if($result[0]['etag'] != $etag) { throw new CalDAVException('Wrong entity tag. The entity seems to have changed.', $this->client); }
	
		// Do the deletion
		$this->client->DoDELETERequest($href, $etag);
	
		// Deletion successful?
		if ( $this->client->GetHttpResultCode() != '200' and $this->client->GetHttpResultCode() != '204' )
		{
			throw new CalDAVException('Received unknown HTTP status', $this->client);
		}
	}
	
	/**
	 * function getEvents()
	 * Gets all events from the current calendar on the CalDAV-Server which lie in a defined time
	 * interval.
	 *
	 * Arguments:
	 * @param DateTime|string|null $start The starting point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
	 *                                    GMT. If omitted the value is set to -infinity.
	 * @param DateTime|string|null $end The end point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
	 *                                  GMT. If omitted the value is set to +infinity.
	 *
	 * Return value:
	 * @return \CalDAVObject[] an array of CalDAVObjects (See CalDAVObject.php), representing the found events.
	 *
	 * Debugging:
	 * @throws Exception
	 * @throws CalDAVException
	 * For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
    public function getEvents ( $start = null, $end = null )
	{
		// Connection and calendar set?
        $this->checkCalendar();
		
		// Are $start and $end in the correct format?
		$start = $this->convertToGmtString($start);
		$end   = $this->convertToGmtString($end);

		// Get it!
		$results = $this->client->GetEvents( $start, $end );
	
		// GET-request successful?
		if ( $this->client->GetHttpResultCode() != '207' )
		{
			throw new CalDAVException('Received unknown HTTP status', $this->client);
		}
		
		// Reformat
		$report = array();
		foreach($results as $event) $report[] = new CalDAVObject($this->url.$event['href'], $event['data'], $event['etag'], $this->calendar);
	
		return $report;
	}

    /**
     * Returns all the events from all available calendars passing its parameters directly to
	 * {@link getEvents}.
     *
     * @param DateTime|string|null $start Interval start as either DateTime object or GMT string (yyyymmddThhmmssZ).
     * @param DateTime|string|null $end Interval end as either DateTime object or GMT string (yyyymmddThhmmssZ).
     *
     * @return CalDAVObject[]
     *
     * @throws Exception
     * @throws CalDAVException
     */
    public function getAllEvents($start = null, $end = null)
    {
    	// remember the current calendar
		$current_calendar = $this->getCalendar();

		// fetch all events
		$events = array();
		foreach ($this->findCalendars() as $calendar) {
			$this->setCalendar($calendar);
			$events[] = $this->getEvents($start, $end);
		}

		// flatten two dimensional array
        if (!empty($events))
            $events = call_user_func_array('array_merge', $events);

		// restore previously set calendar
        if (isset($current_calendar))
            $this->setCalendar($current_calendar);

		return $events;
    }
	
	/**
	 * function getTODOs()
	 * Gets all TODOs from the current calendar on the CalDAV-Server which lie in a defined time
	 * interval and match the given criteria.
	 *
	 * Arguments:
	 * @param DateTime|string|null $start The starting point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
	 *                                    GMT. If omitted the value is set to -infinity.
	 * @param DateTime|string|null $end The end point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
	 *                                  GMT. If omitted the value is set to +infinity.
	 * @param bool|null $completed Filter for completed tasks (true) or for uncompleted tasks (false). If omitted, the function will return both.
	 * @param bool|null $cancelled Filter for cancelled tasks (true) or for uncancelled tasks (false). If omitted, the function will return both.
	 *
	 * Return value:
	 * @return CalDAVObject[] an array of CalDAVObjects (See CalDAVObject.php), representing the found TODOs.
	 *
	 * Debugging:
	 * @throws Exception
	 * @throws CalDAVException
	 * For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
    public function getTODOs ( $start = null, $end = null, $completed = null, $cancelled = null )
	{
		// Connection and calendar set?
        $this->checkCalendar();
	
		// Are $start and $end in the correct format?
        $start = $this->convertToGmtString($start);
        $end   = $this->convertToGmtString($end);

		// Get it!
		$results = $this->client->GetTodos( $start, $end, $completed, $cancelled );
		
		// GET-request successful?
		if ( $this->client->GetHttpResultCode() != '207' )
		{
			throw new CalDAVException('Received unknown HTTP status', $this->client);
		}
	
		// Reformat
		$report = array();
		foreach($results as $event) $report[] = new CalDAVObject($this->url.$event['href'], $event['data'], $event['etag'], $this->calendar);
	
		return $report;
	}

    /**
     * Returns all the TODOs from all available calendars passing its parameters directly to
     * {@link getTodos}.
     *
     * @param DateTime|string|null $start Interval start as either DateTime object or GMT string (yyyymmddThhmmssZ).
     * @param DateTime|string|null $end Interval end as either DateTime object or GMT string (yyyymmddThhmmssZ).
     * @param bool|null $completed Returns completed (true), uncompleted (false) or both (null).
     * @param bool|null $cancelled Returns cancelled (true), uncancelled (false) or both (null).
	 * 
     * @return CalDAVObject[]
	 * 
     * @throws Exception
     * @throws CalDAVException
     */
    public function getAllTODOs($start = null, $end = null, $completed = null, $cancelled = null)
    {
        // remember the current calendar
        $current_calendar = $this->getCalendar();

		// fetch all TODOs
        $todos = array();
        foreach ($this->findCalendars() as $calendar) {
            $this->setCalendar($calendar);
            $todos[] = $this->getTODOs($start, $end, $completed, $cancelled);
        }

        // flatten two dimensional array
        if (!empty($todos))
            $todos = call_user_func_array('array_merge', $todos);

        // restore previously set calendar
        if (isset($current_calendar))
            $this->setCalendar($current_calendar);

        return $todos;
    }

    /**
	 * Returns the currently selected {@link CalDAVCalendar calendar} or null if none is selected.
     * @return CalDAVCalendar|null
     */
	public function getCalendar()
	{
		return $this->calendar;
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
	 * @throws Exception
	 * @throws CalDAVException
	 * For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
	 */
    public function getCustomReport ( $filterXML )
	{
		// Connection and calendar set?
		$this->checkCalendar();
	
		// Get report!
		$this->client->SetDepth('1');
		
		// Get it!
		$results = $this->client->DoCalendarQuery('<C:filter>'.$filterXML.'</C:filter>');
		
		// GET-request successful?
		if ( $this->client->GetHttpResultCode() != '207' )
		{
			throw new CalDAVException('Received unknown HTTP status', $this->client);
		}
	
		// Reformat
		$report = array();
		foreach($results as $event) $report[] = new CalDAVObject($this->url.$event['href'], $event['data'], $event['etag'], $this->calendar);
	
		return $report;
	}

    /**
	 * Checks whether a calendar is selected and throws an exception if not. Also calls {@link checkClient} before.
     * @throws Exception
     * @return bool
     */
	protected function checkCalendar()
	{
		$this->checkClient();
        if (!isset($this->client->calendar_url))
        	throw new Exception('No calendar selected. Try findCalendars() and setCalendar().');
        	
        return true;
	}

    /**
	 * Checks whether the client is set properly and throws an exception if not.
     * @throws Exception
	 * @return bool
     */
	protected function checkClient()
	{
        if (!isset($this->client))
        	throw new Exception('No connection. Try connect().');
        	
        return true;
	}

    /**
	 * Converts the given time to a string in the following format: yyyymmddThhmmssZ where T and Z
	 * are the letters themselves.
     * @param DateTime|string|null $time Time to be converted. When given as null it will be
	 * directly returned as null. When given as string in a wrong format an error will get
	 * triggered.
     * @return null|string
     */
	protected function convertToGmtString($time)
	{
		if (!isset($time)) return null;

        // Convert $time from DateTime object to string in GMT/UTC time.
        if ($time instanceof DateTime)
        {
            $time->setTimezone(new DateTimeZone('GMT'));
            $time = $time->format('Ymd\THis\Z');
        }
        // Check format
        elseif ( ! preg_match( '/^\d{8}T\d{6}Z$/', $time ) )
        	trigger_error('$start or $end are in the wrong format. They must have the format yyyymmddThhmmssZ and should be in GMT', E_USER_ERROR);

		return $time;
	}
}

?>
