<?php

declare(ticks = 1);

/**
 * Handle tail -f kill command
 */
function signal_handler($signal)
{

    echo "\n";

}

/**
 * Console helper, currently just used to get the display size
 */
class Console
{

    public static function getSize()
    {

        return ['cols' => exec('tput cols'), 'rows' => exec('tput lines')];

    }

}

/**
 * Outputs color codes to the terminal
 */
class Color
{

    static protected $validColors = array(
        // Foreground colors
        'black' => '0;30',
        'red' => '0;31',
        'green' => '0;32',
        'yellow' => '0;33',
        'blue' => '0;34',
        'purple' => '0;35',
        'cyan' => '0;36',
        'light_gray' => '0;37',
        'dark_gray' => '0;90',
        'light_red' => '0;91',
        'light_green' => '0;92',
        'light_yellow' => '0;93',
        'light_blue' => '0;94',
        'light_purple' => '0;95',
        'light_cyan' => '0;96',
        'white' => '0;97',
        // Bold colors
        'bold_black' => '1;30',
        'bold_red' => '1;31',
        'bold_green' => '1;32',
        'bold_yellow' => '1;33',
        'bold_blue' => '1;34',
        'bold_purple' => '1;35',
        'bold_cyan' => '1;36',
        'bold_light_gray' => '1;37',
        'bold_dark_gray' => '1;90',
        'bold_light_red' => '1;91',
        'bold_light_green' => '1;92',
        'bold_light_yellow' => '1;93',
        'bold_light_blue' => '1;94',
        'bold_light_purple' => '1;95',
        'bold_light_cyan' => '1;96',
        'bold_white' => '1;97',
        // Underline colors
        'underline_black' => '4;30',
        'underline_red' => '4;31',
        'underline_green' => '4;32',
        'underline_yellow' => '4;33',
        'underline_blue' => '4;34',
        'underline_purple' => '4;35',
        'underline_cyan' => '4;36',
        'underline_light_gray' => '4;37',
        'underline_dark_gray' => '4;90',
        'underline_light_red' => '4;91',
        'underline_light_green' => '4;92',
        'underline_light_yellow' => '4;93',
        'underline_light_blue' => '4;94',
        'underline_light_purple' => '4;95',
        'underline_light_cyan' => '4;96',
        'underline_white' => '4;97',
        // Background colors
        'on_black' => '40',
        'on_red' => '41',
        'on_green' => '42',
        'on_yellow' => '43',
        'on_blue' => '44',
        'on_purple' => '45',
        'on_cyan' => '46',
        'on_light_gray' => '47',
        'on_dark_gray' => '100',
        'on_white' => '107',
    );

    /**
     * Return the text with one or more color code prefixes
     */
    public static function colorize($text, $colors)
    {

        $colors = explode(' ', $colors);
        $modBold = false;
        $modUnderline = false;
        $backgroundColors = [];
        $foregroundColors = [];
        // Look for bold, underline and hi modifiers
        foreach($colors as $color) {
            if ($color == 'bold') {
                $modBold = true;
            }
            if ($color == 'underline') {
                $modUnderline = true;
            }
            if (substr($color, 0, 3) == 'on_') {
                if (isset(static::$validColors[$color])) {
                    $backgroundColors[] = $color;
                }
            } else {
                if (isset(static::$validColors[$color])) {
                    $foregroundColors[] = $color;
                }
            }
        }

        $colorCode = '';
        // Foreground colors next
        foreach($foregroundColors as $color) {
            $color = ($modBold ? 'bold_' : ($modUnderline ? 'underline_' : '')) . $color;
            $colorCode .= "\033[" . static::$validColors[$color] . "m";
        }
        // Background colors first
        foreach($backgroundColors as $color) {
            $colorCode .= "\033[" . static::$validColors[$color] . "m";
        }

        return $colorCode . $text . "\033[0m";

    }

}

class Extract
{

