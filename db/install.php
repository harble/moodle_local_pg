<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     local_pg
 * @category    upgrade
 * @copyright   2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom code to be run on installing the plugin we use it to migrate
 * from the old plugin name local_page that is already installed in some sites
 * to the new one local_pg.
 * @return true
 */
function xmldb_local_pg_install() {
    global $DB;
    $dbman  = $DB->get_manager();
    $tables = [
        'local_page_pages' => 'local_pg_pages',
        'local_page_faq'   => 'local_pg_faq',
        'local_page_langs' => 'local_pg_langs',
    ];

    $fs          = get_file_storage();
    $tobedeleted = [];

    // Migrate from the old plugin name to the new one.
    foreach ($tables as $old => $new) {
        if ($dbman->table_exists($old) && $dbman->table_exists($new)) {
            // Check if the two tables having the same structures.
            $oldfields = array_keys($DB->get_columns($old));
            $newfields = array_keys($DB->get_columns($new));

            if (count($oldfields) !== count($newfields)) {
                continue;
            }

            foreach ($oldfields as $oldfield) {
                if (!in_array($oldfield, $newfields)) {
                    continue 2;
                }
            }

            $records = $DB->get_records($old);

            if (($old == 'local_page_langs') || !class_exists('local_page\context\page')) {
                $DB->insert_records($new, $records);
                $tobedeleted[] = $old;
                continue;
            }

            $filerecordfields = array_keys($DB->get_columns('files'));

            foreach ($records as $oldrecord) {
                $shortname = ($old == 'local_page_pages') ? $oldrecord->shortname : 'faq';

                if (empty($shortname)) {
                    continue;
                }

                $pageid = ($old == 'local_page_pages')
                ? $oldrecord->id
                : $DB->get_field('local_page_pages', 'id', ['shortname' => 'faq']);

                if (empty($pageid)) {
                    continue;
                }

                $context = local_page\context\page::instance($pageid, IGNORE_MISSING);

                if (!$context) {
                    continue;
                }

                if ($old == 'local_page_pages') {
                    $newpageid = $newrecordid = $DB->insert_record($new, $oldrecord);
                } else {
                    $newpageid   = $DB->get_field('local_pg_pages', 'id', ['shortname' => $shortname]);
                    $newrecordid = $DB->insert_record($new, $oldrecord);
                }

                $newcontext = local_pg\context\page::instance($newpageid);

                // Old areas only.
                $areas = ['pagecontent', 'answers', 'questions'];

                foreach ($areas as $area) {
                    if ($fs->is_area_empty($context->id, 'local_page', $area)) {
                        continue;
                    }
                    $files = $fs->get_area_files($context->id, 'local_page', $area);

                    foreach ($files as $oldfile) {
                        if ($oldfile->is_directory()) {
                            continue;
                        }
                        $newfile            = new stdClass();
                        $newfile->component = 'local_pg';
                        $newfile->contextid = $newcontext->id;
                        $newfile->itemid    = $newrecordid;

                        foreach ($filerecordfields as $field) {
                            if (in_array($field, ['id', 'contextid', 'component', 'itemid'])) {
                                continue;
                            }
                            $method = "get_{$field}";

                            if (method_exists($oldfile, $method)) {
                                $newfile->{$field} = $oldfile->{$method}();
                            }
                        }
                        $fs->create_file_from_storedfile($newfile, $oldfile);
                    }
                }
                $context->delete();
            }
            $tobedeleted[] = $old;
        }

        foreach ($tobedeleted as $oldtable) {
            $DB->delete_records($oldtable);
        }
    }

    return true;
}
