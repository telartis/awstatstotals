<?php declare(strict_types=1);

/**
 * Project:    AWStats Totals
 * File:       awstatstotals.php
 * Purpose:    A simple php class to view the totals
 *             (Unique visitors, Number of visits, Pages, Hits, Bandwidth)
 *             for multiple sites per month with sort options.
 * @author     Jeroen de Jong <jeroen@telartis.nl>
 * @copyright  2004-2024 Telartis BV
 * @link       https://www.telartis.nl/en/awstats
 *
 * Functions:
 * main(array $params = []): void
 * main_fetch(array $params = []): string
 * year_data(string $config, int $year): array
 * month_data(string $config, int $year, int $month, bool $complete_month = true): array
 * month_totals(string $config, int $year, int $month, $default = null, string $file = ''): array
 * pages_url_list(string $config, int $year, int $month): array
 * errors404_list(string $config, int $year, int $month): array
 * block_lines(string $name, string $file): array
 * get_configs_files(int $year, int|string $month): array(configs, files)
 * get_filename(string $config, int $year, int $month): string
 * split_filename(string $file): array(config, year, month)
 * parse_dir(string $dir): array
 * detect_language(string $dir): string
 * read_language_data(string $file): array
 * add_trailing_slash(string $file): string
 * remove_trailing_slash(string $file): string
 * byte_format($number, int $decimals = 2): string
 * num_format($number, int $decimals = 0): string
 * lz(int $number): string
 * fetch(int|string $month, int $year, array $rows, array $totals, array $message): string
 * fetch_form(string $script_url, int|string $month, int $year, array $message): string
 * fetch_table_header(string $url, array $message): string
 * fetch_table_body(array $rows, array $totals, string $url, array $message): string
 * fetch_template(): string
 *
 */

namespace telartis\awstatstotals;

// Installation instructions:
//
// 1) Create a new script and call this class:
//   $awstatstotals = new \telartis\awstatstotals\awstatstotals;
//   $awstatstotals->DirData    = '/var/lib/awstats';
//   $awstatstotals->DirLang    = '/usr/share/awstats/lang';
//   $awstatstotals->AWStatsURL = '/cgi-bin/awstats.pl';
//   $awstatstotals->main();
//
// - OR -
//
// 2) Uncomment these two lines if you want to call this class/script directly:
// $obj = new awstatstotals;
// $obj->main();

class awstatstotals
{
    const VERSION = '1.24.0';

    /**
     * Set this value to the directory where AWStats
     * saves its database and working files into.
     */
    public $DirData = '/var/lib/awstats';

    /**
     * The URL of the AWStats script.
     */
    public $AWStatsURL = '/cgi-bin/awstats.pl';

    /**
     * Set your language.
     * Possible value:
     *  Albanian=al, Bosnian=ba, Bulgarian=bg, Catalan=ca,
     *  Chinese (Taiwan)=tw, Chinese (Simpliefied)=cn, Czech=cz, Danish=dk,
     *  Dutch=nl, English=en, Estonian=et, Euskara=eu, Finnish=fi,
     *  French=fr, Galician=gl, German=de, Greek=gr, Hebrew=he, Hungarian=hu,
     *  Icelandic=is, Indonesian=id, Italian=it, Japanese=jp, Korean=kr,
     *  Latvian=lv, Norwegian (Nynorsk)=nn, Norwegian (Bokmal)=nb, Polish=pl,
     *  Portuguese=pt, Portuguese (Brazilian)=br, Romanian=ro, Russian=ru,
     *  Serbian=sr, Slovak=sk, Spanish=es, Swedish=se, Turkish=tr, Ukrainian=ua,
     *  Welsh=wlk.
     *  First available language accepted by browser=auto
     */
    public $Lang = 'auto';

    /**
     * Set the location of language files.
     */
    public $DirLang = '/usr/share/awstats/lang';

    /**
     * How to display not viewed traffic
     * Possible value: ignore, columns, sum
     */
    public $NotViewed = 'sum';

