<?php
/**
 * IcalExport, Mantis calendar Export Plugin
 *
 * Adapted for iCalcreator >= 2.39
 *
 * @package    MantisPlugin
 * @subpackage IcalExport
 * @copyright  Copyright (C) 2013-2022 Kjell-Inge Gustafsson, kigkonsult, All rights reserved.
 * @link       http://kigkonsult.se/IcalExport
 * @license    Subject matter of licence is the software IcalExport.
 *             The above copyright, link, package and version notices,
 *             this licence notice shall be included in all copies or
 *             substantial portions of the IcalExport.
 *
 *             IcalExport is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU Lesser General Public License as published
 *             by the Free Software Foundation, either version 3 of the License,
 *             or (at your option) any later version.
 *
 *             IcalExport is distributed in the hope that it will be useful,
 *             but WITHOUT ANY WARRANTY; without even the implied warranty of
 *             MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *             GNU Lesser General Public License for more details.
 *
 *             You should have received a copy of the GNU Lesser General Public License
 *             along with iCalcreator. If not, see <https://www.gnu.org/licenses/>.
 * @author     Kjell-Inge Gustafsson, kigkonsult <ical@kigkonsult.se>
 * @since      2.2 - 2022-01-02
 *
 * This file is a part of IcalExport.
 */

use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vtodo;
use Kigkonsult\Icalcreator\Util\DateTimeZoneFactory;

class CalendarLoader
{
    /**
     * @var Vcalendar
     */
    private $calendar;

    /**
     * @var string
     */
    private $unique_id;

    /**
     * @var Vtodo
     */
    private $vtodo;

    /**
     * @var string
     */
    private $vtodoPartStat = '';

    /**
     * @var array
     */
    private $vtodoPriority;

    /**
     * @var array
     */
    private $vtodoReproducibility;

    /**
     * @var array
     */
    private $vtodoResolution;

    /**
     * @var array
     */
    private $vtodoSeverity;

    /**
     * @var array
     */
    private $vtodoStatus;

    /*
     * @var string[]  mantis BugData properties
     *
     * Uppate iCal Vtodo properties
     * used as keys in an iCal Vtodo DESCRIPTION property
     */
    private static $descriptionKeys = [
        'project',
        'id',
        'summary',
        'reporter',
        'handler',
        'category',
        'priority',
        'severity',
        'reproducibility',
        'status',
        'resolution',
        'projection',
        'eta',                      // if enabled in config
        'view_state',
        'description',
        'steps_to_reproduce',
        'additional_information',
        'duplicate_id',
        //  'bug_text_id',           // ??
        'os',
        'os_build',
        'platform',
        'version',
        'fixed_in_version',
        'build',
        //  'profile_id',            // ??
        'sponsorship_total',
        'sticky',
        'target_version',
    ];

    /**
     * @var string[]
     */
    private $vtodoDescriptionsArr = [];

    /**
     * @var string[]
     */
    private $vtodoAttendeesArr = [];

    /**
     * @var string
     */
    private $vtodoDelegatedFrom = '';

    /**
     * @var string  iCal text inline end-of-line characters
     */
    private static $ICAL_EOL = '\n';

    /**
     * CalendarLoader factory
     *
     * @param string $unique_id
     * @return static
     */
    public static function factory( string $unique_id ) : self
    {
        $instance            = new self();
        $instance->unique_id = $unique_id;
        $instance->calendar  = new Vcalendar( [ Vcalendar::UNIQUE_ID => $unique_id ] );
        /*
         * this iCal vtodo calendar is simply a 'posting' one,
         * NO 'request' vtodo calendar (i.e. issues are already assigned)
         */
        $instance->calendar->setMethod( Vcalendar::PUBLISH );
        return $instance;
    }

    /**
     * Set (optional) NAME/WR_CALNAME iCal calendar property, WR_CALNAME 'required' by some calendar software
     *
     * @param string $name
     * @return static
     */
    public function setCalendarName( string $name ) : self
    {
        $name = ucfirst( strtolower( $name ));
        $this->calendar->setName( $name );
        $this->calendar->setXprop( Vcalendar::X_WR_CALNAME, $name );
        return $this;
    }

