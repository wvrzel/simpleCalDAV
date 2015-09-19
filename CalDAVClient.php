<?php
/**
 * Copyright 2014 Michael Palm <palm.michael@gmx.de>
 *
 * This file is heavily based on AgenDAV caldav-client-v2.php by 
 * Jorge López Pérez <jorge@adobo.org> which is again heavily based
 * on DAViCal caldav-client-v2.php by Andrew McMillan
 * <andrew@mcmillan.net.nz>
 * 
 * @package simpleCalDAV
 */

require_once('CalDAVCalendar.php');
require_once('include/XMLDocument.php');



class CalDAVClient {
  /**
  * Server, username, password, calendar
  *
  * @var string
  */
  protected $base_url, $user, $pass, $entry, $protocol, $server, $port;

  /**
  * The principal-URL we're using
  */
  protected $principal_url;

  /**
  * The calendar-URL we're using
  */
  public $calendar_url;

  /**
  * The calendar-home-set we're using
  */
  protected $calendar_home_set;

  /**
  * The calendar_urls we have discovered
  */
  protected $calendar_urls;

  /**
  * The useragent which is send to the caldav server
  *
  * @var string
  */
  public $user_agent = 'simpleCalDAVclient';
  
  protected $headers = array();
  protected $body = "";
  protected $requestMethod = "GET";
  protected $httpRequest = "";  // for debugging http headers sent
  protected $xmlRequest = "";   // for debugging xml sent
  protected $httpResponse = ""; // http headers received
  protected $xmlResponse = "";  // xml received
  protected $httpResultCode = "";

  protected $parser; // our XML parser object

  // Requests timeout
  private $timeout;

  // cURL handle
  private $ch;

  // Full URL
  private $full_url;
  
  // First part of the full url
  public $first_url_part;

  /**
   * Constructor
   *
   * Valid options are:
   *
   *  $options['auth'] : Auth type. Can be any of values for
   *   CURLOPT_HTTPAUTH (from
   *   http://www.php.net/manual/es/function.curl-setopt.php). Default:
   *   basic or digest
   *
   *  $options['timeout'] : Timeout in seconds
   */

  // TODO: proxy options, interface used,
  function __construct( $base_url, $user, $pass, $options = array()) {
      $this->user = $user;
      $this->pass = $pass;
      $this->headers = array();

      if ( preg_match( '#^((https?)://([a-z0-9.-]+)(:([0-9]+))?)(/.*)$#', $base_url, $matches ) ) {
          $this->server = $matches[3];
          $this->base_url = $matches[6];
          if ( $matches[2] == 'https' ) {
              $this->protocol = 'ssl';
              $this->port = 443;
          }
          else {
              $this->protocol = 'tcp';
              $this->port = 80;
          }
          if ( $matches[4] != '' ) {
              $this->port = intval($matches[5]);
          }
      } else {
          trigger_error("Invalid URL: '".$base_url."'", E_USER_ERROR);
      }

      $this->timeout = isset($options['timeout']) ? 
          $options['timeout'] : 10;
      $this->ch = curl_init();
      curl_setopt_array($this->ch, array(
                  CURLOPT_CONNECTTIMEOUT => $this->timeout,
                  CURLOPT_FAILONERROR => FALSE,
                  CURLOPT_MAXREDIRS => 2,
                  CURLOPT_FORBID_REUSE => FALSE,
                  CURLOPT_RETURNTRANSFER => TRUE,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_HTTPAUTH =>
                  isset($options['auth']) ?  $options['auth'] :
                  (CURLAUTH_BASIC | CURLAUTH_DIGEST),
                  CURLOPT_USERAGENT => 'cURL based CalDAV client',
                  CURLINFO_HEADER_OUT => TRUE,
                  CURLOPT_HEADER => TRUE,
                  CURLOPT_SSL_VERIFYPEER => FALSE
                  ));

      $this->full_url = $base_url;
      $this->first_url_part = $matches[1];
  }

  /**
   * Check with OPTIONS if calendar-access is enabled
   * 
   * Can be used to check authentication against server
   *
   */
  function isValidCalDAVServer() {
      // Clean headers
      $this->headers = array();
      $dav_options = $this->DoOptionsRequestAndGetDAVHeader();
      $valid_caldav_server = isset($dav_options['calendar-access']);

      return $valid_caldav_server;
  }

  /**
   * Issues an OPTIONS request
   *
   * @param string $url The URL to make the request to
   *
   * @return array DAV options
   */
  function DoOptionsRequestAndGetDAVHeader( $url = null ) {
      $this->requestMethod = "OPTIONS";
      $this->body = "";
      $headers = $this->DoRequest($url);

      $result = array();

      $headers = preg_split('/\r?\n/', $headers);

      // DAV header(s)
      $dav_header = preg_grep('/^DAV:/', $headers);
      if (is_array($dav_header)) {
          $dav_header = array_values($dav_header);
          $dav_header = preg_replace('/^DAV: /', '', $dav_header);

          $dav_options = array();

          foreach ($dav_header as $d) {
              $dav_options = array_merge($dav_options,
                      array_flip(preg_split('/[, ]+/', $d)));
          }

          $result = $dav_options;

      }

      return $result;
  }


  /**
   * Adds an If-Match or If-None-Match header
   *
   * @param bool $match to Match or Not to Match, that is the question!
   * @param string $etag The etag to match / not match against.
   */
  function SetMatch( $match, $etag = '*' ) {
      $this->headers['match'] = sprintf( "%s-Match: \"%s\"", ($match ? "If" : "If-None"), $etag);
  }

