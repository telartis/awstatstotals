<?php declare(strict_types=1);

/**
 * Project:    AWStats Totals
 * File:       awstatstotals.php
 * @author     Jeroen de Jong <jeroen@telartis.nl>
 * @copyright  2004-2023 Telartis BV
 * @version    1.0
 *
 */

require_once 'awstatstotals.class.php';

class my_awstatstotals extends awstatstotals
{
    // public $DirData             = '/var/lib/awstats';
    // public $DirLang             = '/usr/share/awstats/lang';
    // public $AWStatsURL          = '/cgi-bin/awstats.pl';
    // public $Lang                = 'auto';
    // public $NotViewed           = 'sum'; // ignore, columns, sum
    // public $sort_default        = 'bandwidth';  // config, unique, visits, pages, hits, bandwidth, not_viewed_pages, not_viewed_hits, not_viewed_bandwidth
    // public $dec_point           = '.';
    // public $thousands_sep       = ' ';
    // public $FilterConfigs       = [];
    // public $FilterIgnoreConfigs = [];

    // public function __construct()
    // {
    //     $sth = $dbh->prepare('SELECT config FROM websites WHERE user=:id)');
    //     $sth->execute();
    //     $this->FilterConfigs = $sth->fetchColumn();
    // }
}

$awstatstotals = new my_awstatstotals();
$awstatstotals->main();
