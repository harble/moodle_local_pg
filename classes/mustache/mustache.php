<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_pg\mustache;

use core\output\mustache_clean_string_helper;
use core\output\mustache_engine;
use core\output\mustache_string_helper;
use core\output\mustache_quote_helper;
use core\output\mustache_javascript_helper;
use core\output\mustache_pix_helper;
use core\output\mustache_shorten_text_helper;
use core\output\mustache_user_date_helper;
use local_pg\mustache\loader;

/**
 * Class mustache
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mustache {
    /**
     * Mustache engine.
     * @var mustache_engine
     */
    public static $mustache;
    /**
     * Get the mustache engine.
     * exactly the same in the core_renderer.php
     *
     * @return \core\output\mustache_engine
     */
    public static function get_mustache(): mustache_engine {
        global $PAGE, $OUTPUT;

        if (empty(static::$mustache)) {

            $themename = $PAGE->theme->name;
            $themerev = theme_get_revision();

            // Create new localcache directory.
            $cachedir = make_localcache_directory("mustache/$themerev/$themename");

            $loader            = new loader();
            $stringhelper      = new mustache_string_helper();
            $cleanstringhelper = new mustache_clean_string_helper();
            $quotehelper       = new mustache_quote_helper();
            $jshelper          = new mustache_javascript_helper($PAGE);
            $pixhelper         = new mustache_pix_helper($OUTPUT);
            $shortentexthelper = new mustache_shorten_text_helper();
            $userdatehelper    = new mustache_user_date_helper();

            // We only expose the variables that are exposed to JS templates.
            $safeconfig = $PAGE->requires->get_config_for_javascript($PAGE, $OUTPUT);

            $helpers = [
                            'config'      => $safeconfig,
                            'str'         => [$stringhelper, 'str'],
                            'cleanstr'    => [$cleanstringhelper, 'cleanstr'],
                            'quote'       => [$quotehelper, 'quote'],
                            'js'          => [$jshelper, 'help'],
                            'pix'         => [$pixhelper, 'pix'],
                            'shortentext' => [$shortentexthelper, 'shorten'],
                            'userdate'    => [$userdatehelper, 'transform'],
                       ];

            static::$mustache = new mustache_engine([
                'cache'   => $cachedir,
                'escape'  => 's',
                'loader'  => $loader,
                'helpers' => $helpers,
                'pragmas' => [mustache_engine::PRAGMA_BLOCKS],
                // Don't allow the JavaScript helper to be executed from within another
                // helper. If it's allowed it can be used by users to inject malicious
                // JS into the page.
                'disallowednestedhelpers' => ['js'],
                // Disable lambda rendering - content in helpers is already rendered, no need to render it again.
                'disable_lambda_rendering' => true,
            ]);
        }

        return static::$mustache;
    }
}