  /**
   * Add a Depth: header.  Valid values are 0, 1 or infinity
   *
   * @param int $depth  The depth, default to infinity
   */
  function SetDepth( $depth = '0' ) {
      $this->headers['depth'] = 'Depth: '. ($depth == '1' ? "1" : ($depth == 'infinity' ? $depth : "0") );
  }

  /**
   * Add a Depth: header.  Valid values are 1 or infinity
   *
   * @param int $depth  The depth, default to infinity
   */
  function SetUserAgent( $user_agent = null ) {
      $this->user_agent = $user_agent;
      curl_setopt($this->ch, CURLOPT_USERAGENT, $user_agent);
  }

  /**
   * Add a Content-type: header.
   *
   * @param string $type  The content type
   */
  function SetContentType( $type ) {
      $this->headers['content-type'] = "Content-type: $type";
  }

  /**
   * Set the calendar_url we will be using for a while.
   *
   * @param string $url The calendar_url
   */
  function SetCalendar( $url ) {
      $this->calendar_url = $url;
  }

  /**
   * Split response into httpResponse and xmlResponse
   *
   * @param string Response from server
   */
  function ParseResponse( $response ) {
      $pos = strpos($response, '<?xml');
      if ($pos === false) {
          $this->httpResponse = trim($response);
      }
      else {
          $this->httpResponse = trim(substr($response, 0, $pos));
          $this->xmlResponse = trim(substr($response, $pos));
          $this->xmlResponse = preg_replace('{>[^>]*$}s', '>',$this->xmlResponse );
          $parser = xml_parser_create_ns('UTF-8');
          xml_parser_set_option ( $parser, XML_OPTION_SKIP_WHITE, 1 );
          xml_parser_set_option ( $parser, XML_OPTION_CASE_FOLDING, 0 );

          if ( xml_parse_into_struct( $parser, $this->xmlResponse, $this->xmlnodes, $this->xmltags ) === 0 ) {
              //printf( "XML parsing error: %s - %s\n", xml_get_error_code($parser), xml_error_string(xml_get_error_code($parser)) );
              //        debug_print_backtrace();
              //        echo "\nNodes array............................................................\n"; print_r( $this->xmlnodes );
              //        echo "\nTags array............................................................\n";  print_r( $this->xmltags );
              //printf( "\nXML Reponse:\n%s\n", $this->xmlResponse );
              log_message('ERROR', 'XML parsing error: ' 
                      . xml_get_error_code($parser) . ', ' 
                      . xml_error_string(xml_get_error_code($parser)));
          }

          xml_parser_free($parser);
      }
  }

  /**
   * Parse response headers 
   */
  function ParseResponseHeaders($headers) {
      $lines = preg_split('/[\r\n]+/', $headers);
      $this->httpResultCode = preg_replace('/^[\S]+ (\d+).+$/', '\1',
              $lines[0]);
  }

  /**
   * Output http request headers
   *
   * @return HTTP headers
   */
  function GetHttpRequest() {
      return $this->httpRequest;
  }
  /**
   * Output http response headers
   *
   * @return HTTP headers
   */
  function GetResponseHeaders() {
      return $this->httpResponseHeaders;
  }
  /**
   * Output http response body
   *
   * @return HTTP body
   */
  function GetResponseBody() {
      return $this->httpResponseBody;
  }
  /**
   * Output request body
   *
   * @return raw xml
   */
  function GetBody() {
      return $this->body;
  }
  /**
   * Output xml response
   *
   * @return raw xml
   */
  function GetXmlResponse() {
      return $this->xmlResponse;
  }
  /**
   * Output HTTP status code
   *
   * @return string HTTP status code
   */
  function GetHttpResultCode() {
      return $this->httpResultCode;
  }

  /**
   * Send a request to the server
   *
   * @param string $url The URL to make the request to
   *
   * @return string The content of the response from the server
   */
  function DoRequest( $url = null ) {
      if (is_null($url)) {
          $url = $this->full_url;
      }

      $this->request_url = $url;

      curl_setopt($this->ch, CURLOPT_URL, $url);

      // Request method
      curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $this->requestMethod);

      // Empty body. If not used, cURL will spend ~5s on this request
      if ($this->requestMethod == 'HEAD' || empty($this->body) ) {
          curl_setopt($this->ch, CURLOPT_NOBODY, TRUE);
      } else {
          curl_setopt($this->ch, CURLOPT_NOBODY, FALSE);
      }

      // Headers
      if (!isset($this->headers['content-type'])) $this->headers['content-type'] = "Content-type: text/plain";

      // Remove cURL generated 'Expect: 100-continue'
      $this->headers['disable_expect'] = 'Expect:';
      curl_setopt($this->ch, CURLOPT_HTTPHEADER,
              array_values($this->headers));
			  
      curl_setopt($this->ch, CURLOPT_USERPWD, $this->user . ':' .
              $this->pass);

