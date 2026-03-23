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

namespace local_pg\output;

use core_table\output\html_table;
use html_writer;
use local_pg\helper;
use moodle_url;

/**
 * Class faq.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faq implements \renderable, \templatable {
    /**
     * Questions records.
     * @var \stdClass[]
     */
    public $questions = [];

    /**
     * FAQ page record.
     * @var \stdClass
     */
    public readonly \stdClass $record;

    /**
     * Prepare FAQ page.
     */
    public function __construct() {
        global $DB;
        $conditions = [];

        $this->questions = $DB->get_records('local_pg_faq', $conditions, 'sortorder ASC, id ASC');
        $this->get_faq_page_record();
        $this->sort_questions();
    }

    /**
     * Count questions.
     * @return int
     */
    public function get_questions_count() {
        return count($this->questions);
    }

    /**
     * Move question up.
     * @param  int  $qid question id to be moved
     * @return void
     */
    public function move_up($qid) {
        $order       = $this->questions[$qid]->sortorder;
        $tobeupdated = [];

        foreach ($this->questions as $q) {
            if ($q->sortorder == $order) {
                $q->sortorder--;
                $tobeupdated[] = $q;
            } else if ($q->sortorder == ($order - 1)) {
                $q->sortorder++;
                $tobeupdated[] = $q;
            }
        }

        $this->update_questions($tobeupdated);
        $this->fix_sort_order();
    }

    /**
     * Move question down.
     * @param  int  $qid question id to be moved
     * @return void
     */
    public function move_down($qid) {
        $order       = $this->questions[$qid]->sortorder;
        $tobeupdated = [];

        foreach ($this->questions as $q) {
            if ($q->sortorder == $order) {
                $q->sortorder++;
                $tobeupdated[] = $q;
            } else if ($q->sortorder == ($order + 1)) {
                $q->sortorder--;
                $tobeupdated[] = $q;
            }
        }
        $this->update_questions($tobeupdated);
        $this->fix_sort_order();
    }

    /**
     * Sort questions by sortorder and fix sortorder in database.
     * @return void
     */
    public function sort_questions() {
        $needfix = false;
        uasort($this->questions, function ($a, $b) use (&$needfix) {
            if ($a->sortorder != $b->sortorder) {
                return $a->sortorder <=> $b->sortorder;
            }
            $needfix = true;

            return $a->timemodified <=> $b->timemodified;
        });

        if ($needfix) {
            $this->fix_sort_order();
        }
    }

    /**
     * Fixing the sort order of questions.
     * @return void
     */
    public function fix_sort_order() {
        $order    = 0;
        $toupdate = [];

        foreach ($this->questions as $q) {
            $order++;

            if ($q->sortorder != $order) {
                $q->sortorder = $order;
                $toupdate[]   = $q;
            }
        }

        $this->update_questions($toupdate);
    }

    /**
     * Update questions records.
     * @param  mixed $questions
     * @return void
     */
    public function update_questions($questions = null) {
        global $DB;
        $oldquestions = fullclone($this->questions);

        if ($questions === null) {
            $questions       = $this->questions;
            $this->questions = [];
        }

        $transaction = $DB->start_delegated_transaction();

        try {
            foreach ($questions as $q) {
                if (!empty($q->id)) {
                    $DB->update_record('local_pg_faq', $q);
                } else {
                    $q->id = $DB->insert_record('local_pg_faq', $q);
                }

                $this->questions[$q->id] = $q;
            }
        } catch (\Exception $e) {
            $this->questions = $oldquestions;
            $transaction->rollback($e);
        }

        $transaction->allow_commit();
        $this->sort_questions();
    }

    /**
     * Get FAQ page context.
     * @return \local_pg\context\page
     */
    public function get_context() {
        if (isset($this->context)) {
            return $this->context;
        }

        return \local_pg\context\page::instance($this->record->id);
    }

    /**
     * Get the next sortorder value.
     * @return int
     */
    public function get_next_sortorder(): int {
        $maxsortorder = 0;

        foreach ($this->questions as $q) {
            if ($q->sortorder > $maxsortorder) {
                $maxsortorder = $q->sortorder;
            }
        }

        return $maxsortorder + 1;
    }

    /**
     * Get questions editor options.
     * @return array{context: \local_pg\context\page,
     * maxbytes: mixed,
     * maxfiles: int,
     * noclean: bool,
     * subdirs: int,
     * trusttext: bool}
     */
    public function get_question_editor_options() {
        $options             = $this->get_answer_editor_options();
        $options['maxfiles'] = 0;

        return $options;
    }

    /**
     * Get answers editor options.
     * @return array{context: \local_pg\context\page,
     * maxbytes: mixed,
     * maxfiles: int,
     * noclean: bool,
     * subdirs: int,
     * trusttext: bool}
     */
    public function get_answer_editor_options() {
        global $CFG;

        return [
            'context'   => $this->get_context(),
            'maxfiles'  => 5,
            'maxbytes'  => $CFG->maxbytes,
            'trusttext' => false,
            'subdirs'   => 0,
            'noclean'   => true,
        ];
    }

    /**
     * Save a single question from submitted form data.
     * @param  \stdClass $data
     * @return bool
     */
    public function save_question($data) {
        global $DB, $USER, $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if (empty($data->id)) {
            $record                 = new \stdClass();
            $record->question       = '';
            $record->questionformat = 0;
            $record->answer         = '';
            $record->answerformat   = 0;
            $record->sortorder      = $this->get_next_sortorder();
            $record->visible        = $data->visible;
            $record->usermodified   = $USER->id;
            $record->timemodified   = time();
            $record->timecreated    = time();

            $data->id        = $DB->insert_record('local_pg_faq', $record);
            $data->sortorder = $record->sortorder;
        }

        $data = file_postupdate_standard_editor(
            $data,
            'question',
            self::get_question_editor_options(),
            $this->get_context(),
            'local_pg',
            helper::FAQ_Q_FILEAREA,
            $data->id
        );
        $data = file_postupdate_standard_editor(
            $data,
            'answer',
            self::get_answer_editor_options(),
            $this->get_context(),
            'local_pg',
            helper::FAQ_A_FILEAREA,
            $data->id
        );
        $data->usermodified = $USER->id;
        $data->timemodified = time();

        return $DB->update_record('local_pg_faq', $data);
    }

    /**
     * Get the FAQ page record.
     * @return \stdClass
     */
    public function get_faq_page_record(): \stdClass {
        global $DB, $USER;

        if (isset($this->record)) {
            return $this->record;
        }

        $fields  = 'id, shortname, header, layout, visible, pnav, snav';
        $faqpage = $DB->get_record('local_pg_pages', ['shortname' => 'faq'], $fields);

        if (!$faqpage) {
            $faqpage                = new \stdClass();
            $faqpage->shortname     = 'faq';
            $faqpage->header        = get_string('faq', 'local_pg');
            $faqpage->content       = '';
            $faqpage->contentformat = FORMAT_HTML;
            $faqpage->parent        = 0;
            $faqpage->css           = '';
            $faqpage->js            = '';
            $faqpage->timecreated   = time();
            $faqpage->timemodified  = time();
            $faqpage->usermodified  = $USER->id;
            $faqpage->layout        = 'standard';
            $faqpage->visible       = \local_pg\helper::PUBLIC;
            $faqpage->pnav          = 0;
            $faqpage->snav          = 0;
            $faqpage->id            = $DB->insert_record('local_pg_pages', $faqpage);
        }
        $this->record = $faqpage;

        return $this->record;
    }

    /**
     * Get the Table of FAQ to manage and sort questions.
     * @return string
     */
    public function get_faq_table() {
        global $OUTPUT;
        $table       = new html_table();
        $table->head = [
            'id'           => 'id',
            'question'     => get_string('question'),
            'answer'       => get_string('answer'),
            'visible'      => get_string('visible'),
            'timecreated'  => get_string('timecreated'),
            'timemodified' => get_string('update'),
            'actions'      => get_string('actions'),
        ];

        $candelete = has_capability('local/pg:delete', $this->get_context());
        $canedit   = has_capability('local/pg:edit', $this->get_context());

        foreach ($this->questions as $q) {
            $this->format_question($q);
            $this->format_answer($q);

            $qurl     = new moodle_url('/local/pg/faq.php', ['id' => $q->id]);
            $qactions = [];

            if ($canedit && $q->sortorder > 1) {
                $icon       = $OUTPUT->pix_icon('t/up', get_string('moveup'));
                $url        = new moodle_url($qurl, ['action' => 'moveup']);
                $qactions[] = html_writer::link($url, $icon);
            }

            if ($canedit && $q->sortorder < count($this->questions)) {
                $icon       = $OUTPUT->pix_icon('t/down', get_string('movedown'));
                $url        = new moodle_url($qurl, ['action' => 'movedown']);
                $qactions[] = html_writer::link($url, $icon);
            }

            if ($candelete) {
                $icon       = $OUTPUT->pix_icon('t/delete', get_string('delete'));
                $url        = new moodle_url($qurl, ['action' => 'delete']);
                $qactions[] = html_writer::link($url, $icon);
            }

            if ($canedit) {
                $icon       = $OUTPUT->pix_icon('t/edit', get_string('edit'));
                $url        = new moodle_url($qurl, ['action' => 'edit']);
                $qactions[] = html_writer::link($url, $icon);
            }
            $table->data[$q->id] = [
                'id'           => $q->id,
                'question'     => $q->question,
                'answer'       => $q->answer,
                'visible'      => $q->visible ? get_string('yes') : get_string('no'),
                'timecreated'  => userdate($q->timecreated),
                'timemodified' => userdate($q->timemodified),
                'actions'      => implode('|', $qactions),
            ];
        }

        return html_writer::table($table);
    }

    /**
     * Format a single question (Formating text and rewrite urls).
     * @param  \stdClass $q the whole question record.
     * @return void
     */
    public function format_question(\stdClass &$q): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $q->question = file_rewrite_pluginfile_urls(
            $q->question,
            'pluginfile.php',
            $this->get_context()->id,
            'local_pg',
            helper::FAQ_Q_FILEAREA,
            $q->id
        );
        $q->question = format_text($q->question, $q->questionformat, ['context' => $this->get_context()]);
    }

    /**
     * Format the answer text.
     * @param  \stdClass $q the whole question record
     * @return void
     */
    public function format_answer(\stdClass &$q): void {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $q->answer = file_rewrite_pluginfile_urls(
            $q->answer,
            'pluginfile.php',
            $this->get_context()->id,
            'local_pg',
            helper::FAQ_A_FILEAREA,
            $q->id
        );
        $q->answer = format_text($q->answer, $q->answerformat);
    }

    /**
     * Export questions and answer to template.
     * @param  \renderer_base                     $output
     * @return array{questions: array<\stdClass>}
     */
    public function export_for_template(\renderer_base $output) {
        $questions = [];

        foreach ($this->questions as $q) {
            if (empty($q->visible)) {
                continue;
            }
            $this->format_question($q);
            $this->format_answer($q);
            $questions[] = $q;
        }

        return ['questions' => $questions];
    }
}
