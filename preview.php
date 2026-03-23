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
 * TODO describe file preview
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use local_pg\context\page;
use local_pg\helper;
use local_pg\preview;

require('../../config.php');

require_login();
confirm_sesskey();

$id = optional_param('id', null, PARAM_INT);
if ($id) {
    $context = page::instance($id, IGNORE_MISSING);
}

if (empty($context)) {
    $context = context_system::instance();
    require_capability('local/pg:add', $context);
} else {
    require_capability('local/pg:edit', $context);
}

$params = [
    'shortname'     => required_param('shortname', PARAM_ALPHANUMEXT),
    'header'        => required_param('header', PARAM_TEXT),
    'content'       => required_param('content', PARAM_RAW_TRIMMED),
    'contentformat' => required_param('contentformat', PARAM_INT),
    'css'           => optional_param('css', '', PARAM_RAW),
    'js'            => optional_param('js', '', PARAM_RAW),
    'layout'        => required_param('layout', PARAM_ALPHA),
];

if (!empty($params['css']) && !helper::validate_css($params['css'])) {
    die('Not a valid css code.');
}

if (!empty($params['js']) && !helper::validate_js($params['js'])) {
    die('Not a valid js code.');
}

$url = new moodle_url('/local/pg/preview.php', $params);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$preview = new preview($params);

$PAGE->set_heading($preview->get_title());
$PAGE->set_pagelayout($params['layout']);


echo $OUTPUT->header();

echo $preview->out();

echo $OUTPUT->footer();
