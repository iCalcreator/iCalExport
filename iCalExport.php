<?php
/**
 * Copyright (C) 2013 Kjell-Inge Gustafsson, kigkonsult, All rights reserved <ical@kigkonsult.se>
 *
 * iCalExport is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * iCalExport is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with iCalExport.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * iCal Export Plugin
 * @package    MantisPlugin
 * @subpackage iCalExport
 * @copyright  Copyright (C) 2013 Kjell-Inge Gustafsson, kigkonsult, All rights reserved.
 * @license    GNU General Public License (GPL)
 * @link       http://kigkonsult.se/iCalExport
 * @author     Kjell-Inge Gustafsson, kigkonsult <ical@kigkonsult.se>
 * @version    1.05
 * @since      1.05 - 2013-11-09
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

		$this->version     = '1.0rc1';
		$this->requires    = array(
			'MantisCore'     => '1.3.0',
		);

		$this->author      = 'Kjell-Inge Gustafsson';
		$this->contact     = 'ical@kigkonsult.se';
		$this->url         = 'http://kigkonsult.se/iCalExport';
	}
  public function schema() {
    $t_bug_update_sequence_table = plugin_table( 'bug_update_sequence' );
    return array( array( 'CreateTableSQL'
                       , array( $t_bug_update_sequence_table, '
                                        id I NOTNULL UNSIGNED PRIMARY,
                                        sequence I NOTNULL UNSIGNED
                                '
                              )
                       ),
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
  function init() {
    require_once 'api/iCalcreator.class.php'; // iCalcreator 2.x
//    require_once 'api/iCalcreator.php';       // iCalcreator 3.x
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
    $t_table      = db_prepare_string( plugin_table( 'bug_update_sequence' ));
    $t_query      =  'SELECT * FROM ' . $t_table . ' WHERE id=' .  db_param();
    $t_result     = db_query_bound( $t_query, Array( $p_bug_id ));
    $t_rows       = db_num_rows( $t_result );
    if( empty( $t_rows )) {
      $t_query    =  'INSERT INTO ' . $t_table . ' VALUES (' . db_param() . ', ' . db_param() . ')';
      $t_result   = db_query_bound( $t_query, Array( $p_bug_id, 2 ));
    }
    else {
      $t_sequence = db_prepare_string( 'sequence' );
      $t_query    =  'UPDATE ' . $t_table . ' SET ' . $t_sequence . '=' . $t_sequence . '+1' . ' WHERE id=' .  db_param();
      $t_result   = db_query_bound( $t_query, Array( $p_bug_id ));
    }
    return $p_bug_data;
  }
}