    /**
     * Set (optional) description/WR-CALDESC iCal calendar property, WR-CALDESC 'required' by some calendar software
     *
     * @param string $userName
     * @param string $calDesc
     * @param string $dateFmt
     * @return static
     */
    public function setCalendarDescription( string $userName, string $calDesc, string $dateFmt ) : self
    {
        static $FMTX_WR_CALDESC = '%s%s %s (%s)';
        $desc = sprintf( $FMTX_WR_CALDESC, $userName, $calDesc, $this->unique_id, date( $dateFmt ));
        $this->calendar->setDescription( $desc );
        $this->calendar->setXprop( Vcalendar::X_WR_CALDESC, $desc );
        return $this;
    }

    /*
     * Set timezone
     *
     * @param string $timeZone
     * @return static
     */
    public function setCalendarXpropTimezone( string $timeZone ) : self
    {
        $this->calendar->setXprop( Vcalendar::X_WR_TIMEZONE, $timeZone );
        return $this;
    }

    /**
     * Set calenadr url (NO source and refresh-interval due to No calendar GET rest-api)
     *
     * @param string $url
     * @return static
     */
    public function setCalendarurl( string $url ) : self
    {
        $this->calendar->setUrl( $url );
        return $this;
    }

    /**
     * CalendarLoader create and return to browser calendar (with Vtimezone component) file
     *
     * @return string
     */
    public function returnCalendar() : string
    {
        $timezone = $this->calendar->getXprop( Vcalendar::X_WR_TIMEZONE )[1];
        if( DateTimeZoneFactory::isUTCtimeZone( $timezone )) {
            $this->calendar->newVtimezone()->setTimezone( $timezone );
        }
        else {
            $this->calendar->vtimezonePopulate( $timezone );
        }
        $this->calendar->sort();
        return $this->calendar->returnCalendar();
    }

    /**
     * Initiate a new calendar Vtodo component
     *
     * @param int $timestamp
     * @param int $bugId
     * @return static
     * @throws Exception
     */
    public function initNewVtodo( int $timestamp, int $bugId ) : self
    {
        static $SP0 = '';
        static $NEW_ = 'NEW_';
        static $NONE = 'NONE';
        $this->vtodo = $this->calendar->newVtodo();

        /*
         * set iCal component UID property, Universal IDentifier
         */
        $this->vtodo->setUid( self::renderUID( $timestamp, $bugId, $this->unique_id ));

        /*
         * Init for (later) property update
         *
         */
        foreach( self::$descriptionKeys as $key ) {
            $this->vtodoDescriptionsArr[$key] = $SP0;
        }

        /*
         * assure iCal Vtodo PRIORITY/STATUS/SUMMARY are set
         */
        $this->vtodoPriority = [ 0, $NONE ];
        $this->vtodoStatus   = [ Vcalendar::NEEDS_ACTION, $NEW_ ];
        $this->vtodo->setSummary( $SP0 );

        return $this;
    }

    /**
     * Set created (UTC)
     *
     * @param int $timestamp
     * @return static
     * @throws Exception
     */
    public function setVtodoCreated( int $timestamp ) : self
    {
        $this->vtodo->setCreated( self::timestampToDateTime( $timestamp ));
        return $this;
    }

    /**
     * Set iCal component URL property, a link back to the mantis bug report view page
     *
     * @param string $url
     * @param int    $bugId
     * @return static
     */
    public function setVtodoUrl( string $url, int $bugId ) : self
    {
        $this->vtodo->setUrl( self::renderURL( $url, $bugId ));
        return $this;
    }

    /**
     * Set sequence (= update number)
     *
     * @param int $sequeceNo
     * @return static
     */
    public function setVtodoSequenceNo( int $sequeceNo ) : self
    {
        $this->vtodo->setSequence( $sequeceNo );
        return $this;
    }