    /**
     * How to sort.
     * Possible value:
     * config, unique, visits, pages, hits, bandwidth,
     * not_viewed_pages, not_viewed_hits, not_viewed_bandwidth
     */
    public $sort_default = 'bandwidth';

    /**
     * Set number format.
     */
    public $dec_point     = '.';
    public $thousands_sep = ' ';

    /**
     * Config names to filter. Shows all if empty array.
     */
    public $FilterConfigs = [];

    /**
     * Config names to ignore.
     */
    public $FilterIgnoreConfigs = [];

    /*

    To read website configs from database, extend class and do something like:

    public function __construct()
    {
        $sth = $dbh->prepare('SELECT config FROM websites WHERE user=:id)');
        $sth->execute();
        $this->FilterConfigs = $sth->fetchColumn();
    }

    */

    /**
     * Main program
     *
     * @param  array    $params  Optional, default $_GET
     * @return void     Echoed HTML
     */
    public function main(array $params = []): void
    {
        echo $this->main_fetch($params);
    }

    /**
     * Get main HTML
     *
     * @param  array    $params  Optional, default $_GET
     * @return string   HTML
     */
    public function main_fetch(array $params = []): string
    {
        if (!$params) {
            $params = $_GET;
        }
        $sort  = isset($params['sort'])  ? preg_replace('/[^_a-z]/', '', $params['sort']) : $this->sort_default;
        $year  = isset($params['year'])  ? (int) $params['year']  : (int) date('Y');
        $month = isset($params['month']) ? (int) $params['month'] : (int) date('n');
        if (!$month) {
            $month = 'all';
        }

        if (!is_dir($this->DirData)) {

            return 'Could not open directory '.$this->DirData;
        }

        [$configs, $files] = $this->get_configs_files($year, $month);

        $totals = [
            'visits_total'               => 0,
            'unique_total'               => 0,
            'pages_total'                => 0,
            'hits_total'                 => 0,
            'bandwidth_total'            => 0,
            'not_viewed_pages_total'     => 0,
            'not_viewed_hits_total'      => 0,
            'not_viewed_bandwidth_total' => 0,
        ];

        $rows = [];
        if ($files) {
            array_multisort($configs, $files);
            $row_prev = [];
            for ($i = 0, $cnt = count($files); $i <= $cnt; $i++) {
                $row = [];
                if ($i < $cnt) {
                    [$config, $y, $m] = $this->split_filename($files[$i]);
                    $row = $this->month_totals($config, $y, $m, 0, $files[$i]);

                    if ($this->NotViewed == 'sum') {
                        $row['pages']     += $row['not_viewed_pages'];
                        $row['hits']      += $row['not_viewed_hits'];
                        $row['bandwidth'] += $row['not_viewed_bandwidth'];
                    }

                    $totals['visits_total']    += $row['visits'];
                    $totals['unique_total']    += $row['unique'];
                    $totals['pages_total']     += $row['pages'];
                    $totals['hits_total']      += $row['hits'];
                    $totals['bandwidth_total'] += $row['bandwidth'];

                    if ($this->NotViewed == 'columns') {
                        $totals['not_viewed_pages_total']     += $row['not_viewed_pages'];
                        $totals['not_viewed_hits_total']      += $row['not_viewed_hits'];
                        $totals['not_viewed_bandwidth_total'] += $row['not_viewed_bandwidth'];
                    }
                }
                if ( isset($row['config']) && isset($row_prev['config']) && ($row['config'] == $row_prev['config']) ) {
                    $row['visits']    += $row_prev['visits'];
                    $row['unique']    += $row_prev['unique'];
                    $row['pages']     += $row_prev['pages'];
                    $row['hits']      += $row_prev['hits'];
                    $row['bandwidth'] += $row_prev['bandwidth'];

                    if ($this->NotViewed == 'columns') {
                        $row['not_viewed_pages']     += $row_prev['not_viewed_pages'];
                        $row['not_viewed_hits']      += $row_prev['not_viewed_hits'];
                        $row['not_viewed_bandwidth'] += $row_prev['not_viewed_bandwidth'];
                    }
                } elseif ($i > 0) {
                    $rows[] = $row_prev;
                }
                $row_prev = $row;
            }
        }

        if ($sort == 'config') {
            sort($rows);
        } else {
            array_multisort(array_column($rows, $sort), SORT_DESC, $rows);
        }

        $this->DirLang = $this->remove_trailing_slash($this->DirLang);

        if ($this->Lang == 'auto') {
            $this->Lang = $this->detect_language($this->DirLang);
        }

        $message = $this->read_language_data($this->DirLang.'/awstats-'.$this->Lang.'.txt');

        return $this->fetch($month, $year, $rows, $totals, $message);
    }

