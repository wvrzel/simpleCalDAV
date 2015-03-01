<?php
/**
 * CalDAVFilter
 *
 * Copyright 2014 Michael Palm <palm.michael@gmx.de>
 * 
 * This class represents a filter, which can be used to get a custom report of
 * calendar resources (events, todos, etc.) from the CalDAV-Server
 * 
 * resourceType: The type of resource you want to get. Has to be either
 *               "VEVENT", "VTODO", "VJOURNAL", "VFREEBUSY" or "VALARM".
 *               You have to decide.
 *
 * mustInclude(),
 * mustIncludeMatchSubstr(),
 * mustOverlapWithTimerange(): Use these functions for further filter-options
 *
 * toXML(): Transforms the filter to xml-code for the server. Used to pass as
 *          argument for SimpleCalDAVClient->getCustomReport()
 *
 * @package simpleCalDAV
 *
 */

class CalDAVFilter {
	private $resourceType;
	private $mustIncludes = array();
    
    /*
     * @param $type The type of resource you want to get. Has to be either
     *               "VEVENT", "VTODO", "VJOURNAL", "VFREEBUSY" or "VALARM".
     *               You have to decide.
     */
    public function __construct ( $type ) {
		$this->resourceType = $type;
	}
	
	/**
	 * function mustInclude()
	 * Specifies that a certin property has to be included. The content of the
     * property is irrelevant.
     *
     * Only call this function and mustIncludeMatchSubstr() once per property!
	 * 
	 * Examples:
     * mustInclude("SUMMARY"); specifies that all returned resources have to
     * have the SUMMARY-property.
     * mustInclude("LOCATION "); specifies that all returned resources have to
     * have the LOCATION-property.
	 * 
	 * Arguments:
	 * @param $field The name of the property. For a full list of valid
     *               property names see http://www.rfcreader.com/#rfc5545_line3622
     *               Note that the server might not support all of them.
     * @param $inverse Makes the effect inverse: The resource must NOT include
     *                 the property $field
	 */
    public function mustInclude ( $field, $inverse = FALSE ) {
        $this->mustIncludes[] = array("mustInclude", $field, $inverse);
    }
    
    /**
	 * function mustIncludeMatchSubstr()
	 * Specifies that a certin property has to be included and that its value
     * has to match a given substring.
     *
     * Only call this function and mustInclude() once per property!
	 * 
	 * Examples:
     * mustIncludeMatchSubstr("SUMMARY", "a part of the summary"); would return
     * a resource with "SUMMARY:This is a part of the summary" included, but no
     * resource with "SUMMARY:This is a part of the".
	 * 
	 * Arguments:
	 * @param $field The name of the property. For a full list of valid
     *               property names see http://www.rfcreader.com/#rfc5545_line3622
     *               Note that the server might not support all of them.
     * @param $substring Substring to match against the value of the property.
     * @param $inverse Makes the effect inverse: The property value must NOT
     *                 include the $substring
	 */
    public function mustIncludeMatchSubstr ( $field, $substring, $inverse = FALSE ) {
        $this->mustIncludes[] = array("mustIncludeMatchSubstr", $field, $substring, $inverse);
    }
    
    /**
	 * function mustOverlapWithTimerange()
	 * Specifies that the resource has to overlap with a given timerange.
     * @see http://www.rfcreader.com/#rfc4791_line3944
     *
     * Only call this function once per CalDAVFilter-object!
	 * 
	 * Arguments:
	 * @param $start The starting point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
	 *              	GMT. If omitted the value is set to -infinity.
	 * @param $end The end point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
	 *              	GMT. If omitted the value is set to +infinity.
	 */
    public function mustOverlapWithTimerange ( $start = NULL, $end = NULL) {
        // Are $start and $end in the correct format?
		if ( ( isset($start) and ! preg_match( '#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $start, $matches ) )
		  or ( isset($end) and ! preg_match( '#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $end, $matches ) ) )
		{ trigger_error('$start or $end are in the wrong format. They must have the format yyyymmddThhmmssZ and should be in GMT', E_USER_ERROR); }
        
        $this->mustIncludes[] = array("mustOverlapWithTimerange", $start, $end);
    }
    
    /**
     * Transforms the filter to xml-code for the server. Used to pass as
     * argument for SimpleCalDAVClient->getCustomReport()
     *
     * Example:
     * $simpleCalDAVClient->getCustomReport($filter->toXML());
     *
     * @see SimpleCalDAVClient.php
     */
    public function toXML () {
        $xml = '<C:comp-filter name="VCALENDAR"><C:comp-filter name="'.$this->resourceType.'">';
    
        foreach($this->mustIncludes as $filter) {
            switch($filter[0]) {
                case "mustInclude":
                $xml .= '<C:prop-filter name="'.$filter[1].'"';
                if(!$filter[2]) $xml .=  '/>';
                else $xml .=  '><C:is-not-defined/></C:prop-filter>';
                break;
                
                case "mustIncludeMatchSubstr":
                $xml .= '<C:prop-filter name="'.$filter[1].'"><C:text-match';
                if($filter[3]) $xml .= ' negate-condition="yes"';
                $xml .= '>'.$filter[2].'</C:text-match></C:prop-filter>';
                break;
                
                case "mustOverlapWithTimerange":
                if($this->resourceType == "VTODO") $xml .= '<C:comp-filter name="VALARM">';
                $xml .= '<C:time-range';
                if($filter[1] != NULL) $xml .= ' start="'.$filter[1].'"';
                if($filter[2] != NULL) $xml .= ' end="'.$filter[2].'"';
                $xml .= '/>';
                if($this->resourceType == "VTODO") $xml .= '</C:comp-filter>';
                break;
            }
        }
        
        $xml .= '</C:comp-filter></C:comp-filter>';
        
        return $xml;
    }
}

?>