    /*
     * Set iCal Vtodo ORGANIZER (bug assigned to)
     *
     * @param string $email
     * @return static
     */
    public function setVtodOrganizer( string $email ) : self
    {
        $this->vtodo->setOrganizer( $email );
        return $this;
    }

    /**
     * Close off the vtodo calendar component
     *
     * @return void
     */
    public function closeVtodo()
    {
        /*
         * Combine mantis priority and severity into Vtodo PRIORITY
         */
        list( $value, $xValue ) = $this->vtodoPriority;
        $parameters = [ Vcalendar::PRIORITY => $xValue ];
        if( ! empty( $this->vtodoSeverity ) ) {
            list( $xKey, $xValue ) = $this->vtodoSeverity;
            $parameters[$xKey] = $xValue;
        }
        if( ! empty( $this->vtodoReproducibility ) ) {
            list( $xKey, $xValue ) = $this->vtodoReproducibility;
            $parameters[$xKey] = $xValue;
        }
        $this->vtodo->setPriority( $value, self::xPrefixParameterKeys( $parameters ) );

        /*
         * Combine mantis status and resolution into Vtodo STATUS
         */
        list( $value, $xValue ) = $this->vtodoStatus;
        $parameters = [ Vcalendar::STATUS => $xValue ];
        if( ! empty( $this->vtodoResolution ) ) {
            list( $xKey, $xValue ) = $this->vtodoResolution;
            $parameters[$xKey] = $xValue;
        }
        $this->vtodo->setStatus( $value, self::xPrefixParameterKeys( $parameters ) );

        /*
         * Concatenate 'all' collected mantis bug report properties
         * into one iCal DESCRIPTION (with iCal row breaks)
         */
        $this->setVtodoDescription();

        /*
         * Update the vtodo with collected attendees
         */
        $this->setVtodoAttendees();
    }

    /**
     * Set Vtodo Attach
     *
     * @param array  $attachments
     * @param string $url
     * @return void
     */
    public function setVtodoAttachments( array $attachments, string $url )
    {
        static $BUGNOTEID   = 'bugnote_id';
        static $XBUGNOTEID  = 'x-bugnote_id';
        static $CANDOWNLOAD = 'can_download';
        static $EXISTS      = 'exists';
        static $DOWNLOADURL = 'download_url';
        static $ID          = 'id';
        static $XID         = 'x-attach-id';
        foreach( $attachments as $attachment ) {
            if( $attachment[$CANDOWNLOAD] && $attachment[$EXISTS] ) {
                $parameters = [ $XID => $attachment[$ID] ];
                if( ! empty( $attachment[$BUGNOTEID] )) {
                    $parameters[$XBUGNOTEID] = $attachment[$BUGNOTEID];
                }
                $this->vtodo->setAttach( $url . $attachment[$DOWNLOADURL], $parameters );
            } // end if
        } // end foreach
    }

    /**
     * Set/update Vtodo Attendee
     *
     * @param string $attendeeEmail
     * @param string $attendeeName
     * @param array  $parameters
     * @return void
     */
    public function updateVtodoAttendee( string $attendeeEmail, string $attendeeName, array $parameters )
    {
        $oldParameters = isset( $this->vtodoAttendeesArr[$attendeeEmail] )
            ? (array) $this->vtodoAttendeesArr[$attendeeEmail]
            : [];
        foreach( $oldParameters as $key => $value ) {
            if(( Vcalendar::ROLE !== $key ) || ( Vcalendar::CHAIR === $value )) {
                $parameters[$key] = $value;
            }
        } // end foreach
        if( ! isset( $parameters[Vcalendar::ROLE] )) {
            $parameters[Vcalendar::ROLE] = Vcalendar::OPT_PARTICIPANT;
        }
        if( isset( $parameters[Vcalendar::DELEGATED_FROM] )) {
            if( $parameters[Vcalendar::DELEGATED_FROM] !== $attendeeEmail ) {
                $this->vtodoDelegatedFrom = $attendeeEmail;
            }
            unset( $parameters[Vcalendar::DELEGATED_FROM] );
            $parameters['x-reporter'] = Vcalendar::TRUE;
        }
        $parameters[Vcalendar::CN]        = $attendeeName;
        $this->vtodoAttendeesArr[$attendeeEmail] = $parameters;
    }

