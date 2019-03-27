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
// $t_parsed_url = parse_url( $t_url );  // TODO, find site/system unique id??!!
// $t_unique_id  = $t_parsed_url['host'];
$t_unique_id          = config_get( 'hostname' );
$t_url                = config_get( 'path' );
$t_normal_date_format = config_get( 'normal_date_format' );

/*
 * get bug id(s)
 */
$f_bug_id  = gpc_get_int( 'id', 0 );
$t_cnt     = 0;
if( empty( $f_bug_id )) {
    /*
     * Get bug rows according to the current filter
     */
    require_api( 'filter_api.php' );
    $t_page_number  = 1;
    $t_per_page     = -1;
    $t_page_count   = null;
    $t_bug_count    = null;
    $t_result = filter_get_bug_rows(
        $t_page_number,
        $t_per_page,
        $t_page_count,
        $t_bug_count
    );
    $t_cnt          = count( $t_result );
    $t_calname      = plugin_lang_get( 'x_wr_calname2' );
    $t_caldesc      = plugin_lang_get( 'x_wr_caldesc2' );
    $t_redirect_url = 'view_all_bug_page.php';
}
else {
    /*
     * Get bug row according to parameter id
     */
    bug_ensure_exists( $f_bug_id );
    $t_result = bug_get( $f_bug_id, true );
    if( ! empty( $t_result )) {
        $t_cnt    = 1;
        $t_result = [ $t_result ];
    }
    $t_calname      = plugin_lang_get( 'x_wr_calname1' ) . $f_bug_id;
    $t_caldesc      = plugin_lang_get( 'x_wr_caldesc1' ) . $f_bug_id;
    $t_redirect_url = 'view.php?id=' . $f_bug_id;
}
/*
 * If no found, return
 */
