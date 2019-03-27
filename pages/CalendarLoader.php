<?php
/**
 * IcalExport, Mantis calendar Export Plugin
 *
 * Adapted for iCalcreator >= 6.27.16
 *
 * @package    MantisPlugin
 * @subpackage IcalExport
 * @copyright  Copyright (C) 2013-2019 Kjell-Inge Gustafsson, kigkonsult, All rights reserved.
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
 * @version    2.0
 * @since      2.0 - 2019-03-25
 *
 * This file is a part of IcalExport.
 */

use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vtodo;

class CalendarLoader
{
    /**
     * @var Vcalendar
     * @access private
     */
    private $calendar =  null;

    /**
     * @var string
     * @access private
     */
    private $unique_id = null;

    /**
     * @var string
     * @access private
     */
    private $normalDateFormat = null;

    /**
     * @var Vtodo
     * @access private
     */
    private $vtodo = null;

    /**
     * @var string
     * @access private
     */
    private $vtodoPartStat = null;

    /**
     * @var string
     * @access private
     */
    private $vtodoPriority = null;

    /**
     * @var string
     * @access private
     */
    private $vtodoReproducibility = null;

    /**
     * @var string
     * @access private
     */
    private $vtodoResolution = null;

    /**
     * @var string
     * @access private
     */
    private $vtodoSeverity = null;

    /**
     * @var string
     * @access private
     */
    private $vtodoStatus = null;

    /*
     * @var string[]  mantis bug properties as keys in an iCal DESCRIPTION property
     * @access private
     * @static
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
        'eta',
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

    /*
     * @var int  length for the longest $descriptionKeys
     * @access private
     * @static
     */
    private static $descriptionArrKeylength = 22;

    /**
     * @var string[]
     * @access private
     */
    private $vtodoDescriptionsArr = [];

    /**
     * @var string[]
     * @access private
     */
    private $vtodoAttendeesArr = [];

    /**
     * @var string  iCal text inline end-of-line characters
     * @access private
     */
    private static $INLINE_EOL = '\n';

    /**
     * @var string  common end-of-line characters
     * @access private
     */
    static $STD_EOL = ["\r\n", "\n\r", "\n", "\r"];

    /**
     * CalendarLoader constructor.
     *
     * @param string $unique_id
     * @param string $normalDateFormat
     * @param string $userName
     * @param string $calendarName
     * @param string $calendarDesc
     */
    public function __construct( $unique_id, $normalDateFormat, $userName, $calendarName, $calendarDesc ) {
        static $FMTX_WR_CALDESC = '%s%s %s (%s)';
        $this->unique_id        = $unique_id;
        $this->normalDateFormat = $normalDateFormat;
        $this->calendar = new Vcalendar( [ Vcalendar::UNIQUE_ID => $unique_id ] );
        /*
         * this iCal vtodo calendar is simply a 'posting' one,
         * NO 'request' vtodo calendar (i.e. issues are already assigned)
         */
        $this->calendar->setMethod( Vcalendar::PUBLISH );
        /*
         * Set some optional iCal calendar properties,
         * 'required' by some calendar software
         */
        $this->calendar->setXprop(
            Vcalendar::X_WR_CALNAME,
            ucfirst( strtolower( $calendarName ))
        );
        $this->calendar->setXprop(
            Vcalendar::X_WR_CALDESC,
            sprintf(
                $FMTX_WR_CALDESC,
                $userName,
                $calendarDesc,
                $this->unique_id,
                date( $this->normalDateFormat )
            )
        );
        /*
         * Set timezone
         */
        $this->calendar->setXprop( Vcalendar::X_WR_TIMEZONE, Vcalendar::UTC );
        $this->calendar->newVtimezone()->setTzid( Vcalendar::UTC );
    }

    /**
     * CalendarLoader create and return calendar
     */
    public function returnCalendar() {
        $this->calendar->sort();
        $this->calendar->returnCalendar();
    }

