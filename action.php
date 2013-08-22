<?php
/**
 * Plugin flattable: Renders tables in DokuWiki format by using a flat
 * table syntax instead of the wiki syntax (very helpful for big tables
 * with a lot of text)
 *
 * This is the Action Component of this plugin.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Alexander Sulfrian <alexander@sulfrian.net>
 *
 * Thanks to Mykola Ostrovskyy's plugin "tablewidth", that was the base
 * for this action component.
 */

if (!defined('DOKU_INC')) die();

require_once DOKU_INC . 'lib/plugins/action.php';

class action_plugin_flattable extends DokuWiki_Action_Plugin {

    public function register(Doku_Event_Handler &$controller) {
        $controller->register_hook('RENDERER_CONTENT_POSTPROCESS',
                                   'AFTER',
                                   $this,
                                   'handle_renderer_content_postprocess');
    }

    public function handle_renderer_content_postprocess(Doku_Event &$event, $param) {
        if ($event->data[0] == 'xhtml') {
            $event->data[1] = preg_replace(
                '/<!-- table-width ([^\n ]+) -->\n([^\n]*?<table\b)/',
                '$2 $1',
                $event->data[1]);
        }
    }
}


?>
