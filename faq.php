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

use local_pg\helper;

/**
 * FAQ page.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once('../../config.php');

$faq     = new \local_pg\output\faq();
$context = $faq->get_context();
$id      = optional_param('id', 0, PARAM_INT);
$action  = optional_param('action', 'view', PARAM_ALPHA);

$url = new moodle_url('/local/pg/faq.php', ['id' => $id, 'action' => $action]);

$PAGE->set_url($url);
$PAGE->set_context($context);

if (!$PAGE->user_is_editing() || !has_capability('local/pg:edit', $context)) {
    $serve = local_pg\serve::make($faq->get_faq_page_record()->id);
    $serve->set_page_url($url);

    $renderer = $PAGE->get_renderer('local_pg');

    $serve->set_after_content($renderer->render($faq));

    $serve->serve();
}

if ((!empty($id) && $action !== 'view') || $action == 'add') {
    switch ($action) {
        case 'edit':
        case 'add':
            $mform = new local_pg\form\faq_edit($url);

            if ($data = $mform->get_data()) {
                $faq->save_question($data);
                redirect(new moodle_url('/local/pg/faq.php'));
            }

            if (!empty($id)) {
                require_once($CFG->libdir . '/filelib.php');

                $default = $faq->questions[$id];
                $default = file_prepare_standard_editor(
                    $default,
                    'question',
                    $faq->get_question_editor_options(),
                    $context,
                    'local_pg',
                    helper::FAQ_Q_FILEAREA,
                    $id
                );
                $default = file_prepare_standard_editor(
                    $default,
                    'answer',
                    $faq->get_answer_editor_options(),
                    $context,
                    'local_pg',
                    helper::FAQ_A_FILEAREA,
                    $id
                );
                $mform->set_data($default);
            }
            $content = $mform->render();
            break;

        case 'delete':
            if (optional_param('confirm', false, PARAM_BOOL) && confirm_sesskey()) {
                $DB->delete_records('local_pg_faq', ['id' => $id]);
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'local_pg', helper::FAQ_Q_FILEAREA, $id);
                $fs->delete_area_files($context->id, 'local_pg', helper::FAQ_A_FILEAREA, $id);
                redirect(new moodle_url('/local/pg/faq.php'));
            }
            $content = $OUTPUT->confirm(
                get_string('deletequestionconfirm', 'local_pg'),
                new moodle_url($url, ['confirm' => true, 'sesskey' => sesskey()]),
                new moodle_url('/local/pg/faq.php')
            );
            break;

        case 'moveup':
            $faq->move_up($id);
            redirect(new moodle_url('/local/pg/faq.php'));
            break;

        case 'movedown':
            $faq->move_down($id);
            redirect(new moodle_url('/local/pg/faq.php'));
            break;

        default:
            break;
    }
}

if (!isset($content)) {
    $content = '';

    if (has_capability('local/pg:edit', $context)) {
        $editurl = new \moodle_url('/local/pg/edit.php', ['id' => $faq->record->id]);
        $content .= $OUTPUT->single_button(
            $editurl,
            get_string('editpage', 'local_pg'),
            'get',
            ['type' => single_button::BUTTON_WARNING]
        );
        $content .= html_writer::empty_tag('hr');
    }

    $serve = local_pg\serve::make($faq->get_faq_page_record()->id);
    $content .= $serve->out_content_only();

    $content .= $faq->get_faq_table();
    $content .= $OUTPUT->single_button(
        new moodle_url($url, ['action' => 'add']),
        get_string('addquestion', 'local_pg'),
        'get',
        ['type' => single_button::BUTTON_PRIMARY]
    );
}

$PAGE->set_heading($faq->record->header);
$PAGE->set_title($faq->record->header);

echo $OUTPUT->header();

echo $content;

echo $OUTPUT->footer();