    /**
     * set Vtodo Attendees
     *
     * @return void
     */
    private function setVtodoAttendees()
    {
        $XROLE   = 'X-ROLE';
        $HANDLER = 'Handler';
        foreach( $this->vtodoAttendeesArr as $attendeeEmail => $parameters ) {
            if( Vcalendar::CHAIR === $parameters[Vcalendar::ROLE] ) {
                $this->vtodo->setOrganizer(
                    $attendeeEmail,
                    [
                        Vcalendar::CN => $parameters[Vcalendar::CN],
                        $XROLE        => $HANDLER,
                    ]
                );
                if( ! empty( $this->vtodoPartStat ) ) {
                    $parameters[Vcalendar::PARTSTAT] = $this->vtodoPartStat;
                }
                if( ! empty( $this->vtodoDelegatedFrom ) ) {
                    $parameters[Vcalendar::DELEGATED_FROM] = $this->vtodoDelegatedFrom;
                }
            } // end if
            $this->vtodo->setAttendee( $attendeeEmail, $parameters );
        } // end foreach
    }

    /**
     * Set Vtodo Categories
     *
     * @param string $category
     * @param null|array $parameters
     * @return void
     */
    public function setVtodoCategories( string $category, $parameters = [] )
    {
        $this->vtodo->setCategories( $category, self::xPrefixParameterKeys( $parameters ?? [] ));
    }

    /**
     * Set Vtodo Class
     *
     * @param int $classValue
     * @return void
     */
    public function setVtodoClass( int $classValue ) {
        $this->vtodo->setClass(( 50 === $classValue ) ? Vcalendar::P_IVATE : Vcalendar::P_BLIC );
    }

    /**
     * Set Vtodo Comment
     *
     * @param string $bugnote
     * @param null|array $parameters
     * @return void
     */
    public function setVtodoComment( string $bugnote, $parameters = [] ) {
        $this->vtodo->setComment(
            self::fixIcalEol( $bugnote ),
            self::xPrefixParameterKeys( $parameters ?? [] )
        );
    }

    /**
     * Collect key/value pairs for (later) UPDATE of vtodo description
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function updateVtodoDescriptions( string $key, string $value )
    {
        if( array_key_exists( $key, $this->vtodoDescriptionsArr )) {
            $this->vtodoDescriptionsArr[$key] = $value;
        }
    }

    /**
     * Set Vtodo description from collection in vtodoDescriptionsArr
     *
     * @return void
     */
    private function setVtodoDescription()
    {
        static $FMT    = '%s : %s';
        static $KEYLEN = 22;
        /*
         * concatenate 'all' mantis bug report properties
         * into one iCal DESCRIPTION (with iCal row breaks)
         */
        $descriptionRows = [];
        foreach( $this->vtodoDescriptionsArr as $key => $value ) {
            if( empty( $value ) && ! is_numeric( $value )) {
                continue;
            }
            $descriptionRows[] = sprintf( $FMT, str_pad( $key, $KEYLEN ), self::fixIcalEol( $value ));
        } // end foreach
        if( ! empty( $descriptionRows )) {
            $this->vtodo->setDescription( implode( self::$ICAL_EOL, $descriptionRows ));
        }
    }

    /**
     * Set Vtodo Dtstart
     *
     * @param int    $startTimestamp
     * @param string $timezone
     * @return void
     * @throws Exception
     */
    public function setVtodoDtstart( int $startTimestamp, string $timezone )
    {
        $this->vtodo->setDtstart( self::timestampToDateTime( $startTimestamp, $timezone ));
    }

