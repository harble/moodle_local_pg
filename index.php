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
 * Base  page  to serve any page.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once('../../config.php');

$id = optional_param('id', optional_param('page', null, PARAM_INT), PARAM_INT);
$shortname = optional_param('shortname', null, PARAM_ALPHANUMEXT);

if (!$id && $shortname) {
    $id = $DB->get_field('local_pg_pages', 'id', ['shortname' => $shortname]);
}

$serve = local_pg\serve::make($id, true, $shortname);

if (!$serve->page_exists()) {
    $shortname = $serve->get_page_shortname();
    if (in_array($shortname, ['faq', 'support'])) {
        redirect(new moodle_url("/local/pg/$shortname.php"));
    }

    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('pagenotfound', 'local_pg'));
    $params = [];
    if ($id) {
        $params['id'] = $id;
    }

    if ($shortname) {
        $params['shortname'] = $shortname;
    }

    $PAGE->set_url(new moodle_url('/local/pg/index.php', $params));
    $PAGE->add_body_class('page-404');

    http_response_code(404);
    @header("HTTP/1.0 404 Not Found");

    echo $OUTPUT->header();
    echo $OUTPUT->container_start();
    echo $OUTPUT->render_from_template('local_pg/404', []);
    echo $OUTPUT->container_end();
    echo $OUTPUT->footer();
    die;
}

$url = new moodle_url('/local/pg/index.php/' . $serve->get_page_shortname(), []);
$PAGE->set_url($url);

$serve->serve();
