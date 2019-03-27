
IcalExport: Mantis iCal Export Plugin
=====================================

Copyright (C) 2013-2019 Kjell-Inge Gustafsson, kigkonsult, All rights reserved.
author     Kjell-Inge Gustafsson, kigkonsult
link       http://github.com/iCalcreator/IcalExport
license    GNU Lesser General Public License
IcalExport 2.0 - 2019-03-25


The IcalExport Plugin adds calendar export capabilities to MantisBT.


Export selected [MantisBT] issues
as iCal ([rfc2445]/[rfc5545]) calendar tasks (i.e. TODOs)
in a iCal media file (extension .ics)
for import into
- public calendars
- business/organization calendars
- private calendars in a PC, pad or smartphone
- etc

The IcalExport plugin adds buttons for calendar export in the 'View issues'
and 'view' (issue) pages.


You will find an iCal summary as http://en.wikipedia.org/wiki/ICalendar.
The iCal standards are rfc5545/rfc5546 (obsoletes rfc2445/rfc2446):
rfc5545
'Internet Calendaring and Scheduling Core Object Specification',
download from https://tools.ietf.org/html/rfc5545rfc5545,
rfc5546
'iCalendar Transport-Independent Interoperability Protocol (iTIP)
Scheduling Events, BusyTime, To-dos and Journal Entries',
download from https://tools.ietf.org/html/rfc5545rfc5546.


You can found the previous version (1.06) on github as tab, 1.06.


REQUIREMENTS:
-------------
Made for and tested with
- PHP >= 5.6
- MantisBT version 2.20.0
- iCalcreator (version >=2.27.16!), not included


INSTALLATION:
-------------

Unstall any previous version of IcalExport.

Download and extract the IcalExport plugin in the 'plugins' directory,
sub-directory 'IcalExport'.

Download iCalcreator from  https://github.com/iCalcreator/iCalcreator/ and
extract into the 'api' folder. Access to the 'api/iCalcreator/autoload.php'
is required.


In 'Manage' and 'Manage Plugins', you should find and install 'IcalExport'.

There is no configuration.


Calendars (and Vtodos) produced with previous version of IcalExport do not
correlate with Calendars (and Vtodos) produced with this version of IcalExport.


DESCRIPTION:
------------
The iCal media file with vcalendar and vtodo components are built as follows;

First, a <site/system unique id> is set, based on the MantisBT 'hostname' part
config from "config_get( 'nostname' )" and used in each TODO component, below.

METHOD
  value 'PUBLISH'
  The created iCal TODO calendar is simply a 'posting' one, NO 'request' TODO
  calendar (i.e. assuming that issues are already assigned).

Some iCal calendar properties are set, optional in rfc5545 but 'required' by
some calendar software:
X-WR-CALNAME
 value: user_name + ' issues'
X-WR-CALDESC
 value: user_name + ' issues at ' + <site/system unique id> + <date>
X-WR-TIMEZONE
 value: 'UTC'

For each bug report, an iCal VTODO component is created with the following
properties and base input values :

UID
  based on MantisBT bug report properties 'date_submitted' and 'id' and the
  <site/system unique id>
  ex. '20131026T183425Z-12345@kigkonsult.se'
DTSTAMP
  component timestamp (create) date, automatically created by iCalcreator.
  The date timezone is 'UTC'.
DTSTART
  from MantisBT bug report property 'date_submitted' (if assigned on
  registration) or the assign date
  The date timezone is 'UTC'.
SEQUENCE
  from the number of bug (history) updates
SUMMARY
 from MantisBT bug report property 'summary', if missing, empty.
PRIORITY
  from MantisBT bug report property 'priority', if missing, 'priority'-
  value 'unknown is set'.
  MantisBT bug report properties 'reproducibility' and 'severity' are
  set as (x-)parameters.
ORGANIZER
  from MantisBT bug report property 'handler_id'.
  Also set as ATTENDEE with role CHAIR.
  If no 'handler_id' is set, user is set.
URL
  a link back to the MantisBT bug report
CREATED
  from MantisBT bug report property 'date_submitted'.
  The date timezone is 'UTC'.
LAST-MODIFIED
  from MantisBT bug report property 'last_updated' (if not equal to
  'date_submitted').  The date timezone is 'UTC'.
DUE
  from MantisBT bug report property 'due_date', if set.
  If 'due_date' is missing and 'eta' is set, a DUE date property is created,
  based on 'date_submitted' properties.
  The date timezone is 'UTC'.
CATEGORIES
  from MantisBT bug report property 'category' and
  each MantisBT bug report 'tag' creates (additional) CATEGORIES
CLASS
  from MantisBT bug report property 'view_state'
STATUS
  from MantisBT bug report property 'status', 'resolution' is added as
  parameter.
DESCRIPTION
  a concatenation of 'all' non-empty MantisBT bug report properties
  into one iCal DESCRIPTION (rendered with iCal row breaks) in the
  following order:
  project, id, summary, reporter, handler, category, priority, severity,
  reproducibility, status, resolution, projection, eta, view_state, description,
  steps_to_reproduce, additional_information, duplicate_id, bug_text_id, os,
  os_build, platform, version, fixed_in_version, build, profile_id,
  sponsorship_total, sticky and target_version
COMMENT
   each MantisBT bug report (non-private) 'bugnote' (type=BUGNOTE) creates one
   iCal COMMENT
ATTACH
  each MantisBT bug report 'attachment' creates one iCal ATTACH (link)
ATTENDEE
  from MantisBT bug report
    reporter (role OPT-PARTICIPANT)
    assignee (role CHAIR)
    bugnote reporter (role OPT-PARTICIPANT)
    monitors (role OPT-PARTICIPANT)

SUPPORT:
--------
  http://github.com/iCalcreator/IcalExport/issues
