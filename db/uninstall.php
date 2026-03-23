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

/**
 * Code to be executed post uninstall the plugin has been installed is defined here.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Delete all pages contexts before delete the plugin and also
 * remove the custom context level from Moodle core settings.
 * @return bool
 */
function xmldb_local_pg_uninstall() {
    global $DB, $CFG;
    $level = local_pg\context\page::LEVEL;
    $contexts = $DB->get_records('context', ['contextlevel' => $level], '', 'id');
    foreach ($contexts as $record) {
        $context = local_pg\context\page::instance_by_id($record->id, IGNORE_MISSING);
        if ($context) {
            $context->delete();
        }
    }

    // Delete our custom context level from moodle configuration.
    if (empty($CFG->custom_context_classes)) {
        return true;
    }

    if (!is_array($CFG->custom_context_classes)) {
        $levels = @unserialize($CFG->custom_context_classes);

        if (empty($levels) || !is_array($levels)) {
            $levels = [];
        }
    } else {
        $levels = $CFG->custom_context_classes;
    }

    if (empty($levels)) {
        return true;
    }

    foreach ($levels as $lev => $class) {
        // Checking by class is more convenient.
        if ($class === local_pg\context\page::class) {
            unset($levels[$lev]);
        }
    }

    set_config('custom_context_classes', !empty($levels) ? serialize($levels) : null);
    $CFG->custom_context_classes = $levels;
    return true;
}
