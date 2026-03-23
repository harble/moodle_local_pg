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

use html_writer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Edit page content form.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_edit extends \moodleform {

    /**
     * Definition.
     * @return void
     */
    protected function definition() {
        global $PAGE;
        $mform = $this->_form;

        // Page shortname.
        $mform->addElement('text', 'shortname', get_string('shortname', 'local_pg'));
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', get_string('required'), 'required', null, 'client');
        $mform->addRule('shortname', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Page title and header.
        $mform->addElement('text', 'header', get_string('title', 'local_pg'));
        $mform->setType('header', PARAM_TEXT);
        $mform->addRule('header', get_string('required'), 'required', null, 'client');

        $strman = get_string_manager();
        $langs = ['' => get_string('default')] + $strman->get_list_of_translations();
        $mform->addElement('select', 'lang', get_string('language'), $langs);
        if (empty($this->_customdata['id'])) {
            // Don't add multilang content unless saving the first page.
            $mform->hardFreeze('lang');
        }

        // Visibility.
        $voptions = \local_pg\helper::get_visibility_options();
        $mform->addElement('select', 'visible', get_string('visibility', 'local_pg'), $voptions);
        $mform->setDefault('visible', \local_pg\helper::PUBLIC);
        $mform->disabledIf('visible', 'lang', 'noteq', '');

        // Parent page.
        $poptions = \local_pg\helper::get_pages_options();
        $mform->addElement('select', 'parent', get_string('parent', 'local_pg'), $poptions);
        $mform->setDefault('parent', 0);
        $mform->disabledIf('parent', 'lang', 'noteq', '');

        $mform->addElement('checkbox', 'pnav', '', get_string('pnav', 'local_pg'));
        $mform->disabledIf('pnav', 'lang', 'noteq', '');

        $mform->addElement('checkbox', 'snav', '', get_string('snav', 'local_pg'));
        $mform->disabledIf('snav', 'lang', 'noteq', '');

        // Page layout.
        $loptions = \local_pg\helper::get_layout_options();
        $mform->addElement('select', 'layout', get_string('layout', 'local_pg'), $loptions);
        $mform->setDefault('layout', 'base');
        $mform->disabledIf('layout', 'lang', 'noteq', '');

        // Html Editor.
        $editoroptions = \local_pg\helper::get_editor_options();

        if (!empty($this->_customdata['id'])) {
            $context = \local_pg\context\page::instance($this->_customdata['id']);

            $editoroptions['context'] = $context;
        }

        $mform->addElement('editor', 'content_editor', get_string('content', 'local_pg'), [], $editoroptions);

        // Css editor.
        $mform->addElement('textarea', 'css', get_string('css', 'local_pg'), ['height' => '300px']);
        $mform->setType('css', PARAM_RAW);
        $mform->setForceLtr('css');
        $mform->disabledIf('css', 'lang', 'noteq', '');

        // Css debugging.
        $csswarningcontainer = html_writer::div('', 'alert alert-danger', ['id' => 'css-text-error', 'style' => 'display:none;']);
        $mform->addElement('html', $csswarningcontainer);

        // Js editor.
        $mform->addElement('textarea', 'js', get_string('js', 'local_pg'));
        $mform->setType('js', PARAM_RAW);
        $mform->setForceLtr('js');
        $mform->disabledIf('js', 'lang', 'noteq', '');

        // Js debugging.
        $jswarningcontainer = html_writer::div('', 'alert alert-danger', ['id' => 'js-text-error', 'style' => 'display:none;']);
        $mform->addElement('html', $jswarningcontainer);

        // Page id.
        if (!empty($this->_customdata['id'])) {
            $mform->addElement('hidden', 'id');
            $mform->setType('id', PARAM_INT);
            $mform->setDefault('id', $this->_customdata['id']);
        }

        $PAGE->requires->js_call_amd('local_pg/preview', 'init');

        $mform->addElement('button', 'preview', get_string('preview'));
        $this->add_action_buttons();
    }

    /**
     * Form validation.
     * @param  array $data
     * @param  array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        if (!empty($data['css']) && !\local_pg\helper::validate_css($data['css'])) {
            $errors['css'] = get_string('invalidcss', 'local_pg');
        }

        if (!empty($data['js']) && !\local_pg\helper::validate_js($data['js'])) {
            $errors['js'] = get_string('invalidjs', 'local_pg');
        }

        if ($DB->record_exists_select(
            'local_pg_pages',
            'shortname = :shortname AND id != :id',
            ['shortname' => $data['shortname'], 'id' => $data['id'] ?? 0]
        )) {
            $errors['shortname'] = get_string('shortnameexists', 'local_pg');
        }

        return $errors;
    }
}
