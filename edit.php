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
 * TODO describe file edit
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_pg\helper;

require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

$id = optional_param('id', 0, PARAM_INT);
if (!empty($id)) {
    $page = $DB->get_record('local_pg_pages', ['id' => $id]);
    if (!empty($page)) {
        $context = local_pg\context\page::instance($page->id);
        require_capability('local/pg:edit', $context);
    }
}

if (empty($context)) {
    $context = context_system::instance();
    require_capability('local/pg:add', $context);
}

$url = new moodle_url('/local/pg/edit.php', ['id' => $id]);

$PAGE->set_url($url);
$PAGE->set_context($context);

$PAGE->set_heading($page->header ?? $SITE->fullname);

$customdata = [
    'id'      => $id,
    'context' => $context,
];

$options = helper::get_editor_options();
$options['context'] = $context;

if (!empty($page)) {
    $page = file_prepare_standard_editor(
        $page,
        'content',
        $options,
        $context,
        'local_pg',
        helper::CONTENT_FILEAREA,
        $page->id
    );
}

$form = new \local_pg\form\page_edit(null, $customdata);

if (!empty($page)) {
    $form->set_data($page);
}

if ($form->is_cancelled()) {
    if (!empty($id)) {
        redirect(new moodle_url('/local/pg/index.php', ['id' => $id]));
    } else {
        redirect(new moodle_url('/'));
    }
}

if ($data = $form->get_data()) {

    $data->pnav ??= 0;
    $data->snav ??= 0;

    $cache = cache::make('local_pg', 'pages');

    if (!empty($data->id)) {

        $cache->delete($page->shortname);

        $data->timemodified = time();
        $data->usermodified = $USER->id;
        if (empty($data->lang)) {
            $data = file_postupdate_standard_editor(
                $data,
                'content',
                $options,
                $context,
                'local_pg',
                helper::CONTENT_FILEAREA,
                $data->id
            );

            helper::format_page_path($data);
            $DB->update_record('local_pg_pages', $data);
        } else {
            $langrecord = (object) [
                'pageid'       => $data->id,
                'lang'         => $data->lang,
                'content'      => '',
                'header'       => $data->header,
                'timemodified' => $data->timemodified,
            ];

            $oldrecord = $DB->get_record('local_pg_langs', ['pageid' => $data->id, 'lang' => $data->lang], 'id');
            if ($oldrecord) {
                $langrecord->id = $oldrecord->id;
            } else {
                $langrecord->timecreated = time();
                $langrecord->id = $DB->insert_record('local_pg_langs', $langrecord);
            }

            $data = file_postupdate_standard_editor(
                $data,
                'content',
                $options,
                $context,
                'local_pg',
                helper::CUSTOMLANG_FILEAREA,
                $langrecord->id
            );
            $langrecord->content = $data->content;
            $DB->update_record('local_pg_langs', $langrecord);
        }

    } else {
        $record = new stdClass();

        $record->header        = $data->header;
        $record->content       = '';
        $record->contentformat = 0;
        $record->css           = $data->css;
        $record->js            = $data->js;
        $record->visible       = $data->visible;
        $record->layout        = $data->layout;
        $record->shortname     = $data->shortname;
        $record->timemodified  = time();
        $record->timecreated   = time();
        $record->usermodified  = $USER->id;
        $record->pnav          = $data->pnav;
        $record->snav          = $data->snav;
        $record->parent        = $data->parent ?? 0;

        helper::format_page_path($record);
        // Insert with empty contents at first.
        $record->id = $DB->insert_record('local_pg_pages', $record);

        // Update to insert the contents.
        $context = local_pg\context\page::instance($record->id);
        $data = file_postupdate_standard_editor(
            $data,
            'content',
            $options,
            $context,
            'local_pg',
            helper::CONTENT_FILEAREA,
            $record->id
        );
        $data->id = $record->id;

        $DB->update_record('local_pg_pages', $data);
    }

    // Just in case.
    $cache->delete($data->shortname);

    redirect(new moodle_url('/local/pg/index.php/' . $data->shortname));
}

echo $OUTPUT->header();

$form->display();

echo $OUTPUT->footer();
