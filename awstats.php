<?php declare(strict_types=1);

/**
 * Project:    AWStats PHP Wrapper Script
 * File:       awstats.php
 * @author     Jeroen de Jong <jeroen@telartis.nl>
 * @copyright  2004-2023 Telartis BV
 * @version    1.0
 *
 */

require_once 'awstats.class.php';

class my_awstats extends awstats
{
    // public $AWStatsFile = '/usr/local/awstats/cgi-bin/awstats.pl';
}

$awstats = new my_awstats();
$awstats->main();
