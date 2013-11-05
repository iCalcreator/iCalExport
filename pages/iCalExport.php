<?php
# MantisBT - a php based bugtracking system
# Copyright (C) 2002 - 2013  MantisBT Team - mantisbt-dev@lists.sourceforge.net
# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.
/**
 * iCal Export Plugin
 * @package    MantisPlugin
 * @subpackage iCalExport
 * @copyright  Copyright (C) 2013 Kjell-Inge Gustafsson, kigkonsult, All rights reserved.
 * @license    GNU General Public License (GPL)
 * @link       http://kigkonsult.se/iCalExport
 * @author     Kjell-Inge Gustafsson, kigkonsult <ical@kigkonsult.se>
 * @version    1.0
 * @since      1.0 - 2013-11-02
 */
/* MantisBT Core API's */
require_once( 'core.php' );
require_once( 'user_api.php' );
require_once( 'columns_api.php' );
require_once( 'file_api.php' );
require_once( 'bugnote_api.php' );
require_once( 'tag_api.php' );
require_once( 'gpc_api.php' );
auth_ensure_user_authenticated();
helper_begin_long_process();
ob_clean();
# grab the user id currently logged in etc.
$t_user_id       = auth_get_current_user_id();
$t_user_name     = user_get_name( $t_user_id );
$t_bugnote_order = current_user_get_pref( 'bugnote_order' );
$t_normal_date_format = config_get( 'normal_date_format' );
$f_bug_id        = gpc_get_int( 'id', 0 );
if( ! empty( $f_bug_id )) {
# Get bug row according to parameter id
  require_once( 'bug_api.php' );
	bug_ensure_exists( $f_bug_id );
  $t_result      = bug_get( $f_bug_id, true );
  if(( $t_result === false ) || empty( $t_result )) {
    exit();
  }
  $t_result      = array( $t_result );
}
else {
# Get bug rows according to the current filter
  require_once( 'filter_api.php' );
  $t_page_number = 1;
  $t_per_page    = -1;
  $t_bug_count   = null;
  $t_page_count  = null;
  $t_result      = filter_get_bug_rows( $t_page_number, $t_per_page, $t_page_count, $t_bug_count );
  if(( $t_result === false ) || empty( $t_result )) {
    exit();
  }
}
$t_show_tags     = access_has_global_level( config_get( 'tag_view_threshold' ));
$t_url           = config_get( 'path' );
# Ignored fields, these will be skipped
$t_ignore        = array( '_stats', 'bug_text_id', );
# properties that we want to export are 'protected', also add iCalExport 'sequence'
$t_columns       = array_keys( getClassProperties( 'BugData', 'protected' ));
$t_columns[]     = 'sequence';
# iCalcreator is required
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'iCalcreator.class.php'; // iCalcreator 2.x
// require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'iCalcreator.php';       // iCalcreator 3.x
# iCalcreator setup
$t_calendar      = new vcalendar();
$t_parsed_url    = parse_url( $t_url );                     // TODO, find site/system unique id??!!
$t_unique_id     = $t_parsed_url['host'];                   // TODO, find site/system unique id??!!
$t_calendar->setConfig( 'unique_id', $t_unique_id );
// $t_calendar->setProperty( 'X-path', config_get( 'absolute_path_default_upload_folder' )); // test ###
# format iCal dates with UTC timezone
function localTimestamp2UTCdate( $p_value=null, $p_modify=FALSE ) {
  global $g_default_timezone;
  if( empty( $p_value ))
    $p_value     = time();
  $t_fmt         = 'Ymd\THis';
  try {
    $t_date = new DateTime( date( $t_fmt, $p_value ), new DateTimeZone( $g_default_timezone ));
    if( $p_modify )
      $t_date->modify( $p_modify );
    $t_date->setTimezone( new DateTimeZone( 'UTC' ));       // set UTC timezone
    return $t_date->format( $t_fmt.'Z' );
  }
  catch( Exception $e ) {
    if( $p_modify )
      $p_value   = strtotime( date( $t_fmt, $p_value ) . ' ' . $p_modify );
    return date( $t_fmt, $p_value );                        // 'float' (local) timezone
  }
}
$_iCal_inl_eol   = '\n ';                                   // iCal text inline end-of-line characters
# this iCal TODO calendar is simply a 'posting' one, NO 'request' TODO calendar (i.e. issues are already assigned)
$t_calendar->setProperty( 'METHOD', 'PUBLISH' );
# set some optional iCal calendar properties, 'required' by some calendar software
$t_calendar->setProperty( 'X-WR-CALNAME', $t_user_name . plugin_lang_get( 'x_wr_calname' ));
//$t_calendar->setProperty( 'X-WR-CALDESC', $t_user_name . plugin_lang_get( 'x_wr_caldesc' ) . $t_unique_id . ' (' . localTimestamp2UTCdate() . ')' );
$t_calendar->setProperty( 'X-WR-CALDESC', $t_user_name . plugin_lang_get( 'x_wr_caldesc' ) . $t_unique_id . ' (' . date( $t_normal_date_format ) . ')' );
$t_calendar->setProperty( 'X-WR-TIMEZONE', 'UTC' );
# map mantis priorities to iCal priorities
$t_priorities    = array( 10 => 0, 20 => 7, 30 => 5, 40 => 4, 50 => 2, 60 => 1 );
# mantis bug properties order in an iCal DESCRIPTION property
$t_descrArr      = array( 'project'                => ''
                        , 'id'                     => ''
                        , 'summary'                => ''
                        , 'reporter'               => ''
                        , 'handler'                => ''
                        , 'category'               => ''
                        , 'priority'               => ''
                        , 'severity'               => ''
                        , 'reproducibility'        => ''
                        , 'status'                 => ''
                        , 'resolution'             => ''
                        , 'projection'             => ''
                        , 'eta'                    => ''
                        , 'view_state'             => ''
                        , 'description'            => ''
                        , 'steps_to_reproduce'     => ''
                        , 'additional_information' => ''
                        , 'duplicate_id'           => ''
//                      , 'bug_text_id'            => '' // ??
                        , 'os'                     => ''
                        , 'os_build'               => ''
                        , 'platform'               => ''
                        , 'version'                => ''
                        , 'fixed_in_version'       => ''
                        , 'build'                  => ''
//                      , 'profile_id'             => '' // ??
                        , 'sponsorship_total'      => ''
                        , 'sticky'                 => ''
                        , 'target_version'         => ''
                        );
