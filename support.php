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
 * Contact support page
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_pg\context\page;
use local_pg\form\support;
use local_pg\helper;
use local_pg\serve;

// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once('../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

$user = (isloggedin() && !isguestuser()) ? $USER : null;

// If not allowed to view this page, redirect to the homepage. This would be where the site has
// disabled support, or limited it to authenticated users and the current user is a guest or not logged in.
if (
    !isset($CFG->supportavailability)
 || $CFG->supportavailability == CONTACT_SUPPORT_DISABLED
 || ($CFG->supportavailability == CONTACT_SUPPORT_AUTHENTICATED && is_null($user))
) {
    redirect($CFG->wwwroot);
}

$supportpagerecord = helper::get_support_page_record();
$context           = page::instance($supportpagerecord->id);
$url               = new moodle_url('/local/pg/support.php');
$serve             = serve::make($supportpagerecord->id);

$PAGE->set_context($context);
$PAGE->set_url($url);

$PAGE->set_title($serve->get_title());
$PAGE->set_heading($serve->get_title());
$PAGE->set_pagelayout($serve->layout);

$renderer = $PAGE->get_renderer('user');

$form = new support(null, $user);

if ($form->is_cancelled()) {
    redirect($CFG->wwwroot);
} else if ($form->is_submitted() && $form->is_validated() && confirm_sesskey()) {
    $data = $form->get_data();

    $from                  = $user ?? core_user::get_noreply_user();
    $subject               = get_string('supportemailsubject', 'admin', format_string($SITE->fullname));
    $data->notloggedinuser = (!$user);
    $message               = $renderer->render_from_template('user/contact_site_support_email_body', $data);

    $sendmail = email_to_user(
        user: core_user::get_support_user(),
        from: $from,
        subject: $subject,
        messagetext: $message,
        usetrueaddress: true,
        replyto: $data->email,
        replytoname: $data->name
    );

    if (!$sendmail) {
        $supportemail = $CFG->supportemail;
        $form->set_data($data);
        $templatectx = [
            'supportemail' => $user ? html_writer::link("mailto:{$supportemail}", $supportemail) : false,
            'supportform'  => $form->render(),
        ];

        $output = $renderer->render_from_template('user/contact_site_support_not_available', $templatectx);
    } else {
        $level = \core\output\notification::NOTIFY_SUCCESS;
        redirect($CFG->wwwroot, get_string('supportmessagesent', 'user'), 3, $level);
    }
} else {
    if ($PAGE->user_is_editing() && has_capability('local/pg:edit', $context)) {
        $editurl = new \moodle_url('/local/pg/edit.php', ['id' => $serve->id]);
        $content = $OUTPUT->single_button(
            $editurl,
            get_string('editpage', 'local_pg'),
            'get',
            ['type' => single_button::BUTTON_WARNING]
        );
        $content .= html_writer::empty_tag('hr');
        $serve->set_pre_content($content);
    }

    $serve->set_after_content($form->render());
    $output = $serve->out_content_only();
}

echo $OUTPUT->header();

echo $output;

echo $OUTPUT->footer();