    /**
     * Get year data for each month
     *
     * @param  string   $config
     * @param  integer  $year
     * @return array(yyyy-mm-01 => [config, visits, unique, pages, hits, bandwidth, not_viewed_...])
     */
    public function year_data(string $config, int $year): array
    {
        $data = [];
        for ($month = 1; $month <= 12; $month++) {
            $date = $year.'-'.$this->lz($month).'-01';
            $data[$date] = $this->month_totals($config, $year, $month);
        }

        return $data;
    }

    /**
     * Get month data for each day
     *
     * @param  string   $config
     * @param  integer  $year
     * @param  integer  $month
     * @param  boolean  $complete_month  Optional, default TRUE
     * @return array(yyyy-mm-dd => [pages, hits, bandwidth, visits])
     */
    public function month_data(string $config, int $year, int $month, bool $complete_month = true): array
    {
        $data = [];
        if (checkdate($month, 1, $year) && $complete_month) {
            // initialize data array with null values for all days in the given month:
            $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            for ($day = 1; $day <= $days; $day++) {
                $date = $year.'-'.$this->lz($month).'-'.$this->lz($day);
                $data[$date] = [
                    'pages'     => null,
                    'hits'      => null,
                    'bandwidth' => null,
                    'visits'    => null,
                ];
            }
        }
        $file = $this->get_filename($config, $year, $month);
        foreach ($this->block_lines('DAY', $file) as $line) {
            [$dt, $pages, $hits, $bandwidth, $visits] = explode(' ', $line);
            $date = substr($dt, 0, 4).'-'.substr($dt, 4, 2).'-'.substr($dt, 6, 2); // convert yyyymmdd to yyyy-mm-dd
            $data[$date] = [
                'pages'     => (int) $pages,
                'hits'      => (int) $hits,
                'bandwidth' => (int) $bandwidth,
                'visits'    => (int) $visits,
            ];
        }

        return $data;
    }

    /**
     * Get month totals
     *
     * @param  string   $config
     * @param  integer  $year
     * @param  integer  $month
     * @param  mixed    $default  Optional, default NULL
     * @param  string   $file     Optional, default ''
     * @return array(config, visits, unique, pages, hits, bandwidth, not_viewed_pages, not_viewed_hits, not_viewed_bandwidth)
     */
    public function month_totals(string $config, int $year, int $month, $default = null, string $file = ''): array
    {
        if (empty($file)) {
            $file = $this->get_filename($config, $year, $month);
        }
        $visits = $default;
        $unique = $default;
        foreach ($this->block_lines('GENERAL', $file) as $line) {
            [$key, $val] = explode(' ', $line, 2);
            if ($key == 'TotalVisits') $visits = (int) $val;
            if ($key == 'TotalUnique') $unique = (int) $val;
        }
        $rows = [];
        foreach ($this->block_lines('TIME', $file) as $line) {
            $rows[] = array_map('intval', explode(' ', $line));
        }
        $result = [
            'config' => $config,
            'visits' => $visits,
            'unique' => $unique,
        ];
        $keys = [
            'pages',
            'hits',
            'bandwidth',
            'not_viewed_pages',
            'not_viewed_hits',
            'not_viewed_bandwidth',
        ];
        foreach ($keys as $i => $key) {
            $result[$key] = array_sum(array_column($rows, $i + 1));
        }

        return $result;
    }