    /**
     * Set Vtodo Due, opt modified with etaValue if set
     *
     * @param int      $startTimestamp
     * @param string   $timezone
     * @param int|null $etaValue
     * @return void
     * @throws Exception
     */
    public function setVtodoDue( int $startTimestamp, string $timezone, $etaValue = 0 )
    {
        if( empty( $etaValue )) {
            $etaValue = 0;
        }
        $modify = '';
        switch((int) $etaValue ) {
            case 0:
                // fall through
            case 10:
                break;
            case 20:
                $modify = '+ 1 day';
                break;
            case 30:
                $modify = '+ 3 days';
                break;
            case 40:
                $modify = '+ 7 days';
                break;
            case 50:
                $modify = '+ 1 month';
                break;
            case 60:
                $modify = '+ 2 month';
                break;
            default:
                $modify = '+ 10 years';
                break;
        } // end switch
        if( ! empty( $modify )) {
            $this->vtodo->setDue( self::timestampToDateTime( $startTimestamp, $timezone, $modify ));
        }
    }

    /**
     * Set Vtodo Last-modified UTC, also calendar last-modified from the latest vtodo last-modified
     *
     * @param int $timestamp
     * @return void
     * @throws Exception
     */
    public function setLastModified( int $timestamp )
    {
        $dateTime     = self::timestampToDateTime( $timestamp );
        $calLastModDt = $this->calendar->getLastmodified();
        if( false === $calLastModDt ) {
            $this->calendar->setLastmodified( $dateTime );
        }
        elseif( $calLastModDt->getTimeStamp() < $timestamp ) {
            $this->calendar->setLastmodified( $dateTime );
        }
        $this->vtodo->setLastmodified( $dateTime );
    }

    /**
     * Set Vtodo Priority and update descriptions
     *
     * @param int $value
     * @param string $valueText
     * @return void
     */
    public function setVtodoPriority( int $value, string $valueText )
    {
        $priority = 0;
        switch( $value ) {
            case 60:
                $priority = 1;
                break;
            case 50:
                $priority = 2;
                break;
            case 40:
                $priority = 4;
                break;
            case 30:
                $priority = 5;
                break;
            case 20:
                $priority = 7;
                break;
            case 10:
            default:
                break;
        } // end switch
        $this->vtodoPriority = [ $priority, $valueText ];
        $this->updateVtodoDescriptions( strtolower( Vcalendar::PRIORITY ), $valueText );
    }

    /**
     * Set Vtodo RELATED-TO
     *
     * @param string $value
     * @param null|array  $parameters
     * @return void
     */
    public function setVtodoRelatedto( string $value, $parameters = [] )
    {
        $this->vtodo->setRelatedto( $value, $parameters ?? [] );
    }

    /**
     * Save bug reproducibility, will update Vtodo priority/descriptions
     *
     * @param string $valueText
     * @return void
     */
    public function setVtodoReproducibility( string $valueText )
    {
        static $REPRODUCIBILITY     = 'reproducibility';
        $this->vtodoReproducibility = [ $REPRODUCIBILITY, $valueText ];
        $this->updateVtodoDescriptions( $REPRODUCIBILITY, $valueText );
    }


    /**
     * Save bug resolution status, will update Vtodo status/descriptions
     *
     * @param string $valueText
     * @return void
     */
    public function setVtodoResolution( string $valueText )
    {
        static $RESOLUTION     = 'resolution';
        $this->vtodoResolution = [ $RESOLUTION, $valueText ];
        $this->updateVtodoDescriptions( $RESOLUTION, $valueText );
    }

    /**
     * Save bug severity, will update Vtodo priority/descriptions
     *
     * @param string $valueText
     * @return void
     */
    public function setVtodoSeverity( string $valueText )
    {
        static $SEVERITY     = 'severity';
        $this->vtodoSeverity = [ $SEVERITY, $valueText ];
        $this->updateVtodoDescriptions( $SEVERITY, $valueText );
    }

