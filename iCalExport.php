<?php
# MantisBT - a php based bugtracking system

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
/**
 * requires MantisPlugin.class.php
 */
require_once( config_get( 'class_path' ) . 'MantisPlugin.class.php' );
/**
 * iCalExportPlugin Class
 */
class iCalExportPlugin extends MantisPlugin {
	public function register( ) {
		$this->name        = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->page        = '';

		$this->version     = '1.0';
		$this->requires    = array(
			'MantisCore'     => '1.2.0',
		);

		$this->author      = 'Kjell-Inge Gustafsson';
		$this->contact     = 'ical@kigkonsult.se';
		$this->url         = 'http://kigkonsult.se/iCalExport';
	}
  public function schema() {
    return array( array( "AddColumnSQL"
                       , array( db_get_table( 'mantis_bug_table' )
                              ,"
                                        sequence I UNSIGNED NOTNULL DEFAULT '0'
                                "
                              )
                       )
                );
  }
	public function hooks( ) {
		$hooks = array(
			'EVENT_MENU_FILTER' => 'export_issues_menu',
			'EVENT_MENU_ISSUE'  => 'export_issue_menu',
			'EVENT_UPDATE_BUG'  => 'bug_update_sequence',
		);
		return $hooks;
	}
	public function export_issues_menu( ) {
		return array( '<a href="' . plugin_page( 'iCalExport' ) . '" title="' . plugin_lang_get( 'export_title' ) . '">' . plugin_lang_get( 'export_name' ) . '</a>', );
	}
	public function export_issue_menu( $p_event, $p_bug_id ) {
    if( empty( $p_bug_id )) {
      return '';
    }
		return array( '<a href="' . plugin_page( 'iCalExport' ) . '&id=' . $p_bug_id . '" title="' . plugin_lang_get( 'export_title' ) . '">' . plugin_lang_get( 'export_name' ) . '</a>', );
	}
  public function bug_update_sequence( $p_event, $p_bug_data, $p_bug_id ) {
    $t_query =  'UPDATE '
             . db_prepare_string( db_get_table( 'mantis_bug_table' ))
             . ' set ' . db_prepare_string( 'sequence' ) . '=' . db_prepare_string( 'sequence' ) . '+1'
             . ' WHERE id=' . db_prepare_int( $p_bug_id );
    db_query( $t_query );
    return $p_bug_data;
  }
	public function install() {
    @include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'pages/iCalcreator.class.php'; // iCalcreator 2.x
//    @include dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'pages/iCalcreator.php';       // iCalcreator 3.x
    $result = class_exists( 'vcalendar', false );
    if( ! $result ) {
      error_parameters( plugin_lang_get( 'error_no_iCal' ));
      trigger_error( ERROR_PLUGIN_INSTALL_FAILED, ERROR );
    }
    return $result;
  }
  public function uninstall() {
    $t_query = 'ALTER '
             . db_prepare_string( db_get_table( 'mantis_bug_table' ))
             . ' DROP COLUMN ' . db_prepare_string( 'sequence' );
    db_query( $t_query);
    return TRUE;
  }
}
