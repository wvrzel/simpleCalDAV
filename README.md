simpleCalDAV
============

Copyright 2014 Michael Palm <palm.michael@gmx.de>

Table of contents
-----------------

1. [About](#1-about)
2. [Requirements](#2-requirements)
3. [Installation](#3-installation)
4. [How to get started](#4-how-to-get-started)
5. [Example Code](#5-example-code)

------------------------

## 1. About

simpleCalDAV is a php library that allows you to connect to a calDAV-server to get event-, todo-
and free/busy-calendar resources from the server, to change them, to delete them, to create new
ones, etc.
simpleCalDAV was made and tested for connections to the CalDAV-server Baikal 0.2.7. But it should
work with any other CalDAV-server too.

It contains the following functions:
  - connect()
  - findCalendars()
  - setCalendar()
  - getCalendar()
  - create()
  - change()
  - delete()
  - getEvents()
  - getAllEvents()
  - getTODOs()
  - getAllTODOs()
  - getCustomReport()

All of those functions are really easy to use, self-explanatory and are delivered with a big initial
comment, which explains all needed arguments and the return values.

This library is heavily based on AgenDAV caldav-client-v2.php by Jorge López Pérez <jorge@adobo.org>
which again is heavily based on DAViCal caldav-client-v2.php by Andrew McMillan
<andrew@mcmillan.net.nz>.
Actually, I hardly added any features. The main point of my work is to make everything straight
forward and easy to use. You can use simpleCalDAV without a deeper understanding of the
calDAV-protocol.


## 2. Requirements

Requirements of this library are
  - The php extension cURL ( http://www.php.net/manual/en/book.curl.php )


## 3. Installation

Just navigate into a directory on your server and execute

    git clone https://github.com/wvrzel/simpleCalDAV.git

Assure yourself that cURL is installed.

Import `SimpleCalDAVClient.php` in your code and you are ready to go ;-)


## 4. How to get started

Read the comments in `SimpleCalDAVClient.php` and the example code.


## 5. Example Code

Example code is provided under "/example code/".