    /*
     * Initiate a new vtodo calendar component
     *
     * @param int    $timestampSubmitted
     * @param string $url
     * @param string $bugId
     * @param int    $sequenceNo
     * @param string $userEmail
     */
    public function initNewVtodo( $timestampSubmitted, $url, $bugId, $sequenceNo, $userEmail ) {

        $this->vtodo = $this->calendar->newVtodo();

        /*
         * set iCal component UID property, Universal IDentifier
         */
        $this->vtodo->setUid( self::renderUID( $timestampSubmitted, $bugId, $this->unique_id ));

        /*
         * Init arrays/string for (later) property update
         *
         */
        $this->vtodoDescriptionsArr = [];
        foreach( self::$descriptionKeys as $key ) {
            $this->vtodoDescriptionsArr[$key] = null;
        }
        $this->vtodoAttendeesArr    = [];
        $this->vtodoDelegatedFrom   = null;
        $this->vtodoPartStat        = null;
        $this->vtodoResolution      = null;
        $this->vtodoReproducibility = null;
        $this->vtodoSeverity        = null;

        /*
         * Set created
         */
        $this->vtodo->setCreated( self::renderDate( $timestampSubmitted ));

        /*
         * Set iCal component URL property,
         * a link back to the mantis bug report view page
         */
        $this->vtodo->setUrl( self::renderURL( $url, $bugId ));

        /*
         * Set sequence (= update number)
         */
        $this->vtodo->setSequence( $sequenceNo );

        /*
         * assure any iCal ORGANIZER is set
         */
        $this->vtodo->setOrganizer( $userEmail );

        /*
         * assure any iCal PRIORITY is set
         */
        $this->vtodoPriority = [ 0, 'NONE' ];

        /*
         * assure any iCal Status is set
         */
        $this->vtodoStatus = [ Vcalendar::NEEDS_ACTION, 'NEW_' ];

        /*
         * assure any iCal SUMMARY is set
         */
        $this->vtodo->setSummary( '' );
    }

    /*
     * Close off the vtodo calendar component
     */
    public function closeVtodo() {

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
        $this->vtodo->setPriority( $value, self::getXprefixedParameters( $parameters ) );

        /*
         * Combine mantis status and resolution into Vtodo STATUS
         */
        list( $value, $xValue ) = $this->vtodoStatus;
        $parameters = [ Vcalendar::STATUS => $xValue ];
        if( ! empty( $this->vtodoResolution ) ) {
            list( $xKey, $xValue ) = $this->vtodoResolution;
            $parameters[$xKey] = $xValue;
        }
        $this->vtodo->setStatus( $value, self::getXprefixedParameters( $parameters ) );

        /*
         * Concatenate 'all' collected mantis bug report properties
         * into one iCal DESCRIPTION (with iCal row breaks)
         */
        $this->setDescription();

        /*
         * Update the vtodo with collected attendees
         */
        $this->setAttendees();
    }

    /*
     * Set Vtodo Attach
     *
     * @param array  $attachments
     * @param string $url
     */
    public function setAttachments( array $attachments, $url ) {
        static $CANDOWNLOAD = 'can_download';
        static $EXISTS      = 'exists';
        static $DOWNLOADURL = 'download_url';
        foreach( $attachments as $attachment ) {
            if( $attachment[$CANDOWNLOAD] &&
                $attachment[$EXISTS] ) {
                $this->vtodo->setAttach( $url . $attachment[$DOWNLOADURL] );
            }
        }
    }

    /*
     * Collect Vtodo Attendee
     *
     * @param string $attendeeEmail
     * @param string $attendeeName
     * @param array  $parameters
     * @todo duplicates
     */
    public function updateAttendee( $attendeeEmail, $attendeeName, $parameters ) {
        $oldParameters = ( isset( $this->vtodoAttendeesArr[$attendeeEmail] )) ? $this->vtodoAttendeesArr[$attendeeEmail] : [];
        foreach( $oldParameters as $key => $value ) {
            if(( Vcalendar::ROLE != $key ) || ( Vcalendar::CHAIR  == $value )) {
                $parameters[$key] = $value;
            }
        }
        if( ! isset( $parameters[Vcalendar::ROLE] )) {
            $parameters[Vcalendar::ROLE] = Vcalendar::OPT_PARTICIPANT;
        }
        if( isset( $parameters[Vcalendar::DELEGATED_FROM] )) {
            if( $parameters[Vcalendar::DELEGATED_FROM] != $attendeeEmail ) {
                $this->vtodoDelegatedFrom = $attendeeEmail;
            }
            unset( $parameters[Vcalendar::DELEGATED_FROM] );
            $parameters['x-reporter'] = Vcalendar::TRUE;
        }
        $parameters[Vcalendar::CN]          = $attendeeName;
        $this->vtodoAttendeesArr[$attendeeEmail] = $parameters;
    }

