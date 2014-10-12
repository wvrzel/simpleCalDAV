<?php
/**
 * CalDAVException
 *
 * Copyright 2014 Michael Palm <palm.michael@gmx.de>
 * 
 * This class is an extension to the Exception-class, to store and report additional data in the case
 * of a problem.
 * For debugging purposes, just sorround all of your SimpleCalDAVClient-Code with try { ... } catch (Exception $e) { echo $e->__toString(); }
 * 
 * @package simpleCalDAV
 *
 */

class CalDAVException extends Exception {
	private $requestHeader;
	private $requestBody;
	private $responseHeader;
	private $responseBody;
	
	public function __construct($message, $client, $code = 0, Exception $previous = null) {
    	parent::__construct($message, $code, $previous);
    	
    	$this->requestHeader = $client->GetHttpRequest();
    	$this->requestBody = $client->GetBody();
    	$this->responseHeader = $client->GetResponseHeaders();
    	$this->responseBody = $client->GetResponseBody();
    }
    
    public function __toString() {
    	$string = '';
    	$dom = new DOMDocument();
    	$dom->preserveWhiteSpace = FALSE;
		$dom->formatOutput = TRUE;
		
		$string .= '<pre>';
		$string .= 'Exception: '.$this->getMessage().'<br><br><br><br>';
		$string .= 'If you think there is a bug in SimpleCalDAV, please report the following information on github or send it at palm.michael@gmx.de.<br><br><br>';
		$string .= '<br>For debugging purposes:<br>';
		$string .= '<br>last request:<br><br>';
		
    	$string .= $this->requestHeader;
    	
    	if(!empty($this->requestBody)) {
    		
    		if(!preg_match( '#^Content-type:.*?text/calendar.*?$#', $this->requestHeader, $matches)) {
    			$dom->loadXML($this->requestBody);
    			$string .= htmlentities($dom->saveXml());
    		}
    		
    		else $string .= htmlentities($this->requestBody).'<br><br>';
    	}

    	$string .= '<br>last response:<br><br>';
		
    	$string .= $this->responseHeader;
    	
    	if(!empty($this->responseBody)) {
    		if(!preg_match( '#^Content-type:.*?text/calendar.*?$#', $this->responseHeader, $matches)) {
    			$dom->loadXML($this->responseBody);
    			$string .= htmlentities($dom->saveXml());
    		}
	    	
    		else $string .= htmlentities($this->responseBody);
    	}
    	
    	$string .= '<br><br>';
    	
    	$string .= 'Trace:<br><br>'.$this->getTraceAsString();
    
    	$string .= '</pre>';
    	
    	return $string;
    }
    
    public function getRequestHeader() {
    	return $this->requestHeader;
    }

    public function getrequestBody() {
    	return $this->requestBody;
    }
    
    public function getResponseHeader() {
    	return $this->responseHeader;
    }
    
    public function getresponseBody() {
    	return $this->responseBody;
    }
}

?>