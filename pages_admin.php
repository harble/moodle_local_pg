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
 * TODO describe file pages_admin
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();
require_capability('local/pg:viewhidden', context_system::instance());

$url = new moodle_url('/local/pg/pages_admin.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$PAGE->set_heading(get_string('pages', 'local_pg'));
$PAGE->set_pagelayout('admin');

$report = new local_pg\table\pages();
$report->define_baseurl($url);

echo $OUTPUT->header();

if (has_capability('local/pg:add', \context_system::instance())) {
    $editurl = new moodle_url('/local/pg/edit.php');
    echo $OUTPUT->single_button($editurl, get_string('addpage', 'local_pg'), 'get', ['type' => single_button::BUTTON_INFO]);
    echo html_writer::empty_tag('hr');
}

$report->out(0, false);

echo $OUTPUT->footer();
