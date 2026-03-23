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

namespace local_pg\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Class load_samples
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class load_samples extends external_api {
    /**
     * Parameters description for ::load
     * @return external_function_parameters
     */
    public static function load_parameters() {
        return new external_function_parameters([
            'name' => new external_value(PARAM_ALPHANUMEXT, 'name of the sample'),
        ]);
    }
    /**
     * Load a sample by name
     * @param string $name
     * @return array{html: string, js: string, css: string}
     */
    public static function load($name) {
        global $CFG;
        $name = self::validate_parameters(self::load_parameters(), ['name' => $name])['name'];
        $context = context_system::instance();
        self::validate_context($context);

        require_capability('local/pg:add', $context);

        $file = "{$CFG->dirroor}/local/pg/samples/{$name}.mustache";
        $content = file_get_contents($file);

        $pattern = '/\{\{#(html|js|css)\}\}(.*?)\{\{\/\1\}\}/s';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $result = [
            'html' => '',
            'js'   => '',
            'css'  => '',
        ];

        foreach ($matches as $match) {
            $result[$match[1]] = trim($match[2]);
        }

        return $result;
    }
    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function load_returns() {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'html'),
            'js'   => new external_value(PARAM_RAW, 'js'),
            'css'  => new external_value(PARAM_RAW, 'css'),
        ]);
    }
}