if( empty( $t_cnt )) {
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
 * iCalcreator loader setup
 */
require_once( 'CalendarLoader.php' );
$t_calendarLoader = new CalendarLoader( $t_unique_id, $t_normal_date_format, $t_user_name, $t_calname, $t_caldesc );

/*
 * export each bug/row into an Vcalendar vtodo object instance
 */
$t_cnt = 0;
foreach( $t_result as $t_ix => $t_bug ) {
    if( ! isset( $t_bug->id )) {
        continue;
    }
    $t_cnt += 1;

    /*
     * Initiate a new Vtodo with
     * SEQUENCE property set from number of rows in table bug_history (+1)
     */
    $t_last_updated = $t_eta = $t_due = null;
    $t_created = ( isset( $t_bug->date_submitted )) ? $t_bug->date_submitted : time();
    $t_calendarLoader->initNewVtodo(
        $t_created,
        $t_url,
        $t_bug->id,
        get_bug_history_count( $t_bug->id ),
        $t_user_email
    );

    foreach( $t_columns as $t_ix2 => $t_element ) {
        if( in_array( $t_element, $t_ignore )) {
            continue;
        }
        $t_value = $t_bug->{$t_element};
        /*
         * Ignore empty values
         */
        if( empty( $t_value )) {
            continue;
        }
        switch( $t_element ) {
            case 'id':
                $t_calendarLoader->updateDescriptions( $t_element, $t_value );
                break;
            case 'project_id':
                $t_calendarLoader->updateDescriptions( 'project', project_get_name( $t_value ));
                break;
            /*
             * Set iCal component ATTENDEE property
             */
            case 'reporter_id':
                $t_value2 = user_get_email( $t_value );
                $t_calendarLoader->updateAttendee(
                    $t_value2,
                    user_get_name( $t_value ),
                    [ 'DELEGATED-FROM' => true ]
                );
                $t_calendarLoader->updateDescriptions( 'reporter', $t_value2 );
                break;
            /*
             * Set iCal component ORGANIZER/ATTENDEE/DTSTART property
             */
            case 'handler_id':
                $t_calendarLoader->updateAttendee(
                    user_get_email( $t_value ),
                    user_get_name( $t_value ),
                    [ 'ROLE' => 'CHAIR' ]
                );
                $t_dtstart = get_assignment_start_date( $t_bug->id, $t_value );
                if( empty( $t_dtstart )) {
                    $t_dtstart = $t_bug->date_submitted;
                }
                $t_calendarLoader->setDtstart( $t_dtstart );
                break;
            /*
             * Set iCal component CATEGORIES property
             */
            case 'category_id':
                $t_category_name = category_get_name( $t_value );
                $t_calendarLoader->setCategories( [ $t_category_name ] );
                $t_calendarLoader->updateDescriptions( 'category', $t_category_name );
                break;
            /*
             * already fixed
             */
            case 'date_submitted':
                break;
            /*
             * Save for later use
             */
            case 'last_updated':
                $t_last_updated = $t_value;
                break;
            /*
             * Save for later use
             */
            case 'eta':
                $t_eta = $t_value;
                $t_calendarLoader->updateDescriptions( $t_element, get_enum_element( $t_element, $t_value ));
                break;
            /*
             * Set iCal component DUE property
             */
            case 'due_date':
                if( ! date_is_null( $t_value )) {
                    $t_due = $t_value;
                    $t_calendarLoader->setDue( $t_value );
                }
                break;
            /*
             * Set iCal component PRIORITY property
             */
            case 'priority':
                $t_calendarLoader->setPriority( $t_value, get_enum_element( $t_element, $t_value ));
                break;
            /*
             * Set iCal component STATUS property parameter
             */
            case 'resolution':
                $t_calendarLoader->setResolution( $t_element, get_enum_element( $t_element, $t_value ));
                break;
            /*
             * Set iCal component CLASS property
             */
            case 'view_state':
                $t_calendarLoader->setClass( $t_value );
                break;
            /*
             * Used as iCal component STATUS property parameter
             */
            case 'severity':
                $t_calendarLoader->setSeverity( $t_element, get_enum_element( $t_element, $t_value ));
                break;
            /*
             * Used as iCal component PRIORITY property parameter
             */
            case 'reproducibility':
                $t_calendarLoader->setReproducibility( $t_element, get_enum_element( $t_element, $t_value ));
                break;
            /*
             * Set as iCal component STATUS property parameter
             */
            case 'status':
                $t_calendarLoader->setStatus( $t_value, get_enum_element( $t_element, $t_value ));
                break;
            case 'projection':
                $t_calendarLoader->updateDescriptions( $t_element, get_enum_element( $t_element, $t_value ));
                break;
            /*
             * Set iCal component SUMMARY property
             */
            case 'summary':
                $t_calendarLoader->setSummary( $t_value );
                break;
            default:
                $t_calendarLoader->updateDescriptions( $t_element, $t_value );
                break;
        } // end switch( $t_element )
    } // end foreach( $t_columns as $t_ix2 => $t_element )



    /*
     * Set iCal component LAST-MODIFIED property
     */
    if( ! empty( $t_last_updated ) && ( $t_last_updated > $t_created )) {
        $t_calendarLoader->setLastmodified( $t_last_updated );
    }

    /*
     * Create an iCal DUE date property, based on created (above),
     * only if mantis due is not set and eta is set,
     */
    if( empty( $t_due ) && ! empty( $t_eta )) {
        $t_calendarLoader->setDue( $t_created, $t_eta );
    }

    /*
     * Export each mantis bug report bugnote as an iCal COMMENT
     */
    $t_bugnot_users = [];
    foreach(
        (array) bugnote_get_all_visible_bugnotes(
            $t_bug->id,
            current_user_get_pref( 'bugnote_order' ),
            0,
            $t_user_id
        )
        as $t_bugnote ) {
        if(( BUGNOTE    == $t_bugnote->note_type )&&
           ( VS_PRIVATE != $t_bugnote->view_state )) {
            $t_name     = user_get_name( $t_bugnote->reporter_id );
            $t_email    = user_get_email( $t_bugnote->reporter_id );
            $t_calendarLoader->setComment(
                $t_bugnote->note,
                [
                    'id'             => $t_bugnote->id,
                    'type'           => 'bugnote',
                    'date-submitted' => date( $t_normal_date_format, $t_bugnote->date_submitted ),
                    'name'           => $t_name,
                ]
            );
            if( isset( $t_bugnot_users[$t_email] )) {
                $t_bugnot_users[$t_email]['id'][] = $t_bugnote->id;
            }
            else {
                $t_bugnot_users[$t_email] = ['name' => $t_name, 'id' => [$t_bugnote->id]];
            }
        }
    } // end foreach
    foreach( $t_bugnot_users as $t_email => $t_data ) {
        $t_calendarLoader->updateAttendee(
            $t_email,
            $t_data['name'],
            [ 'x-bugnote' => implode( ',', $t_data['id'] ) ]
        );
    }

    /*
     * Export each mantis bug monitors as iCal ATTENDEEs
     */
    foreach( ( array) bug_get_monitors( $t_bug->id ) as $t_monitor_id ) {
        $t_calendarLoader->updateAttendee(
            user_get_email( $t_monitor_id ),
            user_get_name( $t_monitor_id ),
            [ 'x-role' => 'monitor' ]
        );
    }

    /*
     * Export each mantis bug report relations as an iCal RELATED-TOs
     */
    foreach( ( array) relationship_get_all( $t_bug->id, $t_is_different_projects ) as $t_relationship ) {
        if( $t_bug->id == $t_relationship->src_bug_id ) {
            $t_reltype    = 'PARENT';
            $t_rel_bug_id = $t_relationship->dest_bug_id;
        }
        else {
            $t_reltype    = 'CHILD';
            $t_rel_bug_id = $t_relationship->src_bug_id;
        }
        $t_rel_bug      = bug_get( $t_rel_bug_id );
        $date_submitted = $t_rel_bug->date_submitted;
        $t_calendarLoader->setRelatedto(
            CalendarLoader::renderUID( $date_submitted, $t_rel_bug_id, $t_unique_id ),
            [
                'RELTYPE'          => $t_reltype,
                'x-type'           => relationship_get_name_for_api( $t_relationship->type ),
                'x-id'             => $t_rel_bug_id,
                'x-date-submitted' =>
                    CalendarLoader::renderDate( $date_submitted, null, $t_normal_date_format ),
                'x-summary'        => $t_rel_bug->summary,
                'x-url'            => CalendarLoader::renderURL( $t_url, $t_rel_bug_id )
            ]
        );
    }

    /*
     * export each mantis bug report tag(s) as (additional) iCal CATEGORIES
     */
    if( $t_show_tags ) {
        foreach( (array) tag_bug_get_attached( $t_bug->id ) as $t_tag ) {
            $t_calendarLoader->setCategories(
                $t_tag['name'],
                [
                    'date-submitted' => date( $t_normal_date_format, $t_tag['date_attached'] ),
                    'name'           => user_get_name( $t_tag['user_attached'] ),
                    'origin' => 'tags',
                ]
            );
        }
    }

    /*
     * Export each mantis bug report attachments as iCal ATTACHs (link)
     */
    $t_calendarLoader->setAttachments(
        (array) file_get_visible_attachments( $t_bug->id ),
        $t_url
    );

    /*
     * Close Vtodo
     */
    $t_calendarLoader->closeVtodo();

} // end foreach( $t_result as $t_bug )

if( empty( $t_cnt )) {
    require_api( 'print_api.php' );
    print_header_redirect( $t_redirect_url );
}
$t_calendarLoader->returnCalendar();
exit();

/**
 * Return (first) startdate for bug assignment from bug_history
 *
 * @param int $p_bug_id
 * @param int $p_user_id
 * @return int|bool  timestamp or false (not found)
 */
function get_assignment_start_date( $p_bug_id, $p_user_id ) {
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
function get_bug_history_count( $p_bug_id ) {
    static $t_fmt_query = 'SELECT COUNT(*) AS %s FROM {bug_history} WHERE bug_id=%s';
    static $t_fmt_antal = 'antal';
	$t_query  = sprintf( $t_fmt_query, $t_fmt_antal, db_param());
    $t_result = db_query( $t_query, [ $p_bug_id ] );
    $t_rows   = db_num_rows( $t_result );
    if( empty( $t_rows )) {
        $t_result = 1;
    }
    else {
        $row = db_fetch_array( $t_result );
        $t_result = (int) $row[$t_fmt_antal] + 1;
    }
    return $t_result;
}
