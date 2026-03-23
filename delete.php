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
 * TODO describe file delete
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_login();

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);

$page = $DB->get_record('local_pg_pages', ['id' => $id]);
$context = local_pg\context\page::instance($id);

require_capability('local/pg:delete', $context);

if ($confirm && confirm_sesskey()) {
    $DB->delete_records('local_pg_pages', ['id' => $id]);
    $DB->delete_records('local_pg_langs', ['pageid' => $id]);

    $children = $DB->get_records('local_pg_pages', ['parent' => $id]);
    foreach ($children as $child) {
        $child->parent = $page->parent;
        $child->timemodified = time();
        $child->usermodified = $USER->id;
        $DB->update_record('local_pg_pages', $child);
    }

    $context->delete();

    $cache = cache::make('local_pg', 'pages');
    $cache->delete($page->shortname);

    redirect(new moodle_url('/'), get_string('pagedeleted', 'local_pg'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if (empty($page)) {
    redirect(new moodle_url('/'), get_string('pagenotfound', 'local_pg'), null, \core\output\notification::NOTIFY_ERROR);
}

$url = new moodle_url('/local/pg/delete.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);

$PAGE->set_heading(get_string('delete') . ': ' . $page->header);

echo $OUTPUT->header();

echo $OUTPUT->confirm(get_string('confirmpagedelete', 'local_pg', $page->header),
                        new moodle_url('/local/pg/delete.php', ['id' => $id, 'confirm' => true, 'sesskey' => sesskey()]),
                        new moodle_url('/local/pg/index.php/' . $page->shortname));

echo $OUTPUT->footer();
