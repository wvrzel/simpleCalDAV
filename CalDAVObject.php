<?php
/**
 * CalDAVObject
 *
 * Copyright 2014 Michael Palm <palm.michael@gmx.de>
 * 
 * This class represents a calendar resource on the CalDAV-Server (event, todo, etc.)
 * 
 * href: The link to the resource in the calendar
 * data: The iCalendar-Data. The "heart" of the resource.
 * etag: The entity tag is a unique identifier, not only of a resource
 *           like the unique ID, but of serveral versions of the same resource. This means that a resource with one unique
 *           ID can have many different entity tags, depending on the content of the resource. One version of a resource,
 *           i. e. one special description in combination with one special starting time, created at one specific time,
 *           etc., has exactly on unique entity tag.
 *           The assignment of an entity tag ensures, that you know what you are changing/deleting. It ensures, that no one
 *           changed the resource between your viewing of the resource and your change/delete-request. Assigning an entity tag
 *           provides you of accidently destroying the work of others.
 * 
 * @package simpleCalDAV
 *
 */

class CalDAVObject {
	private $href;
	private $data;
	private $etag;
	
	public function __construct ($href, $data, $etag) {
		$this->href = $href;
		$this->data = $data;
		$this->etag = $etag;
	}
	
	
	// Getter
	
	public function getHref () {
		return $this->href;
	}
	
	public function getData () {
		return $this->data;
	}
	
	public function getEtag () {
		return $this->etag;
	}
}

?>