$t_descrArr_kw   = 0;
foreach( $t_descrArr as $t_key => $t_value )
  if( $t_descrArr_kw < strlen( $t_key ))
# map mantis eta property to date modification string
$t_etas          = array( 10 => '', 20 => '+ 1 day', 30 => '+ 3 days', 40 => '+ 7 days', 50 => '+ 1 month', 60 => '+ 2 month' );
# export each row into an vcalendar vtodo object instance
foreach( $t_result as $t_bug ) {
  if( ! isset( $t_bug->id ))
    continue;
  $t_vtodo       = $t_calendar->newComponent( 'vtodo' );
  $t_dtstart     = $t_eta = $t_due = $t_organizer = $t_priority = $t_summary = null;
  foreach( $t_descrArr as $t_k => $t_v )
    $t_descrArr[$t_k] = '';
# set iCal component UID property, Universal IDentifier
  $t_uid_date    = ( isset( $t_bug->date_submitted )) ? $t_bug->date_submitted : null;
  $t_vtodo->setProperty( 'UID', localTimestamp2UTCdate( $t_uid_date ) . ':'.$t_bug->id . '@' . $t_unique_id );
# set iCal component URL property, a link back to mantis bug report
  $t_vtodo->setProperty( 'URL', $t_url.'view.php?id='.$t_bug->id );
  foreach( $t_columns as $t_element ) {
    if( in_array( $t_element, $t_ignore ) ) {
      continue;
    }
    $t_value     = $t_bug->$t_element;
    if( empty( $t_value ) ) {
      continue;
    }
    switch( $t_element ) {
      case 'id':
        if( array_key_exists( $t_element, $t_descrArr ))
          $t_descrArr[$t_element] = $t_value;
        break;
      case 'project_id':
        if( array_key_exists( 'project', $t_descrArr ))
          $t_descrArr['project'] = project_get_name( $t_value );
        break;
# set iCal component CONTACT property using mantis bug report property reporter_id
      case 'reporter_id':
        $t_vtodo->setProperty( 'CONTACT', user_get_email( $t_value ));
        if( array_key_exists( 'reporter', $t_descrArr ))
          $t_descrArr['reporter'] = user_get_name( $t_value );
        break;
# set iCal component ORGANIZER property using mantis bug report property handler_id
      case 'handler_id':
        $t_organizer = $t_value;
        $t_vtodo->setProperty( 'ORGANIZER', user_get_email( $t_value ));
        if( array_key_exists( 'handler', $t_descrArr ))
          $t_descrArr['handler'] = user_get_name( $t_value );
        break;
# set iCal component SEQUENCE property from mantis bug report property sequence
      case 'sequence':
        $t_vtodo->setProperty( 'SEQUENCE', $t_value );
        break;
# set iCal component PRIORITY property using mantis bug report property priority
      case 'priority':
        $t_priority = $t_value;
        if( isset( $t_priorities[$t_value] ) && ! empty( $t_priorities[$t_value] )) {
          $t_vtodo->setProperty( 'PRIORITY', $t_priorities[$t_value] );
          if( array_key_exists( $t_element, $t_descrArr ))
            $t_descrArr[$t_element] = get_enum_element( $t_element, $t_value );
        }
        break;
# set iCal component CATEGORIES property using mantis bug report property category_id
      case 'category_id':
        $t_category = category_get_name( $t_value );
        $t_vtodo->setProperty( 'CATEGORIES', $t_category );
        if( array_key_exists( 'category', $t_descrArr ))
          $t_descrArr['category'] = $t_category;
        break;
# set iCal component CREATED and DTSTART properties from mantis bug report property date_submitted
      case 'date_submitted':
        $t_dtstart = $t_value;
        $t_vtodo->setProperty( 'DTSTART', localTimestamp2UTCdate( $t_value ));
        $t_vtodo->setProperty( 'CREATED', localTimestamp2UTCdate( $t_value ));
        break;
# set iCal component LAST-MODIFIED property from mantis bug report property last_updated
      case 'last_updated':
        $t_vtodo->setProperty( 'LAST-MODIFIED', localTimestamp2UTCdate( $t_value ));
        break;
      case 'eta':
        $t_eta = $t_value;
        if( array_key_exists( $t_element, $t_descrArr ))
          $t_descrArr[$t_element] = get_enum_element( $t_element, $t_value );
        break;
# set iCal component DUE property from mantis bug report property due_date
      case 'due_date':
        if( ! date_is_null( $t_value )) {
          $t_due = localTimestamp2UTCdate( $t_value );
          $t_vtodo->setProperty( 'DUE', $t_due );
        }
        break;
# set iCal component STATUS property using mantis bug report property view_state
      case 'view_state':
        switch( $t_value ) {
          case 50:
            $t_vtodo->setProperty( 'STATUS', 'PRIVATE' );
            break;
          default:
            $t_vtodo->setProperty( 'STATUS', 'PUBLIC' );
            break;
        }
      case 'severity':
      case 'reproducibility':
      case 'status':
      case 'resolution':
      case 'projection':
        if( array_key_exists( $t_element, $t_descrArr ))
          $t_descrArr[$t_element] = get_enum_element( $t_element, $t_value );
        break;
# set iCal component SUMMARY property from mantis bug report property summary
      case 'summary':
        $t_summary = $t_value;
        $t_vtodo->setProperty( 'SUMMARY', $t_value );
      default:
        if( array_key_exists( $t_element, $t_descrArr ))
          $t_descrArr[$t_element] = $t_value;
    } // end switch( $t_element )
  } // end foreach( $t_columns as $t_element )
# convert mantis eta property to an iCal DUE date property, based on mantis bug report property date_submitted
  if( ! empty( $t_dtstart ) && empty( $t_due ) && ! empty( $t_eta ) &&
     isset( $t_etas[$t_eta] ) && ! empty( $t_etas[$t_eta] ))
    $t_vtodo->setProperty( 'DUE', localTimestamp2UTCdate( $t_dtstart, $t_etas[$t_eta] ));
# assure iCal DTSTART is set
  if( empty( $t_dtstart ))
    $t_vtodo->setProperty( 'DTSTART', localTimestamp2UTCdate()); // assure any date is set!!
# assure iCal ORGANIZER is set
  if( empty( $t_organizer ))
    $t_vtodo->setProperty( 'ORGANIZER', user_get_email( $t_user_id ));
# assure iCal PRIORITY is set
  if( empty( $t_priority ))
    $t_vtodo->setProperty( 'PRIORITY', 0 );
# assure iCal SUMMARY is set
  if( empty( $t_summary ))
    $t_vtodo->setProperty( 'SUMMARY', '' );
# concatenate 'all' mantis bug report properties into one iCal DESCRIPTION (with iCal row breaks)
  $t_descr       = '';
  foreach( $t_descrArr as $t_key => $t_value ) {
    if( empty( $t_value ))
      continue;
    $t_value   = str_replace( "\n", $_iCal_inl_eol, rtrim( $t_value ));
    $t_descr  .= str_pad( $t_key, $t_descrArr_kw ).' : '.$t_value.$_iCal_inl_eol;
  } // end foreach( $t_descrArr as $t_key => $t_value )
  if( ! empty( $t_descr ))
    $t_vtodo->setProperty( 'description', substr( $t_descr, 0, -3 ));
# export each mantis bug report bugnote to an iCal COMMENT
  $t_bugnotes  = bugnote_get_all_visible_bugnotes( $t_bug->id, $t_bugnote_order, 0, $t_user_id );
  foreach( $t_bugnotes as $t_bugnote ) {
    if( BUGNOTE == $t_bugnote->note_type )
      $t_vtodo->setProperty( 'COMMENT', user_get_name( $t_bugnote->reporter_id )
                                      . ' ('. date( $t_normal_date_format, $t_bugnote->date_submitted ) . ')'
                                      . ' [' . $t_bugnote->id . '] :' . $_iCal_inl_eol
                                      . str_replace( "\n", $_iCal_inl_eol, rtrim( $t_bugnote->note )));
  } // end foreach( $t_bugnotes as $t_bugnote )
# export each mantis bug report tag to an (additional) iCal CATEGORIES
  if ( $t_show_tags ) {
  	$t_tags    = tag_bug_get_attached( $p_bug_id );
    foreach( $t_tags as $t_tag )
      $t_vtodo->setProperty( 'CATEGORIES', $p_tag['name'] );
  }
# export each mantis bug report attachment to an iCal ATTACH (link)
  $t_attachments = file_get_visible_attachments( $t_bug->id );
  foreach ( $t_attachments as $t_attachment ) {
    if( $t_attachment['can_download'] && $t_attachment['exists'] )
      $t_vtodo->setProperty( 'ATTACH', $t_url . $t_attachment['download_url'] );
  } // end foreach ( $t_attachments as $t_attachment )
} // end foreach( $t_result as $t_bug )
$t_calendar->sort();
$t_calendar->returnCalendar();
exit();
