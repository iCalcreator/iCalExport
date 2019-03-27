<?php
/**
 * IcalExport, Mantis calendar Export Plugin
 *
 * Adapted for iCalcreator >= 6.8
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
 * @version    2.2
 * @since      1.1 - 2017-05-08
 *
 * This file is a part of IcalExport.
 */
/**
 * requires MantisPlugin.class.php
 */
require_once( config_get( 'class_path' ) . 'MantisPlugin.class.php' );

/**
 * IcalExportPlugin Class
 */
class IcalExportPlugin extends MantisPlugin
{
    public function register() {
        $this->name        = plugin_lang_get( 'title' );
        $this->description = plugin_lang_get( 'description' );
        $this->page        = '';

        $this->version  = '2.0';
        $this->requires = [
            'MantisCore' => '2.0.0',
        ];

        $this->author  = 'Kjell-Inge Gustafsson, kigkonsult';
        $this->contact = 'ical@kigkonsult.se';
        $this->url     = 'https://github.com/iCalcreator/IcalExport';
    }

    public function hooks() {
        $hooks = [
            'EVENT_MENU_FILTER' => 'export_issues_menu',
            'EVENT_MENU_ISSUE'  => 'export_issue_menu',
        ];
        return $hooks;
    }

    function init() {
        require_once 'api/iCalcreator/autoload.php';
    }

    public function export_issues_menu( $p_event ) {
        static $t_atag = '<a class="btn btn-primary btn-white btn-round btn-sm" href="%s" title="%s">%s</a>';
        return [
            sprintf(
                $t_atag,
                plugin_page( 'IcalExport' ),
                plugin_lang_get( 'export_title2' ),
                plugin_lang_get( 'export_name' )
            ),
        ];
    }

    public function export_issue_menu( $p_event, $p_bug_id ) {
        static $t_atag = '<a class="btn btn-primary btn-white btn-round btn-sm" href="%s&id=%s" title="%s">%s</a>';
        if( empty( $p_bug_id )) {
            return '';
        }
        return [
            sprintf(
                $t_atag,
                plugin_page( 'IcalExport' ),
                $p_bug_id,
                plugin_lang_get( 'export_title1' ),
                plugin_lang_get( 'export_name' )
            ),
        ];
    }
}
