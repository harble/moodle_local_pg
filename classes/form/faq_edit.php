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

namespace local_pg\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Edit for for questions.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faq_edit extends \moodleform {
    /**
     * Definition
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $faq = new \local_pg\output\faq();

        $mform->addElement('editor', 'question_editor', get_string('question'), null, $faq->get_question_editor_options());
        $mform->addElement('editor', 'answer_editor', get_string('answer'), null, $faq->get_answer_editor_options());

        $mform->addElement('checkbox', 'visible', get_string('visible'));
        $mform->setDefault('visible', true);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHA);

        $id = $this->optional_param('id', null, PARAM_INT);
        if ($id) {
            $mform->addElement('hidden', 'id', $id);
            $mform->setType('id', PARAM_INT);

            $mform->setDefault('action', 'edit');
        } else {
            $mform->setDefault('action', 'add');
        }

        $this->add_action_buttons();
    }
}