    /*
     * set Vtodo Attendees
     *
     * @access private
     */
    private function setAttendees() {
        $XROLE   = 'X-ROLE';
        $HANDLER = 'Handler';
        foreach( $this->vtodoAttendeesArr as $attendeeEmail => $parameters ) {
            if( Vcalendar::CHAIR == $parameters[Vcalendar::ROLE] ) {
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
            }
            $this->vtodo->setAttendee( $attendeeEmail, $parameters );
        }
    }

    /*
     * Set Vtodo Categories
     *
     * @param string $category
     * @param array $parameters
     */
    public function setCategories( $category, $parameters = [] ) {
        $this->vtodo->setCategories( $category, self::getXprefixedParameters( $parameters ));
    }

    /*
     * Set Vtodo Class
     *
     * @param int $classValue
     */
    public function setClass( $classValue ) {
        switch( $classValue ) {
            case 50:
                $this->vtodo->setClass( Vcalendar::P_IVATE );
                break;
            default:
                $this->vtodo->setClass( Vcalendar::P_BLIC );
                break;
        }
    }

    /*
     * Set Vtodo Comment
     *
     * @param string $bugnote
     * @param array  $parameters
     * @param
     */
    public function setComment( $bugnote, $parameters = [] ) {
        $this->vtodo->setComment(
            str_replace( self::$STD_EOL, self::$INLINE_EOL, rtrim( $bugnote )),
            self::getXprefixedParameters( $parameters )
        );
    }

    /*
     * Collect data pairs for (later) uppdate of vtodo description
     *
     */
    public function updateDescriptions( $key, $value ) {
        if( array_key_exists( $key, $this->vtodoDescriptionsArr )) {
            $this->vtodoDescriptionsArr[$key] = $value;
        }
    }

    /*
     * Set Vtodo description from collection in vtodoDescriptionsArr
     *
     * @access private
     */
    private function setDescription() {
        static $FMT = '%s : %s%s';
        /*
         * concatenate 'all' mantis bug report properties
         * into one iCal DESCRIPTION (with iCal row breaks)
         */
        $description = '';
        foreach( $this->vtodoDescriptionsArr as $key => $value ) {
            if( empty( $value )) {
                continue;
            }
            $value  = str_replace( self::$STD_EOL, self::$INLINE_EOL, rtrim( $value ));
            $description .= sprintf(
                $FMT,
                str_pad( $key, self::$descriptionArrKeylength ),
                $value,
                self::$INLINE_EOL
            );
        } // end foreach
        if( ! empty( $description )) {
            $this->vtodo->setDescription( substr( $description, 0, ( 0 - strlen( self::$INLINE_EOL ))));
        }
    }

    /*
     * Set Vtodo Dtstart
     *
     * @param int   $startTimestamp
     */
    public function setDtstart( $startTimestamp ) {
        $this->vtodo->setDtstart( self::renderDate( $startTimestamp ));
    }

    /*
     * Set Vtodo Due
     *
     * @param int   $startTimestamp
     * @param int   $etaValue
     */
    public function setDue( $startTimestamp, $etaValue = null ) {
        if( empty( $etaValue )) {
            $etaValue = 0;
        }
        $modify = null;
        switch( $etaValue ) {
            case 0:
                break;
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
                $modify = '+ 10 years'; // ;)
                break;
        }
        if( ! empty( $modify )) {
            $this->vtodo->setDue( self::renderDate( $startTimestamp, $modify ));
        }
    }

    /*
     * Set Vtodo Last-modified
     *
     * @param int   $timestamp
     */
    public function setLastmodified( $timestamp ) {
        $this->vtodo->setLastmodified( self::renderDate( $timestamp ));
    }