    protected $consoleSize = 0;
    protected $lastInput = '';
    protected $lastError = null;
    protected $previousError = null;
    protected $similarCount = 0;
    protected $stats = [];

    public function __construct()
    {

        $this->consoleSize = Console::getSize();

    }

    public function calcStats()
    {

        if ((isset($this->lastError['filename'])) && ($this->lastError['filename'] != '{main}')) {
            if (!isset($this->stats[$this->lastError['filename']])) {
                $this->stats[$this->lastError['filename']] = [
                    'errors' => 0,
                    'lines' => [],
                    'lastSeen' => null,
                ];
            }
            $this->stats[$this->lastError['filename']]['errors']++;
            $this->stats[$this->lastError['filename']]['lines'][$this->lastError['line']] = (int) $this->lastError['line'];
            if ($this->lastError['date'] instanceof DateTime) {
                $this->stats[$this->lastError['filename']]['lastSeen'] = (
                    $this->stats[$this->lastError['filename']]['lastSeen'] ? ($this->lastError['date'] > $this->stats[$this->lastError['filename']]['lastSeen'] ? $this->lastError['date'] : $this->stats[$this->lastError['filename']]['lastSeen']) : $this->lastError['date']
                );
            }
        }

    }

    public function displayStats()
    {

        echo Color::colorize('STATISTICS', 'dark_gray bold') . "\n";

        foreach($this->stats as $filename => $stat) {
            echo Color::colorize('File: ', 'white bold') . Color::colorize($filename, 'white') . "\n";
            echo Color::colorize(' - ', 'green') . Color::colorize('Errors Found: ', 'white bold') . Color::colorize($stat['errors'], 'white') . "\n";
            echo Color::colorize(' - ', 'green') . Color::colorize('On Lines: ', 'white bold') . Color::colorize(implode(', ', $stat['lines']), 'white') . "\n";
            if ($stat['lastSeen'] instanceof DateTime) {
                echo Color::colorize(' - ', 'light_green') . Color::colorize('Last Seen: ', 'white bold') . Color::colorize($stat['lastSeen']->format('Y-m-d H:i:s'), 'white') . "\n";
            }
        }

        echo "\n";

    }

    public function displayLastError()
    {

        if (is_array($this->previousError)) {
            if (($this->previousError['errorType'] == $this->lastError['errorType']) &&
                ($this->previousError['message'] == $this->lastError['message']) &&
                ($this->previousError['filename'] == $this->lastError['filename']) &&
                ($this->previousError['line'] == $this->lastError['line'])) {
                    $this->similarCount++;
                    return;
            }
        }

        if ($this->similarCount) {
            echo Color::colorize('... and ' . $this->similarCount . ' similar errors', 'green') . "\n\n";
        }

        echo Color::colorize(str_repeat('-', $this->consoleSize['cols']), 'white') . "\n\n";

        $this->similarCount = 0;

        echo Color::colorize($this->lastError['errorType'], 'light_red') . "\n";
        echo Color::colorize($this->lastError['message'], 'yellow') . "\n";
        echo Color::colorize('File: ', 'white bold') . Color::colorize($this->lastError['filename'], 'white') . "\n";
        echo Color::colorize('Line: ', 'white bold') . Color::colorize($this->lastError['line'], 'white') . "\n";
        if ($this->lastError['date'] instanceof DateTime) {
            echo Color::colorize('Date: ', 'white bold') . Color::colorize($this->lastError['date']->format('Y-m-d H:i:s'), 'white') . "\n";
        }

        $stackTrace = [];
        if (isset($this->lastError['stackTrace'])) {
            echo "\n" . Color::colorize('STACK TRACE', 'cyan bold') . "\n";
            //$this->lastError['stackTrace'] = array_reverse($this->lastError['stackTrace']);
            foreach($this->lastError['stackTrace'] as $step => $details) {
                preg_match("/([^(]*)\((.*)\)/", $details['call'], $matches);
                $method = $matches[1];
                $parameters = [];
                if ((isset($matches[2])) && ($matches[2])) {
                    $parameters = preg_split("/[ ]*,[ ]*/", $matches[2]);
                }
                echo Color::colorize('File: ', 'light_cyan bold');
                echo Color::colorize($details['filename'], 'light_cyan') . "\n";
                echo Color::colorize('Line: ', 'light_cyan bold');
                echo Color::colorize(sprintf("%s", $details['line']), 'light_cyan') . "\n";
                echo Color::colorize('Method: ', 'light_cyan bold');
                echo Color::colorize(sprintf("\n  %s(" . (!count($parameters) ? ')' : ''), $method), 'light_cyan') . "\n";
                if (count($parameters)) {
                    echo Color::colorize('      ' . implode(", \n      ", $parameters), 'light_cyan');
                    echo Color::colorize("\n  )\n", 'light_cyan');
                }
                echo "\n";
            }
        } else {
            echo "\n";
        }

        $this->previousError = $this->lastError;

    }