      // Request body
      curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->body);
	  
	  // Save Request
	  curl_setopt($this->ch, CURLINFO_HEADER_OUT, TRUE);

      $response = curl_exec($this->ch);

      if (FALSE === $response) {
          // TODO better error handling
          log_message('ERROR', 'Error requesting ' . $url . ': ' 
                  . curl_error($this->ch));
          return false;
      }

      $info = curl_getinfo($this->ch);
	  
	  // Save request
	  $this->httpRequest = $info['request_header'];

      // Get headers (idea from SabreDAV WebDAV client)
      $this->httpResponseHeaders = substr($response, 0, $info['header_size']);
      $this->httpResponseBody = substr($response, $info['header_size']);

      // Get only last headers (needed when using unspecific HTTP auth
      // method or request got redirected)
      $this->httpResponseHeaders = preg_replace('/^.+\r\n\r\n(.+)/sU', '$1',
              $this->httpResponseHeaders);

      // Parse response
      $this->ParseResponseHeaders($this->httpResponseHeaders);
      $this->ParseResponse($this->httpResponseBody);

      //TODO debug

      /*
      log_message('INTERNALS', 'REQh: ' . var_export($info['request_header'], TRUE));
      log_message('INTERNALS', 'REQb: ' . var_export($this->body, TRUE));
      log_message('INTERNALS', 'RPLh: ' . var_export($this->httpResponseHeaders, TRUE));
      log_message('INTERNALS', 'RPLb: ' . var_export($this->httpResponseBody, TRUE));
      */

      return $response;
  }

  /**
   * Send an OPTIONS request to the server
   *
   * @param string $url The URL to make the request to
   *
   * @return array The allowed options
   */
  function DoOptionsRequest( $url = null ) {
      $this->requestMethod = "OPTIONS";
      $this->body = "";
      $headers = $this->DoRequest($url);
      $options_header = preg_replace( '/^.*Allow: ([a-z, ]+)\r?\n.*/is', '$1', $headers );
      $options = array_flip( preg_split( '/[, ]+/', $options_header ));
      return $options;
  }



  /**
   * Send an XML request to the server (e.g. PROPFIND, REPORT, MKCALENDAR)
   *
   * @param string $method The method (PROPFIND, REPORT, etc) to use with the request
   * @param string $xml The XML to send along with the request
   * @param string $url The URL to make the request to
   *
   * @return array An array of the allowed methods
   */
  function DoXMLRequest( $request_method, $xml, $url = null ) {
      $this->body = $xml;
      $this->requestMethod = $request_method;
      $this->SetContentType("text/xml");
      return $this->DoRequest($url);
  }



  /**
   * Get a single item from the server.
   *
   * @param string $url The URL to GET
   */
  function DoGETRequest( $url ) {
      $this->body = "";
      $this->requestMethod = "GET";
      return $this->DoRequest( $url );
  }


  /**
   * Get the HEAD of a single item from the server.
   *
   * @param string $url The URL to HEAD
   */
  function DoHEADRequest( $url ) {
      $this->body = "";
      $this->requestMethod = "HEAD";
      return $this->DoRequest( $url );
  }


  /**
   * PUT a text/icalendar resource, returning the etag
   *
   * @param string $url The URL to make the request to
   * @param string $icalendar The iCalendar resource to send to the server
   * @param string $etag The etag of an existing resource to be overwritten, or '*' for a new resource.
   *
   * @return string The content of the response from the server
   */
  function DoPUTRequest( $url, $icalendar, $etag = null ) {
      $this->body = $icalendar;

      $this->requestMethod = "PUT";
      if ( $etag != null ) {
          $this->SetMatch( ($etag != '*'), $etag );
      }
      $this->SetContentType('text/calendar; encoding="utf-8"');
      $this->DoRequest($url);

      $etag = null;
      if ( preg_match( '{^ETag:\s+"([^"]*)"\s*$}im', $this->httpResponseHeaders, $matches ) ) $etag = $matches[1];
	  else if ( preg_match( '{^ETag:\s+([^\s]*)\s*$}im', $this->httpResponseHeaders, $matches ) ) $etag = $matches[1];
      if ( !isset($etag) || $etag == '' ) {
          // Try with HEAD
          $save_request = $this->httpRequest;
          $save_response_headers = $this->httpResponseHeaders;
          $save_http_result = $this->httpResultCode;
          $this->DoHEADRequest( $url );
          if ( preg_match( '{^Etag:\s+"([^"]*)"\s*$}im', $this->httpResponseHeaders, $matches ) ) $etag = $matches[1];
		  else if ( preg_match( '{^ETag:\s+([^\s]*)\s*$}im', $this->httpResponseHeaders, $matches ) ) $etag = $matches[1];
          /*
             if ( !isset($etag) || $etag == '' ) {
             printf( "Still No etag in:\n%s\n", $this->httpResponseHeaders );
             }
           */
          $this->httpRequest = $save_request;
          $this->httpResponseHeaders = $save_response_headers;
          $this->httpResultCode = $save_http_result;
      }
      return $etag;
  }


  /**
   * DELETE a text/icalendar resource
   *
   * @param string $url The URL to make the request to
   * @param string $etag The etag of an existing resource to be deleted, or '*' for any resource at that URL.
   *
   * @return int The HTTP Result Code for the DELETE
   */
  function DoDELETERequest( $url, $etag = null ) {
      $this->body = "";

      $this->requestMethod = "DELETE";
      if ( $etag != null ) {
          $this->SetMatch( true, $etag );
      }
      $this->DoRequest($url);
      return $this->httpResultCode;
  }


  /**
   * Get a single item from the server.
   *
   * @param string $url The URL to PROPFIND on
   */
  function DoPROPFINDRequest( $url, $props, $depth = 0 ) {
      $this->SetDepth($depth);
      $xml = new XMLDocument( array( 'DAV:' => '', 'urn:ietf:params:xml:ns:caldav' => 'C' ) );
      $prop = new XMLElement('prop');
      foreach( $props AS $v ) {
          $xml->NSElement($prop,$v);
      }

      $this->body = $xml->Render('propfind',$prop );

      $this->requestMethod = 'PROPFIND';
      $this->SetContentType('text/xml');
      $this->DoRequest($url);
      return $this->GetXmlResponse();
  }


  /**
   * Get/Set the Principal URL
   *
   * @param $url string The Principal URL to set
   */
  function PrincipalURL( $url = null ) {
      if ( isset($url) ) {
          $this->principal_url = $url;
      }
      return $this->principal_url;
  }


  /**
   * Get/Set the calendar-home-set URL
   *
   * @param $url array of string The calendar-home-set URLs to set
   */
  function CalendarHomeSet( $urls = null ) {
      if ( isset($urls) ) {
          if ( ! is_array($urls) ) $urls = array($urls);
          $this->calendar_home_set = $urls;
      }
      return $this->calendar_home_set;
  }


  /**
   * Get/Set the calendar-home-set URL
   *
   * @param $urls array of string The calendar URLs to set
   */
  function CalendarUrls( $urls = null ) {
      if ( isset($urls) ) {
          if ( ! is_array($urls) ) $urls = array($urls);
          $this->calendar_urls = $urls;
      }
      return $this->calendar_urls;
  }


  /**
   * Return the first occurrence of an href inside the named tag.
   *
   * @param string $tagname The tag name to find the href inside of
   */
  function HrefValueInside( $tagname ) {
      foreach( $this->xmltags[$tagname] AS $k => $v ) {
          $j = $v + 1;
          if ( $this->xmlnodes[$j]['tag'] == 'DAV::href' ) {
              return rawurldecode($this->xmlnodes[$j]['value']);
          }
      }
      return null;
  }


  /**
   * Return the href containing this property.  Except only if it's inside a status != 200
   *
   * @param string $tagname The tag name of the property to find the href for
   * @param integer $which Which instance of the tag should we use
   */
  function HrefForProp( $tagname, $i = 0 ) {
      if ( isset($this->xmltags[$tagname]) && isset($this->xmltags[$tagname][$i]) ) {
          $j = $this->xmltags[$tagname][$i];
          while( $j-- > 0 && $this->xmlnodes[$j]['tag'] != 'DAV::href' ) {
              //        printf( "Node[$j]: %s\n", $this->xmlnodes[$j]['tag']);
              if ( $this->xmlnodes[$j]['tag'] == 'DAV::status' && $this->xmlnodes[$j]['value'] != 'HTTP/1.1 200 OK' ) return null;
          }
          //      printf( "Node[$j]: %s\n", $this->xmlnodes[$j]['tag']);
          if ( $j > 0 && isset($this->xmlnodes[$j]['value']) ) {
              //        printf( "Value[$j]: %s\n", $this->xmlnodes[$j]['value']);
              return rawurldecode($this->xmlnodes[$j]['value']);
          }
      }
      else {
          // printf( "xmltags[$tagname] or xmltags[$tagname][$i] is not set\n");
      }
      return null;
  }


  /**
   * Return the href which has a resourcetype of the specified type
   *
   * @param string $tagname The tag name of the resourcetype to find the href for
   * @param integer $which Which instance of the tag should we use
   */
  function HrefForResourcetype( $tagname, $i = 0 ) {
      if ( isset($this->xmltags[$tagname]) && isset($this->xmltags[$tagname][$i]) ) {
          $j = $this->xmltags[$tagname][$i];
          while( $j-- > 0 && $this->xmlnodes[$j]['tag'] != 'DAV::resourcetype' );
          if ( $j > 0 ) {
              while( $j-- > 0 && $this->xmlnodes[$j]['tag'] != 'DAV::href' );
              if ( $j > 0 && isset($this->xmlnodes[$j]['value']) ) {
                  return rawurldecode($this->xmlnodes[$j]['value']);
              }
          }
      }
      return null;
  }


  /**
   * Return the <prop> ... </prop> of a propstat where the status is OK
   *
   * @param string $nodenum The node number in the xmlnodes which is the href
   */
  function GetOKProps( $nodenum ) {
      $props = null;
      $level = $this->xmlnodes[$nodenum]['level'];
      $status = '';
      while ( $this->xmlnodes[++$nodenum]['level'] >= $level ) {
          if ( $this->xmlnodes[$nodenum]['tag'] == 'DAV::propstat' ) {
              if ( $this->xmlnodes[$nodenum]['type'] == 'open' ) {
                  $props = array();
                  $status = '';
              }
              else {
                  if ( $status == 'HTTP/1.1 200 OK' ) break;
              }
          }
          elseif ( !isset($this->xmlnodes[$nodenum]) || !is_array($this->xmlnodes[$nodenum]) ) {
              break;
          }
          elseif ( $this->xmlnodes[$nodenum]['tag'] == 'DAV::status' ) {
              $status = $this->xmlnodes[$nodenum]['value'];
          }
          else {
              $props[] = $this->xmlnodes[$nodenum];
          }
      }
      return $props;
  }


  /**
   * Attack the given URL in an attempt to find a principal URL
   *
   * @param string $url The URL to find the principal-URL from
   */
  function FindPrincipal( $url = null ) {
      $xml = $this->DoPROPFINDRequest( $url, array('resourcetype', 'current-user-principal', 'owner', 'principal-URL',
                  'urn:ietf:params:xml:ns:caldav:calendar-home-set'), 1);

      $principal_url = $this->HrefForProp('DAV::principal');

      if ( !isset($principal_url) ) {
          foreach( array('DAV::current-user-principal', 'DAV::principal-URL', 'DAV::owner') AS $href ) {
              if ( !isset($principal_url) ) {
                  $principal_url = $this->HrefValueInside($href);
              }
          }
      }

      return $this->PrincipalURL($principal_url);
  }


  /**
   * Attack the given URL in an attempt to find the calendar-home-url of the current principal
   *
   * @param string $url The URL to find the calendar-home-set from
   */
  function FindCalendarHome( $recursed=false ) {
      if ( !isset($this->principal_url) ) {
          $this->FindPrincipal();
      }
      if ( $recursed ) {
          $this->DoPROPFINDRequest( $this->first_url_part.$this->principal_url, array('urn:ietf:params:xml:ns:caldav:calendar-home-set'), 0);
      }

      $calendar_home = array();
      foreach( $this->xmltags['urn:ietf:params:xml:ns:caldav:calendar-home-set'] AS $k => $v ) {
          if ( $this->xmlnodes[$v]['type'] != 'open' ) continue;
          while( $this->xmlnodes[++$v]['type'] != 'close' && $this->xmlnodes[$v]['tag'] != 'urn:ietf:params:xml:ns:caldav:calendar-home-set' ) {
              //        printf( "Tag: '%s' = '%s'\n", $this->xmlnodes[$v]['tag'], $this->xmlnodes[$v]['value']);
              if ( $this->xmlnodes[$v]['tag'] == 'DAV::href' && isset($this->xmlnodes[$v]['value']) )
                  $calendar_home[] = rawurldecode($this->xmlnodes[$v]['value']);
          }
      }

      if ( !$recursed && count($calendar_home) < 1 ) {
          $calendar_home = $this->FindCalendarHome(true);
      }

      return $this->CalendarHomeSet($calendar_home);
  }

  /*
   * Find own calendars
   */
  function FindCalendars( $recursed=false ) {
      if ( !isset($this->calendar_home_set[0]) ) {
          $this->FindCalendarHome($recursed);
      }
      $properties = 
          array(
                  'resourcetype',
                  'displayname',
                  'http://calendarserver.org/ns/:getctag',
                  'http://apple.com/ns/ical/:calendar-color',
                  'http://apple.com/ns/ical/:calendar-order',
               );
      $this->DoPROPFINDRequest( $this->first_url_part.$this->calendar_home_set[0], $properties, 1);
	  
      return $this->parse_calendar_info();
  }

  /**
   * Do a PROPFIND on a calendar and retrieve its information
   */
  function GetCalendarDetailsByURL($url) {
      $properties = 
          array(
                  'resourcetype',
                  'displayname',
                  'http://calendarserver.org/ns/:getctag',
                  'http://apple.com/ns/ical/:calendar-color',
                  'http://apple.com/ns/ical/:calendar-order',
               );
      $this->DoPROPFINDRequest($url, $properties, 0);

      return $this->parse_calendar_info();
  }

  /**
   * Find the calendars, from the calendar_home_set
   */
  function GetCalendarDetails( $url = null ) {
      if ( isset($url) ) $this->SetCalendar($url);

      $calendar_properties = array( 'resourcetype', 'displayname', 'http://calendarserver.org/ns/:getctag', 'urn:ietf:params:xml:ns:caldav:calendar-timezone', 'supported-report-set' );
      $this->DoPROPFINDRequest( $this->calendar_url, $calendar_properties, 0);

      $hnode = $this->xmltags['DAV::href'][0];
      $href = rawurldecode($this->xmlnodes[$hnode]['value']);

      $calendar = new CalDAVCalendar($href);
      $ok_props = $this->GetOKProps($hnode);
      foreach( $ok_props AS $k => $v ) {
          $name = preg_replace( '{^.*:}', '', $v['tag'] );
          if ( isset($v['value'] ) ) {
              $calendar->{$name} = $v['value'];
          }
          /*      else {
                  printf( "Calendar property '%s' has no text content\n", $v['tag'] );
                  }*/
      }

      return $calendar;
  }


  /**
   * Get all etags for a calendar
   */
  function GetCollectionETags( $url = null ) {
      if ( isset($url) ) $this->SetCalendar($url);

      $this->DoPROPFINDRequest( $this->calendar_url, array('getetag'), 1);

      $etags = array();
      if ( isset($this->xmltags['DAV::getetag']) ) {
          foreach( $this->xmltags['DAV::getetag'] AS $k => $v ) {
              $href = $this->HrefForProp('DAV::getetag', $k);
              if ( isset($href) && isset($this->xmlnodes[$v]['value']) ) $etags[$href] = $this->xmlnodes[$v]['value'];
          }
      }

      return $etags;
  }


  /**
   * Get a bunch of events for a calendar with a calendar-multiget report
   */
  function CalendarMultiget( $event_hrefs, $url = null ) {

      if ( isset($url) ) $this->SetCalendar($url);

      $hrefs = '';
      foreach( $event_hrefs AS $k => $href ) {
          $href = str_replace( rawurlencode('/'),'/',rawurlencode($href));
          $hrefs .= '<href>'.$href.'</href>';
      }
      $this->body = <<<EOXML
<?xml version="1.0" encoding="utf-8" ?>
<C:calendar-multiget xmlns="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
<prop><getetag/><C:calendar-data/></prop>
$hrefs
</C:calendar-multiget>
EOXML;

      $this->requestMethod = "REPORT";
      $this->SetContentType("text/xml");
      $response = $this->DoRequest( $this->calendar_url );
      
	  $report = array();
      foreach( $this->xmlnodes as $k => $v ) {
          switch( $v['tag'] ) {
              case 'DAV::response':
                  if ( $v['type'] == 'open' ) {
                      $response = array();
                  }
                  elseif ( $v['type'] == 'close' ) {
                      $report[] = $response;
                  }
                  break;
              case 'DAV::href':
                  $response['href'] = basename( rawurldecode($v['value']) );
                  break;
              case 'DAV::getetag':
                  $response['etag'] = preg_replace('/^"?([^"]+)"?/', '$1', $v['value']);
                  break;
              case 'urn:ietf:params:xml:ns:caldav:calendar-data':
                        $response['data'] = $v['value'];
                        break;
          }
      }
	  
      return $report;
  }


  /**
   * Given XML for a calendar query, return an array of the events (/todos) in the
   * response.  Each event in the array will have a 'href', 'etag' and '$response_type'
   * part, where the 'href' is relative to the calendar and the '$response_type' contains the
   * definition of the calendar data in iCalendar format.
   *
   * @param string $filter XML fragment which is the <filter> element of a calendar-query
   * @param string $url The URL of the calendar, or null to use the 'current' calendar_url
   *
   * @return array An array of the relative URLs, etags, and events from the server.  Each element of the array will
   *               be an array with 'href', 'etag' and 'data' elements, corresponding to the URL, the server-supplied
   *               etag (which only varies when the data changes) and the calendar data in iCalendar format.
   */
  function DoCalendarQuery( $filter, $url = null ) {

      if ( isset($url) ) $this->SetCalendar($url);

      $this->body = <<<EOXML
<?xml version="1.0" encoding="utf-8" ?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
<D:prop>
<C:calendar-data/>
<D:getetag/>
</D:prop>$filter
</C:calendar-query>
EOXML;

      $this->requestMethod = "REPORT";
      $this->SetContentType("text/xml");
      $this->DoRequest( $this->calendar_url );

      $report = array();
      foreach( $this->xmlnodes as $k => $v ) {
          switch( $v['tag'] ) {
              case 'DAV::response':
                  if ( $v['type'] == 'open' ) {
                      $response = array();
                  }
                  elseif ( $v['type'] == 'close' ) {
                      $report[] = $response;
                  }
                  break;
              case 'DAV::href':
                  $response['href'] = basename( rawurldecode($v['value']) );
                  break;
              case 'DAV::getetag':
                  $response['etag'] = preg_replace('/^"?([^"]+)"?/', '$1', $v['value']);
                  break;
              case 'urn:ietf:params:xml:ns:caldav:calendar-data':
                        $response['data'] = $v['value'];
                        break;
          }
      }
      return $report;
  }


  /**
   * Get the events in a range from $start to $finish.  The dates should be in the
   * format yyyymmddThhmmssZ and should be in GMT.  The events are returned as an
   * array of event arrays.  Each event array will have a 'href', 'etag' and 'event'
   * part, where the 'href' is relative to the calendar and the event contains the
   * definition of the event in iCalendar format.
   *
   * @param timestamp $start The start time for the period
   * @param timestamp $finish The finish time for the period
   * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default null.
   *
   * @return array An array of the relative URLs, etags, and events, returned from DoCalendarQuery() @see DoCalendarQuery()
   */
  function GetEvents( $start = null, $finish = null, $relative_url = null ) {
      $this->SetDepth('1');
      $filter = "";
      if ( isset($start) && isset($finish) )
          $range = "<C:time-range start=\"$start\" end=\"$finish\"/>";
      elseif ( isset($start) && ! isset($finish) )
          $range = "<C:time-range start=\"$start\"/>";
      elseif ( ! isset($start) && isset($finish) )
          $range = "<C:time-range end=\"$finish\"/>";
      else
          $range = '';

      $filter = <<<EOFILTER
<C:filter>
<C:comp-filter name="VCALENDAR">
<C:comp-filter name="VEVENT">
$range
</C:comp-filter>
</C:comp-filter>
</C:filter>
EOFILTER;

      return $this->DoCalendarQuery($filter, $relative_url);
  }


  /**
   * Get the todo's in a range from $start to $finish.  The dates should be in the
   * format yyyymmddThhmmssZ and should be in GMT.  The events are returned as an
   * array of event arrays.  Each event array will have a 'href', 'etag' and 'event'
   * part, where the 'href' is relative to the calendar and the event contains the
   * definition of the event in iCalendar format.
   *
   * @param timestamp $start The start time for the period
   * @param timestamp $finish The finish time for the period
   * @param boolean   $completed Whether to include completed tasks
   * @param boolean   $cancelled Whether to include cancelled tasks
   * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
   *
   * @return array An array of the relative URLs, etags, and events, returned from DoCalendarQuery() @see DoCalendarQuery()
   */
  function GetTodos( $start = null, $finish = null, $completed = null, $cancelled = null, $relative_url = "" ) {
  	$this->SetDepth('1');
  	
  	if ( isset($start) && isset($finish) )
  		$range = "<C:comp-filter name=\"VALARM\"><C:time-range start=\"$start\" end=\"$finish\"/></C:comp-filter>";
  	elseif ( isset($start) && ! isset($finish) )
  		$range = "<C:comp-filter name=\"VALARM\"><C:time-range start=\"$start\"/></C:comp-filter>";
  	elseif ( ! isset($start) && isset($finish) )
  		$range = "<C:comp-filter name=\"VALARM\"><C:time-range end=\"$finish\"/></C:comp-filter>";
  	else
  		$range = '';

  	
  	// Warning!  May contain traces of double negatives...
  	if(isset($completed) && $completed == true)
  		$completed_filter = '<C:prop-filter name="STATUS"><C:text-match negate-condition="no">COMPLETED</C:text-match></C:prop-filter>';
  	else if(isset($completed) && $completed == false)
  		$completed_filter = '<C:prop-filter name="STATUS"><C:text-match negate-condition="yes">COMPLETED</C:text-match></C:prop-filter>';
  	else
  		$completed_filter = '';
  	
  	if(isset($cancelled) && $cancelled == true)
  		$cancelled_filter = '<C:prop-filter name="STATUS"><C:text-match negate-condition="no">CANCELLED</C:text-match></C:prop-filter>';
  	else if(isset($cancelled) && $cancelled == false)
  		$cancelled_filter = '<C:prop-filter name="STATUS"><C:text-match negate-condition="yes">CANCELLED</C:text-match></C:prop-filter>';
  	else
  		$cancelled_filter = '';
  	
      $filter = <<<EOFILTER
<C:filter>
<C:comp-filter name="VCALENDAR">
<C:comp-filter name="VTODO">
$completed_filter
$cancelled_filter
$range
</C:comp-filter>
</C:comp-filter>
</C:filter>
EOFILTER;

      return $this->DoCalendarQuery($filter);
  }


  /**
   * Get the calendar entry by UID
   *
   * @param uid
   * @param string    $relative_url The URL relative to the base_url specified when the calendar was opened.  Default ''.
   *
   * @return array An array of the relative URL, etag, and calendar data returned from DoCalendarQuery() @see DoCalendarQuery()
   */
  function GetEntryByUid( $uid, $relative_url = null ) {
      $this->SetDepth('1');
      $filter = "";
      if ( $uid ) {
          $filter = <<<EOFILTER
<C:filter>
<C:comp-filter name="VCALENDAR">
<C:comp-filter name="VEVENT">
<C:prop-filter name="UID">
<C:text-match icollation="i;octet">$uid</C:text-match>
</C:prop-filter>
</C:comp-filter>
</C:comp-filter>
</C:filter>
EOFILTER;
      }

      return $this->DoCalendarQuery($filter, $relative_url);
  }


  /**
   * Get the calendar entry by HREF
   *
   * @param string    $href         The href from a call to GetEvents or GetTodos etc.
   *
   * @return string The iCalendar of the calendar entry
   */
  function GetEntryByHref( $href ) {
      //$href = str_replace( rawurlencode('/'),'/',rawurlencode($href));
      $response = $this->DoGETRequest( $href );
	  
	  $report = array();
	  
	  if ( $this->GetHttpResultCode() == '404' ) { return $report; }
	  
      $etag = null;
      if ( preg_match( '{^ETag:\s+"([^"]*)"\s*$}im', $this->httpResponseHeaders, $matches ) ) $etag = $matches[1];
	  else if ( preg_match( '{^ETag:\s+([^\s]*)\s*$}im', $this->httpResponseHeaders, $matches ) ) $etag = $matches[1];
      if ( !isset($etag) || $etag == '' ) {
          // Try with HEAD
          $save_request = $this->httpRequest;
          $save_response_headers = $this->httpResponseHeaders;
          $save_http_result = $this->httpResultCode;
          $this->DoHEADRequest( $href );
          if ( preg_match( '{^Etag:\s+"([^"]*)"\s*$}im', $this->httpResponseHeaders, $matches ) ) $etag = $matches[1];
		  else if ( preg_match( '{^ETag:\s+([^\s]*)\s*$}im', $this->httpResponseHeaders, $matches ) ) $etag = $matches[1];
		  
          /*
             if ( !isset($etag) || $etag == '' ) {
             printf( "Still No etag in:\n%s\n", $this->httpResponseHeaders );
             }
           */
          $this->httpRequest = $save_request;
          $this->httpResponseHeaders = $save_response_headers;
          $this->httpResultCode = $save_http_result;
      }
	  
	  $report = array(array('etag'=>$etag));

      return $report;
  }

  /**
   * Get calendar info after a PROPFIND
   */
  function parse_calendar_info() {
      $calendars = array();
      if ( isset($this->xmltags['urn:ietf:params:xml:ns:caldav:calendar']) ) {
          $calendar_urls = array();
          foreach( $this->xmltags['urn:ietf:params:xml:ns:caldav:calendar'] AS $k => $v ) {
              $calendar_urls[$this->HrefForProp('urn:ietf:params:xml:ns:caldav:calendar', $k)] = 1;
          }

          foreach( $this->xmltags['DAV::href'] AS $i => $hnode ) {
              $href = rawurldecode($this->xmlnodes[$hnode]['value']);

              if ( !isset($calendar_urls[$href]) ) continue;

              //        printf("Seems '%s' is a calendar.\n", $href );


              $calendar = new CalDAVCalendar($href);

              /*
               *  Transform href into calendar
               * /xxxxx/yyyyy/caldav.php/principal/resource/
               *                          t-3       t-2
               */
              $pieces = preg_split('/\//', $href);
              $total = count($pieces);
              $calendar_id = $pieces[$total-2];
              $calendar->setCalendarID($calendar_id);

              $ok_props = $this->GetOKProps($hnode);
              foreach( $ok_props AS $v ) {
                  switch( $v['tag'] ) {
                      case 'http://calendarserver.org/ns/:getctag':
                          $calendar->setCtag((isset($v['value']) ?
                              $v['value'] : '-'));
                          break;
                      case 'DAV::displayname':
                          $calendar->setDisplayName((isset($v['value']) ?
                              $v['value'] : '-'));
                          break;
                      case 'http://apple.com/ns/ical/:calendar-color':
                          $calendar->setRBGcolor((isset($v['value']) ? 
                          		$this->_rgba2rgb($v['value']) : '-'));
                          break;
                      case 'http://apple.com/ns/ical/:calendar-order':
                          $calendar->setOrder((isset($v['value']) ?
                              $v['value'] : '-'));
                          break;
                  }
              }
              $calendars[$calendar->getCalendarID()] = $calendar;
          }
      }

      return $calendars;
  }
  /**
   * Issues a PROPPATCH on a resource
   *
   * @param string    XML request
   * @param string    URL
   * @return          TRUE on success, FALSE otherwise
   */
  function DoPROPPATCH($xml_text, $url) {
      $this->DoXMLRequest('PROPPATCH', $xml_text, $url);

      $errmsg = '';

      if ($this->httpResultCode == '207') {
          $errmsg = $this->httpResultCode;
          // Find propstat tag(s)
          if (isset($this->xmltags['DAV::propstat'])) {
              foreach ($this->xmltags['DAV::propstat'] as $i => $node) {
                  if ($this->xmlnodes[$node]['type'] == 'close') {
                      continue;
                  }
                  // propstat @ $i: open
                  // propstat @ $i + 1: close
                  // Search for prop and status
                  $level = $this->xmlnodes[$node]['level'];
                  $level++;

                  while ($this->xmlnodes[++$node]['level'] >= $level) {
                      if ($this->xmlnodes[$node]['tag'] == 'DAV::status'
                              && $this->xmlnodes[$node]['value'] !=
                              'HTTP/1.1 200 OK') {
                          return $this->xmlnodes[$node]['value'];
                      }
                  }
              }
          }
      } else if ($this->httpResultCode != 200) {
          return 'Unknown HTTP code';
      }

      return TRUE;
  }

  /**
   * Queries server using a principal-property search
   *
   * @param string    XML request
   * @param string    URL
   * @return          FALSE on error, array with results otherwise
   */
  function principal_property_search($xml_text, $url) {
      $result = array();
      $this->DoXMLRequest('REPORT', $xml_text, $url);

      if ($this->httpResultCode == '207') {
          $errmsg = $this->httpResultCode;
          // Find response tag(s)
          if (isset($this->xmltags['DAV::response'])) {
              foreach ($this->xmltags['DAV::response'] as $i => $node) {
                  if ($this->xmlnodes[$node]['type'] == 'close') {
                      continue;
                  }

                  $result[$i]['href'] =
                      $this->HrefForProp('DAV::response', $i+1);

                  $level = $this->xmlnodes[$node]['level'];
                  $level++;

                  $ok_props = $this->GetOKProps($node);

                  foreach ($ok_props as $v) {
                      switch($v['tag']) {
                          case 'DAV::displayname':
                              $result[$i]['displayname'] =
                                  isset($v['value']) ? $v['value'] : '';
                              break;
                          case 'DAV::email':
                              $result[$i]['email'] = 
                                  isset($v['value']) ? $v['value'] : '';
                              break;
                      }
                  }

              }
          }
      } else if ($this->httpResultCode != 200) {
          return 'Unknown HTTP code';
      }

      return $result;
  }

  /**
   * Converts a RGBA hexadecimal string (#rrggbbXX) to RGB
   */
  private function _rgba2rgb($s) {
      if (strlen($s) == '9') {
          return substr($s, 0, 7);
      } else {
          // Unknown string
          return $s;
      }
  }
  
	public function printLastMessages() {
		$string = '';
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = FALSE;
		$dom->formatOutput = TRUE;
		
		$string .= '<pre>';
		$string .= 'last request:<br><br>';
		
		$string .= $this->httpRequest;
		 
		if(!empty($this->body)) {
			$dom->loadXML($this->body);
			$string .= htmlentities($dom->saveXml());
		}
		
		$string .= '<br>last response:<br><br>';
		
		$string .= $this->httpResponse;
		 
		if(!empty($this->xmlResponse)) {
			$dom->loadXML($this->xmlResponse);
			$string .= htmlentities($dom->saveXml());
		}
		
		$string .= '</pre>';
		 
		echo $string;
	}
}

  /**
   * Error handeling functions
   */

$debug = TRUE;
   
function log_message ($type, $message) {
	global $debug;
	if ($debug) {
		echo '['.$type.'] '.$message.'\n';
	}
}
