<?php

/**
 * AWStats PHP Wrapper Script
 *
 * In your AWStats.conf set the following:
 * WrapperScript="awstats.php"
 *
 * @author      Jeroen de Jong <jeroen@telartis.nl>
 * @copyright   2004-2022 Telartis BV
 * @version     1.2
 *
 * @link        https://www.telartis.nl/en/awstats
 *
 * Changelog:
 * 1.0 initial version
 * 1.1 changed month param pattern
 * 1.2 added type declarations to function arguments and return values
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

/**
 * The location of the AWStats script.
 */
$AWStatsFile = '/usr/local/awstats/cgi-bin/awstats.pl';


$param = addparam('config', '/^[-\.a-z0-9]+$/i');
if (empty($param)) {
    die("config parameter not set!");
}
$param .= addparam('output', '/^[a-z0-9]+$/', true);
$param .= addparam('year',   '/^\d{4}$/');
$param .= addparam('month',  '/^(\d{1,2}|all)$/');
$param .= addparam('lang',   '/^[a-z]{2}$/');
$param .= addfilterparam('hostfilter');
$param .= addfilterparam('hostfilterex');
$param .= addfilterparam('urlfilter');
$param .= addfilterparam('urlfilterex');
$param .= addfilterparam('refererpagesfilter');
$param .= addfilterparam('refererpagesfilterex');
$param .= addfilterparam('filterrawlog');

passthru('perl '.$AWStatsFile.$param);


function addparam(string $name, string $pattern, bool $allways = false): string
{
    $result = $allways ? ' -'.$name : '';
    if (isset($_GET[$name])) {
        if (preg_match($pattern, $_GET[$name])) {
            $result .= ($allways ? '' : ' -'.$name).'='.$_GET[$name];
        }
    }
    return $result;
}

function addfilterparam(string $name): string
{
    return addparam($name, '/^[^;:,`| ]+$/');
}
