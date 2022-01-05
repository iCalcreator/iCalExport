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
/*
 * MantisBT Core API's
 */
require_once( 'core.php' );
require_api( 'user_api.php' );
require_api( 'bug_api.php' );
require_api( 'bugnote_api.php' );
require_api( 'file_api.php' );
require_api( 'gpc_api.php' );
require_api( 'relationship_api.php' );
require_api( 'tag_api.php' );
auth_ensure_user_authenticated();
helper_begin_long_process();
ob_clean();

/*
 * get the user currently logged in
 */
$t_user_id    = auth_get_current_user_id();
$t_user_name  = user_get_name( $t_user_id );
$t_user_email = user_get_email( $t_user_id );
$t_show_tags  = access_has_global_level( config_get( 'tag_view_threshold' ));

/*
 * get some config values
 */
$t_unique_id          = config_get( 'hostname' );  // TODO, find site/system unique id??!!
$t_url                = config_get( 'path' );
$t_normal_date_format = config_get( 'normal_date_format' );
$t_timezone           = config_get_global( 'default_timezone' );
if( is_blank( $t_timezone )) {
    $t_timezone       = @date_default_timezone_get();
}

/*
 * get bug id(s)
 */
$f_bug_id    = gpc_get_int( 'id', 0 );
$t_bug_count = 0;
if( empty( $f_bug_id )) {
    /*
     * Get bug rows according to the current filter
     */
    require_api( 'filter_api.php' );
    $t_page_number  = 1;
    $t_per_page     = -1;
    $t_page_count   = null;
    $t_result       = filter_get_bug_rows( $t_page_number, $t_per_page, $t_page_count, $t_bug_count );
    $t_calname      = plugin_lang_get( 'x_wr_calname2' );
    $t_caldesc      = plugin_lang_get( 'x_wr_caldesc2' );
    $t_redirect_url = 'view_all_bug_page.php';
} // end if
else {
    /*
     * Get bug row according to parameter id
     */
    bug_ensure_exists( $f_bug_id );
    $t_result = bug_get( $f_bug_id, true );
    if( ! empty( $t_result )) {
        $t_bug_count = 1;
        $t_result    = [ $t_result ];
    }
    $t_calname       = plugin_lang_get( 'x_wr_calname1' ) . $f_bug_id;
    $t_caldesc       = plugin_lang_get( 'x_wr_caldesc1' ) . $f_bug_id;
    $t_redirect_url  = 'view.php?id=' . $f_bug_id;
} // end else

/*
 * If no found, return
 */
if( empty( $t_bug_count )) {
    require_api( 'print_api.php' );
    print_header_redirect( $t_redirect_url );
}

/*
 * properties that we want to export are 'protected'
 */
$t_columns = array_keys( getClassProperties( 'BugData', 'protected' ));

/*
 * Ignored fields, these will be skipped
 */
$t_ignore = [ '_stats', 'bug_text_id', ];

/*
 * iCalcreator Vcalendar loader setup
 */
require_once( 'CalendarLoader.php' );
$t_calendarLoader = CalendarLoader::factory( $t_unique_id )
    ->setCalendarName( $t_user_name )
    ->setCalendarDescription( $t_user_name, $t_caldesc, $t_normal_date_format )
    ->setCalendarXpropTimezone( $t_timezone )
    ->setCalendarurl( $t_url . $t_redirect_url );
/*
 * export each bug/row into an Vcalendar vtodo object instance
 */
