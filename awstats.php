<?php declare(strict_types=1);

/**
 * Project:    AWStats PHP Wrapper Class
 * File:       awstats.php
 * @author     Jeroen de Jong <jeroen@telartis.nl>
 * @copyright  2004-2023 Telartis BV
 * @version    1.4
 * @link       https://www.telartis.nl/en/awstats
 *
 * Installation instructions:
 *
 * - In your AWStats.conf set the following:
 *   WrapperScript="awstats.php"
 *
 * - Create a new script and call this class:
 *   $awstats = new \telartis\awstatstotals\awstats();
 *   $awstats->AWStatsFile = '/usr/local/awstats/cgi-bin/awstats.pl';
 *   $awstats->main();
 *
 * Changelog:
 * 1.0 initial version
 * 1.1 changed month param pattern
 * 1.2 added type declarations to function arguments and return values
 * 1.3 converted to class
 * 1.4 added namespace
 *
 */

namespace telartis\awstatstotals;

class awstats
{
    /**
     * The location of the AWStats script.
     */
    public $AWStatsFile = '/usr/local/awstats/cgi-bin/awstats.pl';

    public function main(): void
    {
        $param = $this->configparam();
        if (empty($param)) {
            die("config parameter not set!");
        }
        $param .= $this->addparam('output', '/^[a-z0-9]+$/', true);
        $param .= $this->addparam('year',   '/^\d{4}$/');
        $param .= $this->addparam('month',  '/^(\d{1,2}|all)$/');
        $param .= $this->addparam('lang',   '/^[a-z]{2}$/');
        $param .= $this->addfilterparam('hostfilter');
        $param .= $this->addfilterparam('hostfilterex');
        $param .= $this->addfilterparam('urlfilter');
        $param .= $this->addfilterparam('urlfilterex');
        $param .= $this->addfilterparam('refererpagesfilter');
        $param .= $this->addfilterparam('refererpagesfilterex');
        $param .= $this->addfilterparam('filterrawlog');

        passthru('perl '.$this->AWStatsFile.$param);
    }

    public function configparam(): string
    {
        return $this->addparam('config', '/^[-\.a-z0-9]+$/i');
    }

    public function addparam(string $name, string $pattern, bool $allways = false): string
    {
        $result = $allways ? ' -'.$name : '';
        if (isset($_GET[$name])) {
            if (preg_match($pattern, $_GET[$name])) {
                $result .= ($allways ? '' : ' -'.$name).'='.$_GET[$name];
            }
        }

        return $result;
    }

    public function addfilterparam(string $name): string
    {
        return $this->addparam($name, '/^[^;:,`| ]+$/');
    }

} // end class
