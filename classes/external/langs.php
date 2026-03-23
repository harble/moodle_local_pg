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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_pg\helper;

/**
 * Class langs
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class langs extends external_api {
    /**
     * Parameters description for ::get_content
     * @return external_function_parameters
     */
    public static function get_content_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id'   => new external_value(PARAM_INT, 'The page id'),
            'lang' => new external_value(PARAM_LANG, 'The language code', VALUE_OPTIONAL, ''),
        ]);
    }
    /**
     * Get the page content and header for a page for specific language
     * @param int $id
     * @param string $lang
     * @return array{content: ?string, header: ?string}
     */
    public static function get_content($id, $lang) {
        global $DB;
        $params = self::validate_parameters(self::get_content_parameters(), compact('id', 'lang'));

        $context = \local_pg\context\page::instance($params['id']);
        self::validate_context($context);

        $default = empty($params['lang']);
        if ($default) {
            $table = 'local_pg_pages';
            unset($params['lang']);
        } else {
            $table = 'local_pg_langs';
            $params['pageid'] = $params['id'];
            unset($params['id']);
        }
        $record = $DB->get_record($table, $params, 'id, header, content, contentformat');
        $return = [
            'header'         => null,
            'content_editor' => [
                'text'   => null,
                'format' => null,
                'itemid' => null,
            ],
        ];

        if (!$record) {
            return $return;
        }
        if (!empty($record->header)) {
            $return['header'] = $record->header;
        }
        if (!empty($record->content)) {
            $islangrecord = ($table === 'local_pg_langs');
            $options = helper::get_editor_options();
            $options['context'] = $context;
            $record = file_prepare_standard_editor(
                $record,
                'content',
                $options,
                $context,
                'local_pg',
                $islangrecord ? helper::CUSTOMLANG_FILEAREA : helper::CONTENT_FILEAREA,
                $record->id
            );
            $return['content_editor'] = $record->content_editor;
        }
        return $return;
    }
    /**
     * External description of returning types of ::get_content
     * @return external_single_structure
     */
    public static function get_content_returns(): external_single_structure {
        return new external_single_structure([
            'header' => new external_value(PARAM_TEXT, 'The page header'),
            'content_editor' => new external_single_structure(
                [
                    'text' => new external_value(PARAM_RAW, 'The page content'),
                    'format' => new external_value(PARAM_INT, 'The editor format'),
                    'itemid' => new external_value(PARAM_INT, 'The files item id'),
                ], 'The content of the loaded page'
            ),
        ]);
    }
}