    /**
     * Get full list of Pages-URLs
     *
     * @param  string   $config
     * @param  integer  $year
     * @param  integer  $month
     * @return array(url, pages, bandwidth, entry, exit)
     */
    public function pages_url_list(string $config, int $year, int $month): array
    {
        $data = [];
        $file = $this->get_filename($config, $year, $month);
        foreach ($this->block_lines('SIDER', $file) as $line) {
            [$url, $pages, $bandwidth, $entry, $exit] = explode(' ', $line);
            $data[] = [
                'url'       => $url,
                'pages'     => (int) $pages,
                'bandwidth' => (int) $bandwidth,
                'entry'     => (int) $entry,
                'exit'      => (int) $exit,
            ];
        }

        return $data;
    }

    /**
     * Get list of URLs with 404 errors
     *
     * @param  string   $config
     * @param  integer  $year
     * @param  integer  $month
     * @return array(url, hits, referer)
     */
    public function errors404_list(string $config, int $year, int $month): array
    {
        $data = [];
        $file = $this->get_filename($config, $year, $month);
        foreach ($this->block_lines('SIDER_404', $file) as $line) {
            [$url, $hits, $referer] = explode(' ', $line);
            $data[] = [
                'url'     => $url, // URL with 404 errors
                'hits'    => (int) $hits,
                'referer' => $referer, // Last URL referer
            ];
        }

        return $data;
    }

    /**
     * Get lines array from within BEGIN/END-block
     *
     * @param  string   $name  Block name
     * @param  string   $file  Config file name
     * @return array
     */
    public function block_lines(string $name, string $file): array
    {
        $result = [];
        if (!empty($file)) {
            $handle = fopen($file, 'r');
            $is_block = false;
            while (!feof($handle)) {
               $line = trim((string) fgets($handle, 4096));
               if (preg_match('/^BEGIN_'.$name.' \d+$/', $line)) {
                    $is_block = true;
               } elseif ($line == 'END_'.$name) {
                   break;
               } elseif ($is_block) {
                    $result[] = $line;
               }
            }
            fclose($handle);
        }

        return $result;
    }

    /**
     * Get configs and files arrays
     *
     * @param  integer     $year
     * @param  int|string  $month
     * @return array($configs, $files)
     */
    public function get_configs_files(int $year, $month): array
    {
        $files = [];
        $configs = [];
        $pattern = '/awstats'.($month == 'all' ? '\d{2}' : $this->lz((int) $month)).$year.'\.(.+)\.txt$/';
        foreach ($this->parse_dir($this->DirData) as $file) {
            if (preg_match($pattern, $file, $match)) {
                $config = $match[1];
                if ((!$this->FilterConfigs || in_array($config, $this->FilterConfigs))
                    && !in_array($config, $this->FilterIgnoreConfigs)) {
                    $configs[] = $config;
                    $files[] = $file;
                }
            }
        }

        return [$configs, $files];
    }

    /**
     * Get config file name or empty string when file is not found
     *
     * @param  string   $config
     * @param  integer  $year
     * @param  integer  $month
     * @return string
     */
    public function get_filename(string $config, int $year, int $month): string
    {
        $name = 'awstats'.$this->lz($month).$year.'.'.$config.'.txt';
        $filename = $this->remove_trailing_slash($this->DirData).'/'.$name;
        if (!file_exists($filename)) {
            $filename = '';
            $pattern = '/'.preg_quote($name, '/').'$/';
            foreach ($this->parse_dir($this->DirData) as $file) {
                if (preg_match($pattern, $file, $match)) {
                    $filename = $file;
                    break;
                }
            }
        }

        return $filename;
    }

    /**
     * Split config file name
     *
     * @param  string   $file
     * @return array(config, year, month)
     */
    public function split_filename(string $file): array
    {
        [, $month, $year, $config] = preg_match('/awstats(\d{2})(\d{4})\.(.+)\.txt$/', $file, $match)
            ? $match
            : [null, 0, 0, ''];

        return [$config, (int) $year, (int) $month];
    }

