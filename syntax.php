<?php
/**
 * Plugin flattable: Renders tables in DokuWiki format by using a flat
 * table syntax instead of the wiki syntax (very helpful for big tables
 * with a lot of text)
 *
 * This is the Syntax Component of this plugin.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Alexander Sulfrian <alexander@sulfrian.net>
 *
 * Thanks to Olaf Trieschmann's plugin "itemtables", that was the base
 * for this plugin.
 * Thanks to Ashish Kulkarni for the FlatTableMacro for trac wiki, that
 * provieded the flat table syntax.
 */

if(!defined('DOKU_INC')) die();

require_once DOKU_INC . 'lib/plugins/syntax.php';
require_once DOKU_INC . 'inc/parser/parser.php';
require_once DOKU_INC . 'inc/parser/xhtml.php';

class syntax_plugin_flattable extends DokuWiki_Syntax_Plugin {

    private $quoted_string_regex;
    private $value_regex;
    private $key_regex;

    private $options = array();

    function __construct() {
        $this->quoted_string_regex = '"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"';
        $this->value_regex = $this->quoted_string_regex . '|[^ ]*';
        $this->key_regex = '\\b[^= ]*';
    }

    function getType() {
        return 'substition';
    }

    function getPType() {
        return 'block';
    }

    function getSort() {
        return 32;
    }

    function connectTo($mode) {
        $this->Lexer->addEntryPattern(
            // match key=value=default (=default is optional)
            '<flattable(?:\\s+' . $this->key_regex . '=(?:' . $this->value_regex . ')'.
                              '(?:=(?:' . $this->value_regex . '))?)*\\s*>(?=.*</flattable>)',
            $mode,
            'plugin_flattable');
    }

    function postConnect() {
        $this->Lexer->addExitPattern(
            '</flattable>',
            'plugin_flattable');
    }

