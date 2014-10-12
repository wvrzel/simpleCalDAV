<?php
/**
 * CalDAVCalendar
 *
 * Copyright 2014 Michael Palm <palm.michael@gmx.de>
 *
 * This class represents an accsessible calendar on the server.
 * 
 * I think the functions
 *   - getURL()
 *   - getDisplayName()
 *   - getCalendarID()
 *   - getRGBAcolor()
 *   - getRBGcolor()
 * are pretty self-explanatory.
 * 
 * 
 * getCTag() returns the ctag of the calendar.
 * The ctag is an hash-value used to check, if the client is up to date. The ctag changes everytime
 * someone changes something in the calendar. So, to check if anything happend since your last visit:
 * just compare the ctags.
 * 
 * getOrder() returns the order of the calendar in the list of calendars
 *   
 *
 * @package simpleCalDAV
 *
 */

class CalDAVCalendar {
	private $url;
	private $displayname;
	private $ctag;
	private $calendar_id;
	private $rgba_color;
	private $rbg_color;
	private $order;
	
	function __construct ( $url, $displayname = null, $ctag = null, $calendar_id = null, $rbg_color = null, $order = null ) {
		$this->url = $url;
		$this->displayname = $displayname;
		$this->ctag = $ctag;
		$this->calendar_id = $calendar_id;
		$this->rbg_color = $rbg_color;
		$this->order = $order;
	}
	
	function __toString () {
		return( '(URL: '.$this->url.'   Ctag: '.$this->ctag.'   Displayname: '.$this->displayname .')'. "\n" );
	}
	
	// Getters
	
	function getURL () {
		return $this->url;
	}
	
	function getDisplayName () {
		return $this->displayname;
	}
	
	function getCTag () {
		return $this->ctag;
	}
	
	function getCalendarID () {
		return $this->calendar_id;
	}
	
	function getRBGcolor () {
		return $this->rbg_color;
	}
	
	function getOrder () {
		return $this->order;
	}
	
	
	// Setters
	
	function setURL ( $url ) {
		$this->url = $url;
	}
	
	function setDisplayName ( $displayname ) {
		$this->displayname = $displayname;
	}
	
	function setCtag ( $ctag ) {
		$this->ctag = $ctag;
	}
	
	function setCalendarID ( $calendar_id ) {
		$this->calendar_id = $calendar_id;
	}
	
	function setRBGcolor ( $rbg_color ) {
		$this->rbg_color = $rbg_color;
	}
	
	function setOrder ( $order ) {
		$this->order = $order;
	}
}

?>