$t_bug_count = 0;
foreach( $t_result as $t_ix => $t_bugData ) {
    if( ! isset( $t_bugData->id )) {
        continue;
    }
    ++$t_bug_count;

    /*
     * Initiate a new Vtodo
     * SEQUENCE property set from number of rows in table bug_history (+1)
     */
    $t_eta = $t_due = null;
    $t_created      = $t_bugData->date_submitted ?? time();
    $t_calendarLoader->initNewVtodo( $t_created, (int) $t_bugData->id )
        ->setVtodoCreated( $t_created )
        ->setVtodoUrl( $t_url, (int) $t_bugData->id )
        ->setVtodoSequenceNo( get_bug_history_count( $t_bugData->id ))
        ->setVtodOrganizer( $t_user_email );

    foreach( $t_columns as $t_ix2 => $t_element ) {
        if( in_array( $t_element, $t_ignore, true ) ) {
            continue;
        }
        $t_value = $t_bugData->{$t_element};
        /*
         * Ignore empty values
         */
        if( empty( $t_value ) && ! is_numeric( $t_value )) {
            continue;
        }

        switch( $t_element ) {
            case 'id':
                $t_calendarLoader->updateVtodoDescriptions( $t_element, $t_value );
                break;
            case 'project_id':
                $t_calendarLoader->updateVtodoDescriptions( 'project', project_get_name( $t_value ));
                break;
            /*
             * Set/update iCal Vtodo ATTENDEE (DELEGATED-FROM) property (reporter
             */
            case 'reporter_id':
                $t_value2 = user_get_email( $t_value );
                $t_calendarLoader->updateVtodoAttendee(
                    $t_value2,
                    user_get_name( $t_value ),
                    [ 'DELEGATED-FROM' => true ]
                );
                $t_calendarLoader->updateVtodoDescriptions( 'reporter', $t_value2 );
                break;
            /*
             * Set iCal Vtodo ORGANIZER/ATTENDEE/DTSTART property
             */
            case 'handler_id':
                $t_calendarLoader->updateVtodoAttendee(
                    user_get_email( $t_value ),
                    user_get_name( $t_value ),
                    [ 'ROLE' => 'CHAIR' ]
                );
                $t_dtstart = get_assignment_start_date((int) $t_bugData->id, $t_value );
                if( empty( $t_dtstart )) {
                    $t_dtstart = $t_bugData->date_submitted;
                }
                $t_calendarLoader->setVtodoDtstart( $t_dtstart, $t_timezone );
                break;
            /*
             * Set iCal Vtodo CATEGORIES property
             */
            case 'category_id':
                $t_category_name = category_get_name( $t_value );
                $t_calendarLoader->setVtodoCategories( $t_category_name );
                $t_calendarLoader->updateVtodoDescriptions( 'category', $t_category_name );
                break;
            /*
             * already fixed
             */
            case 'date_submitted':
                break;
            /*
             * Set iCal Vtodo LAST-MODIFIED property (UTC), Vcalendar with latest last-mod
             */
            case 'last_updated':
                if( $t_value > $t_created ) {
                    $t_calendarLoader->setLastModified( $t_value );
                }
                break;
            /*
             * Save for later use, Vtodo DUEDATE, here only if eta is enabled in config
             */
            case 'eta':
                $t_eta = $t_value;
                $t_calendarLoader->updateVtodoDescriptions( $t_element, get_enum_element( $t_element, $t_value ));
                break;
            /*
             * Set iCal Vtodo DUE property
             */
            case 'due_date':
                if( ! date_is_null( $t_value )) {
                    $t_due = $t_value;
                    $t_calendarLoader->setVtodoDue( $t_value, $t_timezone );
                }
                break;
            /*
             * Set iCal Vtodo PRIORITY property
             */
            case 'priority':
                $t_calendarLoader->setVtodoPriority( $t_value, get_enum_element( $t_element, $t_value ));
                break;
            /*
             * Base for iCal Vtodo STATUS status/descriptions
             */
            case 'resolution':
                $t_calendarLoader->setVtodoResolution( get_enum_element( $t_element, $t_value ) );
                break;
            /*
             * Set iCal Vtodo CLASS property
             */
            case 'view_state':
                $t_calendarLoader->setVtodoClass( $t_value );
                break;
            /*
             * Set iCal Vtodo priority/descriptions
             */
            case 'severity':
                $t_calendarLoader->setVtodoSeverity( get_enum_element( $t_element, $t_value ) );
                break;
            /*
             * Used as base for iCal Vtodo PRIORITY property parameter
             */
            case 'reproducibility':
                $t_calendarLoader->setVtodoReproducibility( get_enum_element( $t_element, $t_value ) );
                break;
            /*
             * Set iCal Vtodo STATUS property parameter
             */
            case 'status':
                $t_calendarLoader->setVtodoStatus( $t_value, get_enum_element( $t_element, $t_value ));
                break;
            case 'projection':
                $t_calendarLoader->updateVtodoDescriptions( $t_element, get_enum_element( $t_element, $t_value ));
                break;
            /*
             * Set iCal Vtodo SUMMARY property
             */
            case 'summary':
                $t_calendarLoader->setVtodoSummary( $t_value );
                break;
            default:
                $t_calendarLoader->updateVtodoDescriptions( $t_element, $t_value );
                break;
        } // end switch( $t_element )
    } // end foreach( $t_columns as $t_ix2 => $t_element )

    /*
     * Create/replace an iCal DUE date property, based on created (above),
     * only if mantis due is not set and eta is set,
     */
    if( empty( $t_due ) && ! empty( $t_eta )) {
        $t_calendarLoader->setVtodoDue( $t_created, $t_timezone, $t_eta );
    }

    /*
     * Export each mantis bug report attachments as iCal ATTACHs (link)
     * Collect bugnote_id / attach_id relations
     */
    $t_attachments  = file_get_visible_attachments( $t_bugData->id );
    $t_attach_notes = [];
    if( ! empty( $t_attachments )) {
        foreach( $t_attachments as $t_attachment ) {
            if( ! empty( $t_attachment['bugnote_id'] )) {
                $t_attach_notes[$t_attachment['bugnote_id']] = $t_attachment['id'];
            }
        } // end foreach
        $t_calendarLoader->setVtodoAttachments( $t_attachments, $t_url );
    } // end if

    /*
     * Export each mantis bug report bugnote as an iCal COMMENT
     * Collect bugnote attendees
     */
    $t_bugnot_users = [];
    foreach(
        (array) bugnote_get_all_visible_bugnotes(
            $t_bugData->id,
            current_user_get_pref( 'bugnote_order' ),
            0,
            $t_user_id
        )
        as $t_bugnote ) {
        if(( BUGNOTE === $t_bugnote->note_type ) && ( VS_PRIVATE !== $t_bugnote->view_state )) {
            $t_name       = user_get_name( $t_bugnote->reporter_id );
            $t_email      = user_get_email( $t_bugnote->reporter_id );
            $t_parameters =
                [
                    'bugnote-id'     => $t_bugnote->id,
                    'type'           => 'bugnote',
                    'date-submitted' => date( $t_normal_date_format, $t_bugnote->date_submitted ),
                    'name'           => $t_name,
                ];
            if( isset( $t_attach_notes[$t_bugnote->id] )) {
                $t_parameters['attach-id'] = $t_attach_notes[$t_bugnote->id];
            }
            $t_calendarLoader->setVtodoComment( $t_bugnote->note, $t_parameters );
            if( isset( $t_bugnot_users[$t_email] )) {
                $t_bugnot_users[$t_email]['id'][] = $t_bugnote->id;
            }
            else {
                $t_bugnot_users[$t_email] = [ 'name' => $t_name, 'id' => [ $t_bugnote->id ] ];
            }
        } // end if
    } // end foreach

    /*
     * Set/Update Vtodo (bugnotes) ATTENDEEs
     */
    foreach( $t_bugnot_users as $t_email => $t_data ) {
        $t_calendarLoader->updateVtodoAttendee(
            $t_email,
            $t_data['name'],
            [ 'x-bugnote' => implode( ',', $t_data['id'] ) ]
        );
    } // end foreach

    /*
     * Export each mantis bug monitors as iCal (monitor) ATTENDEEs
     */
    foreach( ( array) bug_get_monitors( $t_bugData->id ) as $t_monitor_id ) {
        $t_calendarLoader->updateVtodoAttendee(
            user_get_email( $t_monitor_id ),
            user_get_name( $t_monitor_id ),
            [ 'x-role' => 'monitor' ]
        );
    } // end foreach

    /*
     * Export each mantis bug report relations as an iCal RELATED-TOs
     */
    foreach( ( array) relationship_get_all( $t_bugData->id, $t_is_different_projects ) as $t_relationship ) {
        $t_rel_bug_id = ( $t_bugData->id == $t_relationship->src_bug_id ) // NOTE ==
            ? $t_relationship->dest_bug_id
            : $t_relationship->src_bug_id;
        switch( $t_relationship->type ) {
            case 2 :
                $t_reltype = 'PARENT';
                break;
            case 1 : // fall through
            case 3 :
                $t_reltype = 'CHILD';
                break;
            case 0 : // fall through
            case 4 : // fall through
            default:
                $t_reltype = 'SIBLING';
                break;
        } // end switch
        $t_rel_bug        = bug_get( $t_rel_bug_id );
        $t_date1          = $t_rel_bug->date_submitted;
        $t_date2          = CalendarLoader::timestampToDateTime( $t_date1, $t_timezone );
        $t_calendarLoader->setVtodoRelatedto(
            CalendarLoader::renderUID( $t_date1, $t_rel_bug_id, $t_unique_id ),
            [
                'RELTYPE'          => $t_reltype,
                'x-type'           => relationship_get_name_for_api( $t_relationship->type ),
                'x-id'             => $t_rel_bug_id,
                'x-date-submitted' => CalendarLoader::renderIcalDateTime( $t_date2, $t_timezone ),
                'x-summary'        => $t_rel_bug->summary,
                'x-url'            => CalendarLoader::renderURL( $t_url, $t_rel_bug_id )
            ]
        );
    } // end foreach

    /*
     * export each mantis bug report tag(s) as (additional) iCal CATEGORIES
     */
    if( $t_show_tags ) {
        foreach( (array) tag_bug_get_attached( $t_bugData->id ) as $t_tag ) {
            $t_calendarLoader->setVtodoCategories(
                $t_tag['name'],
                [
                    'date-submitted' => date( $t_normal_date_format, $t_tag['date_attached'] ),
                    'name'           => user_get_name( $t_tag['user_attached'] ),
                    'origin'         => 'tags',
                ]
            );
        } // end foreach
    } // end if

    /*
     * Close Vtodo
     */
    $t_calendarLoader->closeVtodo();

} // end foreach( $t_result as $t_bugData )