    function handle($match, $state, $pos, &$handler) {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                return array($state, $match);
                break;
            case DOKU_LEXER_MATCHED:
                return array($state, $match);
                break;
            case DOKU_LEXER_UNMATCHED:
                return array($state, $match);
                break;
            case DOKU_LEXER_EXIT:
                return array($state, '');
                break;
        }
        return array();
    }

    private function unquote($value) {
        if (preg_match('/^"(.*)"$/', $value, $match)) {
           return stripslashes($match[1]);
        }

        return $value;
    }

    private function render_row($key, $values) {
        if ($key === false) {
            return '';
        }

        // output the row
        $row = "| $key ";

        foreach ($this->options['__col'] as $col) {
            $row .= '| ';
            if (array_key_exists($col, $values)) {
                $row .= $values[$col] . ' ';
            }
            else if (array_key_exists($col, $this->options['__defaults'])) {
                $row .= $this->options['__defaults'][$col] . ' ';
            }
        }
        $row .= "|\n";

        return $row;
    }

    private function render_tables($match, $mode, $data) {
        $table = '';

        // draw heading if requested
        if (trim($this->options['header']) != '') {
            $table .= '^'. $this->options['header'] . str_repeat('^', count($this->options['__col'])+1) . "\n";
        }

        // draw the descriptors of each field
        $table .= '^ ';
        if ($this->options['key'] !== false) {
            $table .= $this->options['key'];
        }
        $table .= ' ^ ';

        foreach ($this->options['__col'] as $col) {
            $row[$col] = '';

            if (array_key_exists($col, $this->options['__head'])) {
                $table .= $this->options['__head'][$col];
            }
            else {
                $table .= $col;
            }

            $table .= ' ^ ';
        }
        $table .= "\n";


        $lines = explode("\n", $match);
        $row_ready = false;
        $row_key = false;
        $row = array();
        $last_col = false;
        $spaces = false;
        foreach($lines as $line) {
            if ($this->options['key'] === false) {
                // legacy item table syntax

                $line = trim($line);
                if ($last_col !== false) {
                    $cell_off = strpos($line, $this->options["cell_off"]);
                    if ($cell_off !== false) {
                        $row[$last_col] .= ' ' . substr($line, 0, $cell_off);
                        $last_col = false;
                    }
                    else {
                        $row[$last_col] .= ' ' . $line;
                    }
                }
                else {
                    if (strpos($line, $this->options['thead']) === 0) {
                        // key
                        $row_ready = substr($line, strlen($this->options['thead']));
                    }
                    else {
                        if (strpos($line, $this->options['fdelim']) === false) {
                            continue;
                        }

                        list($key, $value) = explode($this->options['fdelim'], $line, 2);

                        $cell_on = strpos($value, $this->options['cell_on']);
                        if ($cell_on !== false) {
                            $value = substr($value, $cell_on + strlen($this->options['cell_on']));

                            $cell_off = strpos($value, $this->options['cell_off']);
                            if ($cell_off !== false) {
                                $last_col = false;
                                $value = substr($value, 0, $cell_off);
                            }
                            else {
                                $last_col = $key;
                            }
                        }
                        else {
                            $last_col = false;
                        }
                        $row[$key] = $value;
                    }
                }
            }
            else {
                // flat table syntax
                if ($line != '' && $line[0] != '#') {
                    // ignore empty lines and comments
                    if (preg_match('/^([^\s]+.*)\:\s*$/', $line, $match)) {
                        // key
                        $row_ready = $match[1];
                    }
                    else if (preg_match('/^(\s*)@([^@\s]+?)\:\s*(.+)?$/', $line, $match)) {
                        // row
                        $spaces = $match[1];
                        $last_col = $match[2];
                        $row[$last_col] = $match[3];
                    }
                    else if ($spaces !== false && ($spaces === '' || strpos($line, $spaces) === 0)) {
                        // continuation of last cell
                        $row[$last_col] .= ' ' . substr($line, strlen($spaces));
                    }
                }
            }

            if ($row_ready !== false) {
                $table .= $this->render_row($row_key, $row);

                // prepare for next row
                $row_key = $row_ready;
                $row_ready = false;
                $row = array();
            }
        }

        // output last row
        $table .= $this->render_row($row_key, $row);

        if ($this->options['norender'] != '') {
            // display dokuwiki source
            $table = preg_replace('/^/m', '  ', $table);
        }
        else if (!plugin_isdisabled('sortablejs')) {
            // automatic enable sortablejs if available
            $sort = '';
            if ($this->options['sort'] != false) {
                $sort = ' ' . $this->options['sort'];
            }

            $table = '<sortable' . $sort . ">\n" . $table . '</sortable>';
        }

        $output = p_render($mode, p_get_instructions($table), $data);
        if ($this->options['twidth'] !== false) {
            $output = "<!-- table-width width='" . $this->options['twidth'] . "' -->\n" . $output;
        }

        return $output;
    }

    function render($mode, &$renderer, $data) {
        if ($mode == 'xhtml') {
            list($state, $match) = $data;
            switch ($state) {
                case DOKU_LEXER_ENTER:
                    // default settings
                    $this->options['header'] = '';
                    $this->options['__col'] = array();
                    $this->options['__head'] = array();
                    $this->options['__default'] = array();
                    $this->options['cell_on'] = '<tablecell>';
                    $this->options['cell_off'] = '</tablecell>';
                    $this->options['fdelim'] = '=';
                    $this->options['thead'] = '_';
                    $this->options['twidth'] = false;
                    $this->options['norender'] = false;
                    $this->options['sort'] = false;
                    $this->options['key'] = false;

                    // parse attributes
                    preg_match_all('/(' . $this->key_regex . ')' .           // key
                                     '=(' . $this->value_regex . ')' .       // value
                                     '(?:=(' . $this->value_regex . '))?/',  // default (optional)
                                   $match, $matches, PREG_SET_ORDER);
                    foreach ($matches as $m) {
                        $key = trim($m[1]);
                        $value = $this->unquote($m[2]);

                        if ($key == 'c') {
                            // this is the itemtable legacy syntax
                            $cols = explode(',', $value);
                            $this->options['__col'] = array_merge($this->options['__col'], $cols);
                        }
                        else {
                            if (array_key_exists($key, $this->options) && !is_array($this->options[$key])) {
                                // set value for option
                                $this->options[$key] = $value;
                            }
                            else {
                                // define column and set or change heading
                                if (!in_array($key, $this->options['__col'])) {
                                    $this->options['__col'][] = $key;
                                }

                                $this->options['__head'][$key] = $value;

                                if (isset($m[3])) {
                                    $this->options['__defaults'][$key] = $this->unquote($m[3]);
                                }
                            }
                        }
                    }
                    break;

                case DOKU_LEXER_UNMATCHED:
                    $renderer->doc .= $this->render_tables($match, $mode, $data);
                    break;
            }

            return true;
        }

        return false;
    }
}
