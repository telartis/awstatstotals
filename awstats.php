<?php

/**
 * AWStats PHP Wrapper Script
 *
 * In your AWStats.conf set the following:
 * WrapperScript="awstats.php"
 *
 * @author     Jeroen de Jong <jeroen@telartis.nl>
 * @copyright  2004-2023 Telartis BV
 * @version    1.2
 * @link       https://www.telartis.nl/en/awstats
 *
 * Changelog:
 * 1.0 initial version
 * 1.1 changed month param pattern
 * 1.2 added type declarations to function arguments and return values
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