    /**
     * Recursive directory parsing to include support for nested data directories
     *
     * @param  string   $dir
     * @return array
     */
    public function parse_dir(string $dir): array
    {
        $files = [];
        if (!empty($dir)) {
            $dir = $this->add_trailing_slash($dir);
            if ($dh = @opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (!preg_match('/^\./s', $file)) {
                        if (is_dir($dir.$file)) {
                            $newdir = $dir.$file.'/';
                            chdir($newdir);
                            $files = array_merge($files, $this->parse_dir($newdir));
                        } else {
                            $files[] = $dir.$file;
                        }
                    }
                }
                chdir($dir);
            }
        }

        return $files;
    }

    /**
     * Detect Language
     *
     * @param  string   $dir
     * @return string
     */
    public function detect_language(string $dir): string
    {
        $lang = '';
        $languages = (string) filter_input(INPUT_SERVER, 'HTTP_ACCEPT_LANGUAGE',
            FILTER_DEFAULT,
            FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_BACKTICK
        );
        foreach (explode(',', $languages) as $lang) {
            $lang = strtolower(substr(trim($lang), 0, 2));
            if (!empty($lang) && file_exists("$dir/awstats-$lang.txt")) {
                break;
            } else {
                $lang = '';
            }
        }
        if (empty($lang)) {
            $lang = 'en';
        }

        return $lang;
    }

    /**
     * Read Language Data
     *
     * @param  string   $file
     * @return array
     */
    public function read_language_data(string $file): array
    {
        $message = [];
        if (!empty($file) && file_exists($file)) {
            foreach (file($file) as $line) {
                if (preg_match('/^message(\d+)=(.*)$/', $line, $match)) {
                    $message[$match[1]] = $match[2];
                }
            }
        }
        if (!$message) {
            $message[7]   = 'Statistics for';
            $message[10]  = 'Number of visits';
            $message[11]  = 'Unique visitors';
            $message[56]  = 'Pages';
            $message[57]  = 'Hits';
            $message[60]  = 'Jan';
            $message[61]  = 'Feb';
            $message[62]  = 'Mar';
            $message[63]  = 'Apr';
            $message[64]  = 'May';
            $message[65]  = 'Jun';
            $message[66]  = 'Jul';
            $message[67]  = 'Aug';
            $message[68]  = 'Sep';
            $message[69]  = 'Oct';
            $message[70]  = 'Nov';
            $message[71]  = 'Dec';
            $message[75]  = 'Bandwidth';
            $message[102] = 'Total';
            $message[115] = 'OK';
            $message[133] = 'Reported period';
            $message[160] = 'Viewed traffic';
            $message[161] = 'Not viewed traffic';
        }

        return $message;
    }

    /**
     * Add trailing slash
     *
     * @param  string   $file
     * @return string
     */
    public function add_trailing_slash(string $file): string
    {
        $file = trim($file);
        if (substr($file, -1) != '/') {
            $file .= '/';
        }

        return $file;
    }

    /**
     * Remove trailing slash
     *
     * @param  string   $file
     * @return string
     */
    public function remove_trailing_slash(string $file): string
    {
        $file = trim($file);
        if (substr($file, -1) == '/') {
            $file = substr($file, 0, -1);
        }

        return $file;
    }

    /**
     * Byte Format
     *
     * @param  int|float|string  $number
     * @param  integer  $decimals
     * @return string
     */
    public function byte_format($number, int $decimals = 2): string
    {
        // kilo, mega, giga, tera, peta, exa, zetta, yotta, ronna, quetta
        $prefix_arr = ['','K','M','G','T','P','E','Z','Y','R','Q'];
        $i = 0;
        if ($number == 0) {
            $result = 0;
        } else {
            $value = round((float) $number, $decimals);
            while ($value > 1024) {
                $value /= 1024;
                $i++;
            }
            $result = $this->num_format($value, $decimals);
        }
        $result .= ' '.$prefix_arr[$i].'B'.($i == 0 ? 'ytes' : '');

        return $result;
    }

    /**
     * Number Format
     *
     * @param  int|float|string  $number
     * @param  integer  $decimals
     * @return string
     */
    public function num_format($number, int $decimals = 0): string
    {
        return number_format((float) $number, $decimals, $this->dec_point, $this->thousands_sep);
    }

    /**
     * Formatting a number with leading zeros
     *
     * @param  integer  $number
     * @return string
     */
    public function lz(int $number): string
    {
        return substr('0'.$number, -2);
    }

    /**
     * Fetch HTML
     *
     * @param  int|string  $month
     * @param  integer  $year
     * @param  array    $rows
     * @param  array    $totals
     * @param  array    $message
     * @return string
     */
    public function fetch($month, int $year, array $rows, array $totals, array $message): string
    {
        $script_url = (string) filter_input(INPUT_SERVER, 'SCRIPT_URL',
            FILTER_DEFAULT,
            FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_BACKTICK
        );

        $sort_url   =       $script_url.'?month='.$month.'&year='.$year.'&sort=';
        $config_url = $this->AWStatsURL.'?month='.$month.'&year='.$year.'&config=';

        return str_replace(
            '[content]',
            $this->fetch_form($script_url, $month, $year, $message).
            '<table class="content" align="center">'."\n".
            $this->fetch_table_header($sort_url, $message).
            $this->fetch_table_body($rows, $totals, $config_url, $message).
            '</table>'."\n",
            $this->fetch_template()
        );
    }

    /**
     * Fetch HTML form
     *
     * @param  string   $script_url
     * @param  int|string  $month
     * @param  integer  $year
     * @param  array    $message
     * @return string
     */
    public function fetch_form(string $script_url, $month, int $year, array $message): string
    {
        $html = '<form action="'.$script_url.'">
<table class="form b" border="0" cellpadding="2" cellspacing="0" width="100%">
<tr><td class="l">

<table class="d" border="0" cellpadding="8" cellspacing="0" width="100%">
<tr>
<th>'.$message[133].':</th>
<td class="l">';
    $html .= '<select class="f" name="month">'."\n";
    for ($i = 1; $i <= 12; $i++) {
        $html .= '<option value="'.$i.'"'.($month == $i ? ' selected' : '').'>'.$message[$i + 59]."\n";
    }
    $html .= '<option value="all"'.($month == 'all' ? ' selected' : '').'>-'."\n";
    $html .= '</select>'."\n";

    $html .= '<select class="f" name="year">'."\n";
    for ($curyear = date('Y'), $i = $curyear - 4; $i <= $curyear; $i++) {
        $html .= '<option value="'.$i.'"'.($year == $i ? ' selected' : '').'>'.$i."\n";
    }
    $html .= '</select>'."\n";
    $html .= '<input type="submit" class="f" value="'.$message[115].'">
</td></tr>
</table>

</td></tr>
</table>
</form>

';

        return $html;
    }

    /**
     * Fetch HTML table header
     *
     * @param  string   $url
     * @param  array    $message
     * @return string
     */
    public function fetch_table_header(string $url, array $message): string
    {
        $html = '';
        if ($this->NotViewed == 'columns') {
            $html .= '<tr>';
            $html .= '<td>&nbsp;'; // Statistics for config
            $html .= '<td class="border" colspan="5">'.$message[160];      // Viewed traffic
            $html .= '<td class="border" colspan="3">'.$message[161]."\n"; // Not viewed traffic
            $html .= "\n";
        }
        $html .= '<tr>';
        $html .= '<td bgcolor="#ECECEC" class="l" nowrap>&nbsp;<a href="'.$url.'config" class="h">'.$message[7].'</a>';
        $html .= '<td width="80" bgcolor="#FFB055"><a href="'.$url.'unique"    class="h">'.$message[11].'</a>';
        $html .= '<td width="80" bgcolor="#F8E880"><a href="'.$url.'visits"    class="h">'.$message[10].'</a>';
        $html .= '<td width="80" bgcolor="#4477DD"><a href="'.$url.'pages"     class="h">'.$message[56].'</a>';
        $html .= '<td width="80" bgcolor="#66F0FF"><a href="'.$url.'hits"      class="h">'.$message[57].'</a>';
        $html .= '<td width="80" bgcolor="#2EA495"><a href="'.$url.'bandwidth" class="h">'.$message[75].'</a>';
        if ($this->NotViewed == 'columns') {
            $html .= '<td width="80" bgcolor="#4477DD"><a href="'.$url.'not_viewed_pages"     class="h">'.$message[56].'</a>';
            $html .= '<td width="80" bgcolor="#66F0FF"><a href="'.$url.'not_viewed_hits"      class="h">'.$message[57].'</a>';
            $html .= '<td width="80" bgcolor="#2EA495"><a href="'.$url.'not_viewed_bandwidth" class="h">'.$message[75].'</a>';
        }
        $html .= "\n";

        return $html;
    }

    /**
     * Fetch HTML table body
     *
     * @param  array    $rows
     * @param  array    $totals
     * @param  string   $url
     * @param  array    $message
     * @return string
     */
    public function fetch_table_body(array $rows, array $totals, string $url, array $message): string
    {
        $html = '';
        foreach ($rows as $row) {
            $html .= '<tr>'.
                '<td class="l"><a href="'.$url.$row['config'].'">'.$row['config'].'</a>'.
                '<td>'.$this->num_format($row['unique']).
                '<td>'.$this->num_format($row['visits']).
                '<td>'.$this->num_format($row['pages']).
                '<td>'.$this->num_format($row['hits']).
                '<td>'.$this->byte_format($row['bandwidth']);
            if ($this->NotViewed == 'columns') {
                $html .=
                '<td>'.$this->num_format($row['not_viewed_pages']).
                '<td>'.$this->num_format($row['not_viewed_hits']).
                '<td>'.$this->byte_format($row['not_viewed_bandwidth']);
            }
            $html .= "\n";
        }
        $html .= '<tr>'.
            '<td bgcolor="#ECECEC" class="l">&nbsp;'.$message[102].
            '<td bgcolor="#ECECEC">'.$this->num_format($totals['unique_total']).
            '<td bgcolor="#ECECEC">'.$this->num_format($totals['visits_total']).
            '<td bgcolor="#ECECEC">'.$this->num_format($totals['pages_total']).
            '<td bgcolor="#ECECEC">'.$this->num_format($totals['hits_total']).
            '<td bgcolor="#ECECEC">'.$this->byte_format($totals['bandwidth_total']);
        if ($this->NotViewed == 'columns') {
            $html .=
            '<td bgcolor="#ECECEC">'.$this->num_format($totals['not_viewed_pages_total']).
            '<td bgcolor="#ECECEC">'.$this->num_format($totals['not_viewed_hits_total']).
            '<td bgcolor="#ECECEC">'.$this->byte_format($totals['not_viewed_bandwidth_total']);
        }
        $html .= "\n";

        return $html;
    }

    /**
     * Fetch HTML template
     *
     * @return string
     */
    public function fetch_template(): string
    {
        return '<!DOCTYPE HTML PUBLIC -//W3C//DTD HTML 4.01 Transitional//EN>
<html>
<head>
<title>AWStats Totals</title>
<style type="text/css">
body    { font: 11px verdana,arial,helvetica,sans-serif; background-color: white }
td      { font: 11px verdana,arial,helvetica,sans-serif; text-align: center; color: black }
.l      { text-align: left }
.b      { background-color: #CCCCDD; padding: 2px; margin: 0 }
.d      { background-color: white }
.f      { font: 14px verdana,arial,helvetica }
.border { border: #ECECEC 1px solid }
a       { text-decoration: none }
a:hover { text-decoration: underline }
a.h     { color: black }
</style>
</head>
<body>

[content]

<br><br><center><b>AWStats Totals '.self::VERSION.'</b> - <a
href="https://www.telartis.nl/en/awstats">&copy; 2004-2024 Telartis BV</a></center><br><br>

</body>
</html>';
    }

} // end class