    public function parseRow($inp)
    {

        if ($this->lastInput) {
            $inp = $this->lastInput . "\n" . $inp;
        }

        // New error declaration
        $matched = preg_match("/(\[([A-Za-z0-9: -]+)\] )?(PHP Notice:|PHP Fatal error:|PHP Warning:|PHP Parse error:|PHP Catchable fatal error:)?(.*) in (\/.*?\.php)( ?\(([0-9]+)\)|:([0-9]+)| on line ([0-9]+))/s", $inp, $matches);
        if ($matched) {
            if ($this->lastInput) {
                $this->lastInput = '';
            }
            if ($this->lastError) {
                $this->displayLastError();
            }
            $this->lastError = [
                'date' => ($matches[2] ? new DateTime($matches[2]) : null),
                'errorType' => ($matches[3] ? $matches[3] : 'Unspecified Error'),
                'message' => trim($matches[4]),
                'filename' => $matches[5],
                'line' => array_pop($matches),
            ];
            $this->calcStats();
            return;
        }

        // Stack trace
        $matched = preg_match("/(#[0-9]+) ({main}|(\/.*?\.php)\(([0-9]+)\): (.*))/", $inp, $matches);
        if ($matched) {
            if ($this->lastInput) {
                $this->lastInput = '';
            }
            if (!$this->lastError) {
                return; // ignore
            }
            if (isset($matches[5])) {
                $this->lastError['stackTrace'][] = [
                    'step' => $matches[1],
                    'filename' => (isset($matches[3]) ? $matches[3] : $matches[2]),
                    'line' => (isset($matches[4]) ? $matches[4] : null),
                    'call' => (isset($matches[5]) ? $matches[5] : null),
                ];
            }
            return;
        }

        // Stack trace #2
        $matched = preg_match("/PHP   ([0-9]+). (.*) (\/.*?\.php):([0-9]+)/", $inp, $matches);
        if ($matched) {
            if ($this->lastInput) {
                $this->lastInput = '';
            }
            if (!$this->lastError) {
                return; // ignore
            }
            if (isset($matches[3])) {
                $this->lastError['stackTrace'][] = [
                    'step' => $matches[1],
                    'filename' => (isset($matches[3]) ? $matches[3] : null),
                    'line' => (isset($matches[4]) ? $matches[4] : null),
                    'call' => (isset($matches[2]) ? $matches[2] : null),
                ];
            }
            return;
        }

        $this->lastInput = $inp;

    }

    public function getLastError()
    {

        return $this->lastError;

    }

    public function getStats()
    {

        return $this->stats;

    }

}

date_default_timezone_set('Europe/London');

pcntl_signal(SIGTERM, "signal_handler");
pcntl_signal(SIGINT,  "signal_handler");
pcntl_signal(SIGHUP,  "signal_handler");

$extract = new Extract();

while ($inp = trim(fgets(STDIN))) {
    $error = $extract->parseRow($inp);
}

$extract->displayLastError();
$extract->displayStats();
