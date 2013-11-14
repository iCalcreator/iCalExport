iCalExport: Mantis iCal Export Plugin
=====================================
Copyright (C) 2013 Kjell-Inge Gustafsson, kigkonsult, All rights reserved.
license    GNU General Public License (GPL)
link       http://github.com/iCalcreator/iCalExport
author     Kjell-Inge Gustafsson, kigkonsult
iCalExport 1.06 - 2013-11-14

The iCalExport Plugin adds calendar export capabilities to MantisBT.

FUNCTION:
---------
Export selected MantisBT bug reports as calendar tasks (i.e. TODOs) in a iCal
media file (extension .ics) for import to a public calendar (ex. Google) or a
private calendar in a PC, pad or smartphone.

The iCal standards are rfc5545/rfc5546 (obsoletes rfc2445/rfc2446):
rfc5545
'Internet Calendaring and Scheduling Core Object Specification',
download from http://kigkonsult.se/downloads/dl.php?f=rfc5545,
rfc5546
'iCalendar Transport-Independent Interoperability Protocol (iTIP)
Scheduling Events, BusyTime, To-dos and Journal Entries',
download from http://kigkonsult.se/downloads/dl.php?f=rfc5546.

The plugin uses the iCalcreator class, included (license LGPL), for the iCal
PHP 'hard stuff', more iCalcreator info at http://kigkonsult.se/iCalcreator/.

Perform a database backup before installing the plugin! The plugin will create
a new table in parallel to the MantisBT bug report table, used as a bug report
update counter.

REQUIREMENTS:
-------------
Made for and tested against PHP >= 5.2 and MantisBT version 1.2.0.
The default timezone (config $g_default_timezone) MUST be set!

INSTALLATION:
-------------
Extract the iCalExport plugin in the 'plugins' directory, sub-directory
'iCalExport'. In 'Manage' module, you should find and install 'iCalExport'.

DESCRIPTION:
------------
The iCal media file with vcalendar and vtodo components are built as follows;

First, a <site/system unique id> is set, based on the MantisBT 'host' part
config from "config_get( 'path' )" and used in each TODO component, below.

METHOD
 value 'PUBLISH'
 The created iCal TODO calendar is simply a 'posting' one, NO 'request' TODO
 calendar (i.e. assuming that issues are already assigned).

Some iCal calendar properties are set, optional in rfc5545 but 'required' by
some calendar software:
X-WR-CALNAME
 value: user_name + ' bug reports'
X-WR-CALDESC
 value: user_name + ' bug reports at ' + <site/system unique id> + <date>
X-WR-TIMEZONE
 value: 'UTC'

For each bug report, an iCal VTODO component is created with the following
properties and base input values (mandatory iCal properties are marked *):
UID *
 based on MantisBT bug report properties 'date_submitted' and 'id' and the
 <site/system unique id>
 ex. '20131026T183425Z:12345@kigkonsult.se'
DTSTAMP *
 component timestamp (create) date, automatically created by iCalcreator.
 The date timezone is 'UTC'.
DTSTART *
 from MantisBT bug report property 'date_submitted'.
 The date timezone is 'UTC'.
SEQUENCE *
  from the iCalExport plugin added counter for bug report modifications.
SUMMARY *
 from MantisBT bug report property 'summary', if missing, empty.
PRIORITY *
 from MantisBT bug report property 'priority', if missing, a 'no priority'-
 value is set
ORGANIZER *
 the handler email, using MantisBT bug report property 'handler_id'. If
 missing, calendar export user email is set.
URL
 a link back to the MantisBT bug report
CONTACT
 the reporter email, using MantisBT bug report property 'reporter_id'
CREATED
 from MantisBT bug report property 'date_submitted'.
 The date timezone is 'UTC'.
LAST-MODIFIED
 from MantisBT bug report property 'last_updated' (if not equal to
 'date_submitted').  The date timezone is 'UTC'.
DUE
 from MantisBT bug report property 'due_date', if set.
 If 'due_date' is missing, a DUE date property is created,
 based and calculated from MantisBT bug report 'eta' (if set) and
 'date_submitted' properties.
 The date timezone is 'UTC'.
CATEGORIES
 from MantisBT bug report property 'category',
 each MantisBT bug report 'tag' creates (additional) CATEGORIES
 (iCal CATEGORIES may ocurr multiple times)
CLASS
 from MantisBT bug report property 'view_state'
STATUS
 from MantisBT bug report property 'resolution'
DESCRIPTION
 a concatenation of 'all' non-empty MantisBT bug report (expanded) properties
 into one iCal DESCRIPTION (formatted and with iCal row breaks) in the
 following order:
 project, id, summary, reporter, handler, category, priority, severity,
 reproducibility, status, resolution, projection, eta, view_state, description,
 steps_to_reproduce, additional_information, duplicate_id, bug_text_id, os,
 os_build, platform, version, fixed_in_version, build, profile_id,
 sponsorship_total, sticky and target_version
COMMENT
  each MantisBT bug report 'bugnote' (type=BUGNOTE) creates one iCal COMMENT
 (iCal COMMENT may ocurr multiple times)
ATTACH
 each MantisBT bug report 'attachment' creates one iCal ATTACH (link)
 (iCal ATTACH may ocurr multiple times)

SUPPORT:
--------
* iCalExport support/issues
  http://github.com/iCalcreator/iCalExport/issues

* iCal support
  http://kigkonsult.se/support/index.php

* iCal documentation
  http://kigkonsult.se/resources/index.php

* iCalExport language updates, queries, improvement/development issues
  or professional support and development
  http://kigkonsult.se/contact/