    /**
     * Set Vtodo Status/descriptions
     *
     * @param int $value
     * @param string $valueText
     * @return void
     */
    public function setVtodoStatus( int $value, string $valueText )
    {
        static $STATUS = 'status';
        switch( true ) {
            case ( 90 <= $value ) : // CLOSED
                $status = Vcalendar::CANCELLED;
                break;
            case ( 80 <= $value ) : // RESOLVED
                $status = Vcalendar::COMPLETED;
                break;
            case ( 50 <= $value ) : // ASSIGNED
                $status = Vcalendar::IN_PROCESS;
                $this->vtodoPartStat = Vcalendar::ACCEPTED;
                break;
            case ( 40 <= $value ) : // CONFIRMED
                $status = Vcalendar::IN_PROCESS;
                break;
            case ( 30 <= $value ) : // ACKNOWLEDGED
                $status = Vcalendar::IN_PROCESS;
                break;
            case ( 20 <= $value ) : // FEEDBACK
                $status = Vcalendar::NEEDS_ACTION;
                break;
            case ( 10 <= $value ) : // NEW_
                // fall through
            default :
                $status = Vcalendar::NEEDS_ACTION;
                break;
        } // end switch
        $this->vtodoStatus = [ $status, $valueText ];
        $this->updateVtodoDescriptions( $STATUS, $valueText );
    }

    /**
     * Set Vtodo Summary/descriptions
     *
     * @param string $summary
     * @return void
     */
    public function setVtodoSummary( string $summary )
    {
        $this->vtodo->setSummary( $summary );
        $this->updateVtodoDescriptions( strtolower( Vcalendar::SUMMARY ), $summary );
    }

    /**
     * Return array with 'X-'-prefixed parameter keys
     *
     * @param null|array $parameters
     * @return void
     */
    private static function xPrefixParameterKeys( $parameters = [] ) : array
    {
        static $X = 'X-';
        if( empty( $parameters )) {
            return [];
        }
        $params = [];
        foreach( (array) $parameters as $k => $v ) {
            if( 0 !== stripos( $k, $X )) {
                $params[$X . $k] = $v;
            }
        }
        return $params;
    }

    /**
     * Convert eols to ical eol in rtrimmed string
     *
     * @param $string
     * @return string
     */
    private static function fixIcalEol( $string ) : string
    {
        static $EOLs = [ "\r\n", "\n\r", "\n", "\r" ];
        return str_replace( $EOLs, self::$ICAL_EOL, rtrim( $string ));
    }

    /**
     * Render UID, submitted timestamp + bug_id + unique_id
     *
     * @param int    $timestampSubmitted
     * @param int    $bugId
     * @param string $unique_id
     * @return string
     * @throws Exception
     */
    public static function renderUID( int $timestampSubmitted, int $bugId, string $unique_id ) : string
    {
        static $FMTUID = '%s-%s@%s';
        return sprintf(
            $FMTUID,
            self::renderIcalDateTime( self::timestampToDateTime( $timestampSubmitted )), // UTC
            $bugId,
            $unique_id
        );
    }

    /**
     * Render URL for mantis single view-bug-page
     *
     * @param string $url
     * @param int $bugId
     * @return string
     */
    public static function renderURL( string $url, int $bugId ) : string
    {
        static $FMTURL = '%sview.php?id=%s';
        return sprintf( $FMTURL, $url, $bugId );
    }

    /**
     * Return string iCal format datetime
     *
     * @param DateTime $dateTime
     * @param null|string $timezone
     * @return string
     */
    public static function renderIcalDateTime( DateTime $dateTime, $timezone = '' ) : string
    {
        static $DATEFMT = 'Ymd\THis';
        static $SP1     = ' ';
        return $dateTime->format( $DATEFMT ) .
            ( empty( $timezone ) ? Vcalendar::Z : $SP1 . $timezone );
    }

    /**
     * Return DateTime from timestamp, opt with timezone else UTC
     *
     * @param int         $timestamp
     * @param null|string $timezone
     * @param null|string $modify
     * @return DateTime
     * @throws Exception
     */
    public static function timestampToDateTime(
        int    $timestamp,
        string $timezone = null,
        string $modify = null
    ) : DateTime
    {
        static $AT = '@';
        $t_date    = new DateTime( $AT . $timestamp ); // in UTC
        if( ! empty( $timezone )) {
            $t_date->setTimezone( new DateTimeZone( $timezone ));
        }
        if( ! empty( $modify )) {
            $t_date->modify( $modify );
        }
        return $t_date;
    }
}
