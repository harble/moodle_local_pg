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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Class support_edit.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class support extends \moodleform {
    /**
     * Define the contact site support form.
     */
    public function definition(): void {
        global $CFG, $PAGE;

        $mform       = $this->_form;
        $user        = $this->_customdata;
        $strrequired = get_string('required');
        $strname     = get_string('name');
        $stremail    = get_string('email');

        // phpcs:disable moodle.Commenting.InlineComment.DocBlock
        /**
         * Name.
         * @var \HTML_QuickForm_text
         */
        $name = $mform->createElement('text', 'name', $strname, ['label' => $strname, 'placeholder' => $strname]);

        /**
         * Email.
         * @var \HTML_QuickForm_text
         */
        $email = $mform->createElement('text', 'email', $stremail, ['label' => $stremail, 'placeholder' => $stremail]);

        /**
         * Merge name and email to one group.
         * @var \HTML_QuickForm_group
         */
        $group = $mform->addGroup([$name, $email], 'name-email', '', null, false);
        // phpcs:enable moodle.Commenting.InlineComment.DocBlock

        $mform->addGroupRule('name-email', [
            'name'  => [[$strrequired, 'required', null, 'client']],
            'email' => [[get_string('missingemail'), 'required', null, 'client']],
        ], 'required', null, 2, 'client');

        $group->updateAttributes('class');
        $mform->setType('name', PARAM_TEXT);
        $mform->setType('email', PARAM_EMAIL);

        // Subject.
        $mform->addElement('text', 'subject', get_string('subject'));
        $mform->addRule('subject', $strrequired, 'required', null, 'client');
        $mform->setType('subject', PARAM_TEXT);

        // Message.
        $mform->addElement('textarea', 'message', get_string('message'));
        $mform->addRule('message', $strrequired, 'required', null, 'client');
        $mform->setType('message', PARAM_TEXT);

        // If the user is logged in set name and email fields to the current user info.
        if (isloggedin() && !isguestuser()) {
            $mform->setDefault('name', fullname($user));
            $mform->setDefault('email', $user->email);
            $mform->hardFreeze('name-email');
        }

        if (!empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey)) {
            $mform->addElement('recaptcha', 'recaptcha_element', get_string('security_question', 'auth'));
            $mform->addHelpButton('recaptcha_element', 'recaptcha', 'auth');
            $mform->closeHeaderBefore('recaptcha_element');
        }

        $js = <<<'JS'
            require(['jquery'], function($) {
                $('.col-form-label.sr-only').removeClass('sr-only');
            });
        JS;
        $PAGE->requires->js_init_code($js, true);
        $this->set_display_vertical();
        $this->add_action_buttons(true, get_string('submit'));
    }

    /**
     * Validate user supplied data on the contact site support form.
     *
     * @param  array $data  array of ("fieldname"=>value) of submitted data
     * @param  array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        }

        if ($this->_form->elementExists('recaptcha_element')) {
            $recaptchaelement = $this->_form->getElement('recaptcha_element');

            if (!empty($this->_form->_submitValues['g-recaptcha-response'])) {
                $response = $this->_form->_submitValues['g-recaptcha-response'];

                if (!$recaptchaelement->verify($response)) {
                    $errors['recaptcha_element'] = get_string('incorrectpleasetryagain', 'auth');
                }
            } else {
                $errors['recaptcha_element'] = get_string('missingrecaptchachallengefield');
            }
        }

        return $errors;
    }
}