    /*
     * Set Vtodo Priority
     *
     * @param int    $value
     * @param string $valueText
     */
    public function setPriority( $value, $valueText ) {
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
        }
        $this->vtodoPriority = [ $priority, $valueText ];
        $this->updateDescriptions( strtolower( Vcalendar::PRIORITY ), $valueText );
    }

    /*
     * Set Vtodo RELATED-TO
     *
     * @param string $value
     * @param array  $parameters
     */
    public function setRelatedto( $value, array $parameters ) {
        $this->vtodo->setRelatedto( $value, $parameters );
    }

    /*
     * Save bug reproducibility, will update Vtodo priority
     *
     * @param int    $value
     * @param string $valueText
     */
    public function setReproducibility( $value, $valueText ) {
        static $REPRODUCIBILITY     = 'reproducibility';
        $this->vtodoReproducibility = [ $REPRODUCIBILITY, $valueText ];
        $this->updateDescriptions( $REPRODUCIBILITY, $valueText );
    }


    /*
     * Save bug resolution status, will update Vtodo Status
     *
     * @param int    $value
     * @param string $valueText
     */
    public function setResolution( $value, $valueText ) {
        static $RESOLUTION     = 'resolution';
        $this->vtodoResolution = [ $RESOLUTION, $valueText ];
        $this->updateDescriptions( $RESOLUTION, $valueText );
    }

    /*
     * Save bug severity, will update Vtodo priority
     *
     * @param int    $value
     * @param string $valueText
     */
    public function setSeverity( $value, $valueText ) {
        static $SEVERITY     = 'severity';
        $this->vtodoSeverity = [ $SEVERITY, $valueText ];
        $this->updateDescriptions( $SEVERITY, $valueText );
    }

    /*
     * Set Vtodo Status
     *
     * @param int    $value
     * @param string $valueText
     */
    public function setStatus( $value, $valueText ) {
        static $STATUS = 'status';
        switch( $value ) {
            case 10 : // NEW_
                $status = Vcalendar::NEEDS_ACTION;
                break;
            case 20 : // FEEDBACK
                $status = Vcalendar::NEEDS_ACTION;
                break;
            case 30 : // ACKNOWLEDGED
                $status = Vcalendar::IN_PROCESS;
                break;
            case 40 : // CONFIRMED
                $status = Vcalendar::IN_PROCESS;
                break;
            case 50 : // ASSIGNED
                $status = Vcalendar::IN_PROCESS;
                $this->vtodoPartStat = Vcalendar::ACCEPTED;
                break;
            case 80 : // RESOLVED
                $status = Vcalendar::COMPLETED;
                break;
            case 80 : // CLOSED
                $status = Vcalendar::CANCELLED;
                break;
        }
        $this->vtodoStatus = [ $status, $valueText ];
        $this->updateDescriptions( $STATUS, $valueText );
    }

    /*
     * Set Vtodo Summary
     *
     * @param string $summary
     */
    public function setSummary( $summary ) {
        $this->vtodo->setSummary( $summary );
        $this->updateDescriptions( strtolower( Vcalendar::SUMMARY ), $summary );
    }

    /*
     * Get 'X-'-prefixed parameters
     *
     * @param array  $parameters
     * @access private
     * @static
     */
    private function getXprefixedParameters( $parameters ) {
        static $X = 'X-';
        $params = [];
        foreach( (array) $parameters as $k => $v ) {
            $params[$X . $k] = $v;
        }
        return $params;
    }

    /*
     * render UID
     *
     * @param int    $timestampSubmitted
     * @param int    $bugId
     * @param string $unique_id
     * @static
     */
    public static function renderUID( $timestampSubmitted, $bugId, $unique_id ) {
        static $FMTUID = '%s-%s@%s';
        return sprintf(
            $FMTUID,
            self::renderDate( $timestampSubmitted ),
            $bugId,
            $unique_id
        );
    }

    /*
     * render URL for mantis view-bug-page
     *
     * @param string $url
     * @param int    $bugId
     * @static
     */
    public static function renderURL( $url, $bugId ) {
        static $FMTURL = '%sview.php?id=%s';
        return sprintf( $FMTURL, $url, $bugId );
    }

    /**
     * Return string UTC datetime from timestamp
     *
     * @param int    $timestamp
     * @param string $modify
     * @param string $normalDateFormat
     * @return string
     * @throws Exception;
     */
    public static function renderDate( $timestamp, $modify = null, $normalDateFormat = null ) {
        static $DATEFMT = 'Ymd\THis';
        static $AT      = '@';
        static $Z       = 'Z';
        if( empty( $normalDateFormat )) {
            $normalDateFormat = $DATEFMT;
        }
        try {
            $t_date = new DateTime( $AT . $timestamp ); // UTC
            if( ! empty( $modify )) {
                $t_date->modify( $modify );
            }
            $output = $t_date->format( $normalDateFormat ) . $Z;
        }
        catch( Exception $e ) {
            if( ! empty( $modify )) {
                $timestamp = strtotime( date( $DATEFMT, $timestamp ) . ' ' . $modify );
            }
            $output = date( $normalDateFormat, $timestamp ) . $Z;
        }
        return $output;
    }
}