if( empty( $t_bug_count )) {
    require_api( 'print_api.php' );
    print_header_redirect( $t_redirect_url );
}
$t_calendarLoader->returnCalendar();
exit();

/**
 * Return (first) startdate for user bug assignment from bug_history
 *
 * @param int $p_bug_id
 * @param int $p_user_id
 * @return int|bool  timestamp or false (not found)
 */
function get_assignment_start_date( int $p_bug_id, int $p_user_id )
{
    static $t_fmt_query =
        'SELECT date_modified FROM {bug_history} WHERE bug_id=%s AND field_name=%s AND new_value=%s ORDER BY 1';
    static $t_fmt_handler_id = 'handler_id';
    $t_query  = sprintf( $t_fmt_query, db_param(), db_param(), db_param());
    $t_result = db_query( $t_query, [ $p_bug_id, $t_fmt_handler_id, $p_user_id ] );
    $t_rows   = db_num_rows( $t_result );
    return ( empty( $t_rows )) ? false : (int) db_result( $t_result );
}

/**
 * Return number of rows (+1) in database table bug_history for bug_id as sequence number
 *
 * @param int $p_bug_id
 * @return int
 */
function get_bug_history_count( int $p_bug_id ) : int
{
    static $t_fmt_query = 'SELECT COUNT(*) AS %s FROM {bug_history} WHERE bug_id=%s';
    static $t_fmt_antal = 'antal';
	$t_query  = sprintf( $t_fmt_query, $t_fmt_antal, db_param());
    $t_result = db_query( $t_query, [ $p_bug_id ] );
    $t_rows   = db_num_rows( $t_result );
    if( empty( $t_rows )) {
        $t_result = 1;
    }
    else {
        $t_row    = db_fetch_array( $t_result );
        $t_result = (int) $t_row[$t_fmt_antal] + 1;
    }
    return $t_result;
}
