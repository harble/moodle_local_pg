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

namespace local_pg;

use cacheable_object;
use core\exception\required_capability_exception;
use html_writer;
use local_pg\mustache\mustache;
use moodle_url;
use single_button;
use stdClass;

/**
 * Class serve.
 *
 * @package    local_pg
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class serve implements cacheable_object {
    /**
     * Slashed arguments from the current page url.
     * @var array
     */
    protected array $args;

    /**
     * Page shortname.
     * @var string
     */
    protected string $shortname;

    /**
     * Page header (title).
     * @var string
     */
    protected string $header;

    /**
     * Page content (html).
     * @var string
     */
    protected string $content;

    /**
     * If this page should be displayed in the primary navigation.
     * @var bool
     */
    protected bool $pnav;

    /**
     * If this page should be displayed in the secondary navigation.
     * @var bool
     */
    protected bool $snav;

    /**
     * The page content after formatted and replacing placeholders for files and strings.
     * @var string
     */
    protected string $formattedcontent;

    /**
     * Page id.
     * @var int
     */
    protected int $id;

    /**
     * If the multilang loaded or not.
     * @var bool
     */
    protected bool $multilangloaded = false;
    /**
     * Multilang record id.
     * @var int
     */
    protected int $langid;
    /**
     * Page creation time.
     * @var int
     */
    protected int $timecreated;

    /**
     * Page last modified time.
     * @var int
     */
    protected int $timemodified;

    /**
     * Page content format usually HTML_FORMAT.
     * @var int
     */
    protected int $contentformat;

    /**
     * Page css style code.
     * @var string
     */
    protected string $css;

    /**
     * The css code after minification.
     * @var string
     */
    protected string $minifiedcss;

    /**
     * Page js code.
     * @var string
     */
    protected string $js;

    /**
     * The js code after minification.
     * @var string
     */
    protected string $minifiedjs;

    /**
     * The page exists or not.
     * @var bool
     */
    protected bool $exist;

    /**
     * Page visibility.
     * @var int
     */
    protected int $visible;

    /**
     * Page parent id.
     * @var int
     */
    protected int $parent;

    /**
     * Page layout.
     * @var string
     */
    protected string $layout;

    /**
     * Page context.
     * @var context\page
     */
    public context\page $context;

    /**
     * Page url.
     * @var \moodle_url
     */
    public \moodle_url $url;

    /**
     * Extra content to be displayed after the main content.
     * @var string
     */
    public string $after = '';

    /**
     * Extra content to be displayed before the main content.
     * @var string
     */
    public string $pre = '';

    /**
     * array of cached instances.
     * @var self[]
     */
    protected static $cached = [];
    /**
     * prepare to serve the page by check the slashed arguments and page existence.
     * @param \stdClass|int|null $page
     * @param bool               $loadcontent
     */
    public function __construct($page = null, $loadcontent = true) {
        if (is_number($page) && $page > 0) {
            global $DB;
            $fields = '*';

            if (!$loadcontent) {
                $fields = 'id, shortname, header, pnav, snav, visible, parent, layout, path';
            }

            $page = $DB->get_record('local_pg_pages', ['id' => $page], $fields);
        }

        if (empty($page)) {
            $this->construct_from_args();

            return;
        }

        if (is_object($page)) {
            foreach ($page as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }

            if (!isset($this->exist)) {
                $this->exist = true;
            }

            if (empty($this->args)) {
                $this->args = [$page->shortname];
            }

            return;
        }

        $this->exist = false;
        $this->args  = [];
    }

    /**
     * Magic getter
     * @param string $name
     */
    public function __get($name) {
        if ($name == 'title') {
            $name = 'header';
        }

        if (property_exists($this, $name)) {
            return $this->$name;
        }

        debugging('Property ' . $name . ' does not exist in class ' . self::class, DEBUG_DEVELOPER);
        return null;
    }
    /**
     * Get page shortname from slashed args.
     * @return array
     */
    protected static function get_args() {
        global $CFG, $ME;
        if (strpos($ME, '/local/pg/') === 0) {
            $url = new moodle_url($ME);
            $path = str_replace('\\', '/', $url->get_path());
            $path = str_replace('/local/pg/', '', $path);
            $args = explode('/', $path);
            $args = array_filter($args);
            if (!empty($args)) {
                return $args;
            }
        }

        require_once($CFG->libdir . '/configonlylib.php');
        $args = min_get_slash_argument();

        if (!empty($args)) {
            $args = explode('/', $args);
        } else {
            $args = [];

            if (!empty($_GET)) {
                foreach ($_GET as $key => $value) {
                    if ($value === '') {
                        $args += explode('/', $key);
                    } else {
                        if (stristr($key, '?')) {
                            $tosearch = explode('?', $key);
                        } else if (stristr($key, '&')) {
                            $tosearch = explode('&', $key);
                        }

                        if (!empty($tosearch)) {
                            $args += explode('/', $tosearch[0]);
                            $_GET[$tosearch[1]] = $value;
                        }
                    }
                }
            }
        }

        foreach ($args as $key => $value) {
            $args[$key] = clean_param($value, PARAM_ALPHANUMEXT);

            if (empty($args[$key])) {
                unset($args[$key]);
            }
        }

        return array_values($args);
    }

    /**
     * Complete the construction of the class from the slashed arguments.
     * @return void
     */
    protected function construct_from_args() {
        $this->args  = array_values(self::get_args());
        $this->exist = $this->page_exists();
    }

    /**
     * Make an instance of this class.
     * @param  ?int  $pageid
     * @param  bool  $loadcontent load the page content, js, css or not
     * @param ?string $shortname if the shortname specified the id of the page will be ignored
     *                           in case if it is not existed in the cache.
     * @return serve
     */
    public static function make($pageid = null, $loadcontent = true, $shortname = null): self {
        global $DB;

        if ($pageid && !$shortname) {
            foreach (static::$cached as $page) {
                if (isset($page->id) && $page->id == $pageid) {
                    return $page;
                }
            }

            $shortname = $DB->get_field('local_pg_pages', 'shortname', ['id' => $pageid]);
        }

        if (!isset($shortname)) {
            $args = self::get_args();
            $shortname = array_pop($args) ?? null;

            // Todo: get the page by its path.
            $path = "/{$shortname}";
            while ($parent = array_pop($args)) {
                $path = "/{$parent}{$path}";
            }
        }

        if ($shortname && isset(static::$cached[$shortname])) {
            return static::$cached[$shortname];
        }

        if (empty($shortname)) {
            // Page not exists.
            return new self();
        }

        $cache = \cache::make('local_pg', 'pages');
        $page  = $cache->get($shortname);

        if ($page) {
            if ($page instanceof self) {
                $page->load_current_lang_content();
                static::$cached[$shortname] = $page;
                return $page;
            }

            $instance = new self($page, $loadcontent);
            if ($loadcontent) {
                $instance->load_current_lang_content();
            }

            return $instance;
        }

        $instance = new self($pageid, $loadcontent);

        $cache->set($shortname, $instance);

        $instance->load_current_lang_content();

        static::$cached[$shortname] = $instance;

        return $instance;
    }

    /**
     * Prepare the class object to be saved in cache.
     * @return stdClass
     */
    public function prepare_to_cache() {
        $data        = new stdClass();
        $data->args  = $this->args;
        $data->exist = $this->page_exists();

        if (!$data->exist) {
            return $data;
        }

        foreach ($this as $key => $value) {
            if (isset($data->$key)) {
                continue;
            }

            if ($key == 'url') {
                continue;
            }

            if (is_object($value) || is_array($value)) {
                continue;
            }

            $data->$key = $value;
        }

        return $data;
    }

    /**
     * Wake the class from cache.
     * @param  object $data
     * @return serve
     */
    public static function wake_from_cache($data) {
        return new self($data);
    }

    /**
     * Check if the page is visible to the current user.
     *
     * @param  bool $strict if true, throw exception if the page is not visible.
     * @throws required_capability_exception
     * @return bool
     */
    public function is_visible($strict = false) {
        if (!isset($this->visible)) {
            // Should not happen.
            debugging('Page visibility is not set.', DEBUG_DEVELOPER);
            return false;
        }

        if (has_capability('local/pg:viewhidden', $this->get_page_context())) {
            return true;
        }

        if ($this->get_page_shortname() === 'support') {
            global $CFG, $USER;
            if (!isset($CFG->supportavailability)
             || $CFG->supportavailability == CONTACT_SUPPORT_DISABLED
             || ($CFG->supportavailability == CONTACT_SUPPORT_AUTHENTICATED
                 && (!isloggedin() || isguestuser()))
            ) {
                return false;
            }
        }

        if ($this->visible == helper::PUBLIC) {
            return true;
        }

        if ($this->visible == helper::ALLOW_GUEST && isloggedin()) {
            return true;
        }

        if ($this->visible == helper::REQUIRE_AUTH && isloggedin() && !isguestuser()) {
            return true;
        }

        if (!$strict) {
            return false;
        }

        throw new required_capability_exception($this->get_page_context(), 'local/pg:viewhidden', 'nopermissions', '');
    }

    /**
     * Set the page url.
     *
     * @param moodle_url $url
     */
    public function set_page_url(\moodle_url $url) {
        $this->url = $url;
    }

    /**
     * Get the page url.
     *
     * @return moodle_url
     */
    public function get_page_url() {
        if (isset($this->url)) {
            return $this->url;
        }

        $this->url = new moodle_url('/local/pg/' . $this->get_page_shortname(), ['page' => $this->id]);

        return $this->url;
    }

    /**
     * Get the page shortname.
     *
     * @return string|null
     */
    public function get_page_shortname() {
        if (isset($this->shortname)) {
            return $this->shortname;
        }

        if (empty($this->args)) {
            return null;
        }

        return end($this->args);
    }

    /**
     * Check if the page exists.
     *
     * @return bool
     */
    public function page_exists() {
        global $DB, $ME;
        if (isset($this->exist)) {
            return $this->exist;
        }

        if (empty($this->args)) {

            if (stristr($ME, '/local/pg/')) {
                if (!$id = optional_param('id', null, PARAM_INT)) {
                    $id = optional_param('page', null, PARAM_INT);
                }

                if ($id) {
                    $page = $DB->get_record('local_pg_pages', ['id' => $id]);

                    if ($page) {
                        $this->args = [$page->shortname];
                    }
                }

                if (empty($this->args)) {
                    return false;
                }
            }
        }

        if (empty($page)) {
            $page = $DB->get_record('local_pg_pages', ['shortname' => $this->get_page_shortname()]);
        }

        if ($page) {
            foreach ($page as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Get the page title.
     *
     * @return string
     */
    public function get_title() {
        if (!$this->multilangloaded) {
            $this->load_current_lang_content(true);
        }
        return format_string($this->header);
    }

    /**
     * Load the content, css, js from the database.
     * @return void
     */
    protected function load_content() {
        global $DB;
        $page = $DB->get_record('local_pg_pages', ['id' => $this->id]);

        if ($page) {
            $this->content       = $page->content;
            $this->contentformat = $page->contentformat;
            $this->css           = $page->css;
            $this->js            = $page->js;
        }
        $this->load_current_lang_content();
    }

    /**
     * Load content according to the current language.
     * @param bool $titleonly if to only load the title without the content
     * @return void
     */
    public function load_current_lang_content($titleonly = false) {
        global $DB;
        if (!$this->page_exists()) {
            return;
        }

        $lang = current_language();
        $strman = get_string_manager();

        $parents = [];
        if (method_exists($strman, 'get_language_dependencies')) {
            $parents = $strman->get_language_dependencies($lang);
        }

        $langs = array_unique(array_merge($parents, [$lang]));
        [$langin, $langparams] = $DB->get_in_or_equal($langs, SQL_PARAMS_NAMED);

        $fields = 'id, header, lang';
        $notnullconditions = 'pl.header IS NOT NULL';
        if (!$titleonly) {
            $fields .= ', content';
            $notnullconditions .= ' OR pl.content IS NOT NULL';
        }

        $sql = "SELECT $fields
                FROM {local_pg_langs} pl
                WHERE pl.lang $langin
                  AND pl.pageid = :pageid
                  AND ($notnullconditions)
                  ORDER BY pl.header DESC, pl.id DESC";
        $params = ['pageid' => $this->id] + $langparams;
        $result = $DB->get_records_sql($sql, $params);

        $contentfound = false;
        $headerfound = false;
        foreach ($result as $row) {
            if (!empty($row->header) && (!$headerfound || $row->lang == $lang)) {
                $headerfound = true;
                $this->header = $row->header;
            }

            if (!empty($row->content) && (!$contentfound || $row->lang == $lang)) {
                $contentfound = true;
                $this->content = $row->content;
                $this->langid = $row->id;
                unset($this->formattedcontent);
            }
        }
        $this->multilangloaded = true;
    }
    /**
     * Get the page content.
     *
     * @return string
     */
    public function get_formatted_content() {
        if (isset($this->formattedcontent)) {
            return $this->pre . $this->formattedcontent . $this->after;
        }

        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if (!isset($this->content)) {
            $this->load_content();
        }

        $this->set_page_context();

        $content = file_rewrite_pluginfile_urls(
            $this->content,
            'pluginfile.php',
            $this->get_page_context()->id,
            'local_pg',
            empty($this->langid) ? helper::CONTENT_FILEAREA : helper::CUSTOMLANG_FILEAREA,
            empty($this->langid) ? $this->id : $this->langid
        );
        self::render_content($content);

        $options                = ['context' => $this->get_page_context(), 'noclean' => true];
        $this->formattedcontent = format_text($content, $this->contentformat, $options);

        return $this->pre . $this->formattedcontent . $this->after;
    }

    /**
     * Render the contents as if it is from a mustache file to replace strings, userdate and others.
     * @param  string $content
     * @param  array  $context
     * @return void
     */
    public static function render_content(&$content, $context = []) {
        global $USER, $SITE;
        $mustache = mustache::get_mustache();

        $context = [
            'sitename'     => format_string($SITE->fullname),
            'userfullname' => @fullname($USER) ?? get_string('guest'),
        ] + $context;
        $content = $mustache->render($content, $context);
    }

    /**
     * Get the records of the children pages.
     * @return array
     */
    public function get_children_pages() {
        global $DB;
        $sort     = 'path ASC, id ASC';
        $fields   = 'id, shortname, header, visible';
        $children = $DB->get_records('local_pg_pages', ['parent' => $this->id], $sort, $fields);

        return $children;
    }

    /**
     * Get the parent page record.
     * @return ?stdClass
     */
    public function get_parent_page() {
        global $DB;

        if (empty($this->parent)) {
            return null;
        }

        return $DB->get_record('local_pg_pages', ['id' => $this->parent], 'id, shortname, header, path, visible');
    }

    /**
     * Get the minified css code.
     *
     * @return string
     */
    public function get_minified_css() {
        if (isset($this->minifiedcss)) {
            return $this->minifiedcss;
        }

        if (!isset($this->css)) {
            $this->load_content();
        }

        try {
            $minifier = new \core_minify();
            $css      = $this->css;
            $css      = '.local-pg-' . $this->get_page_shortname() . '{' . $css . '}';
            $out      = $minifier->css($css);

            if (strlen($out) > strlen($css)) {
                $out = '';
            }
        } catch (\Throwable $e) {
            $out = '';
        }
        $this->minifiedcss = $out;

        return $out;
    }

    /**
     * Get the minified js code.
     *
     * @return string
     */
    public function get_minified_js() {
        if (isset($this->minifiedjs)) {
            return $this->minifiedjs;
        }

        if (!isset($this->js)) {
            $this->load_content();
        }

        try {
            $minifier = new \core_minify();
            $out      = $minifier->js($this->js);

            if (strlen($out) > strlen($this->js)) {
                $out = '';
            }
        } catch (\Throwable $e) {
            $out = '';
        }
        $this->minifiedjs = $out;

        return $out;
    }

    /**
     * Get the page context.
     *
     * @return context\page
     */
    public function get_page_context() {
        if (!isset($this->context)) {
            $this->context = context\page::instance($this->id, MUST_EXIST);
        }

        return $this->context;
    }

    /**
     * Inject the js into the page.
     * @return void
     */
    protected function inject_js() {
        global $PAGE;
        $js = $this->get_minified_js();

        if (!empty($js)) {
            $PAGE->requires->js_init_code($js, true);
        }
    }

    /**
     * Inject the css code into the header of the page.
     * @return void
     */
    protected function inject_css() {
        global $PAGE;
        $css = $this->get_minified_css();

        if (!empty($css)) {
            $injector = <<<JS
                var style = document.createElement('style');
                style.innerHTML = '{$css}';
                document.head.appendChild(style);
            JS;
            $PAGE->requires->js_init_code($injector, false);
        }
    }

    /**
     * Set a pre-content to be displayed before the page main content.
     * @param  string $pre
     * @param  bool   $format either to format the pre content or add it raw.
     * @return void
     */
    public function set_pre_content($pre, $format = false) {
        if ($format) {
            $this->pre = format_text($pre, FORMAT_MOODLE, ['context' => $this->get_page_context()]);
        } else {
            $this->pre = $pre;
        }
    }

    /**
     * Set extra content to be displayed after the page main contents.
     * @param  string $after
     * @param  bool   $format either to format the after content or add it raw.
     * @return void
     */
    public function set_after_content($after, $format = false) {
        if ($format) {
            $this->after = format_text($after, FORMAT_MOODLE, ['context' => $this->get_page_context()]);
        } else {
            $this->after = $after;
        }
    }

    /**
     * Render the content of the page in a html box
     * without tempering the $PAGE or sending headers...
     * @return string
     */
    public function out_content_only() {
        global $OUTPUT;

        if (!$this->is_visible()) {
            return '';
        }

        $this->inject_js();
        $this->inject_css();

        $out = $OUTPUT->heading($this->get_title(), 4);
        $out .= $this->get_formatted_content();

        foreach ($this->get_children_pages() as $child) {
            $serve = new self($child, false);

            if ($serve->is_visible()) {
                $url = $serve->get_page_url();
                $out .= \html_writer::empty_tag('hr');
                $out .= \html_writer::link($url, $OUTPUT->heading($serve->get_title(), 5));
            }
        }

        return $OUTPUT->box($out, 'generalbox local-pg local-pg-' . $this->get_page_shortname());
    }

    /**
     * Set the context to this page context.
     * @return void
     */
    public function set_page_context() {
        global $PAGE;
        $PAGE->set_context($this->get_page_context());
    }

    /**
     * Serve the page.
     */
    public function serve() {
        global $PAGE, $OUTPUT, $FULLME, $CFG;

        if ($this->get_page_shortname() === 'faq') {
            $fullme = (new moodle_url($FULLME))->out_omit_querystring();

            if (!stristr($fullme, $CFG->wwwroot . '/local/pg/faq.php')) {
                redirect(new moodle_url('/local/pg/faq.php'));
            }
        }

        if ($this->get_page_shortname() === 'support') {
            $fullme = (new moodle_url($FULLME))->out_omit_querystring();

            if (!stristr($fullme, $CFG->wwwroot . '/local/pg/support.php')) {
                redirect(new moodle_url('/local/pg/support.php'));
            }
        }

        // Throw exception if page is not visible to the current user.
        $this->is_visible(true);

        $PAGE->add_body_class('local-pg-' . $this->get_page_shortname());

        $this->set_page_context();
        $PAGE->set_url($this->get_page_url());

        $PAGE->set_pagelayout($this->layout);

        $PAGE->set_title($this->get_title());
        $PAGE->set_heading($this->get_title());

        $thisnode = $PAGE->navigation->find('page-' . $this->get_page_shortname(), null);
        $thisnode->make_active();
        $thisnode->action = null;

        $PAGE->has_navbar();
        $PAGE->navbar->ignore_active();

        if ($parent = $this->get_parent_page()) {
            $parentnode = $PAGE->navbar->add(
                $parent->header,
                new moodle_url('/local/pg/index.php/' . $parent->shortname),
                \navbar::TYPE_CUSTOM,
                $parent->shortname,
                'page-' . $parent->shortname
            );
        } else {
            $parentnode = &$PAGE->navbar;
        }

        $parentnode->add(
            $this->get_title(),
            $this->get_page_url(),
            \navbar::TYPE_CUSTOM,
            $this->get_page_shortname(),
            'page-' . $this->get_page_shortname()
        )->make_active();

        if ($this->pnav) {
            while ($active = $PAGE->primarynav->find_active_node()) {
                $active->make_inactive();
            }

            $PAGE->primarynav->find('page-' . $this->get_page_shortname(), null)->make_active();
        }

        $this->inject_js();
        $this->inject_css();

        echo $OUTPUT->header();

        if ($PAGE->user_is_editing()) {
            $hasbuttons = false;

            if (has_capability('local/pg:add', \context_system::instance())) {
                $editurl = new moodle_url('/local/pg/edit.php');
                echo $OUTPUT->single_button(
                    $editurl,
                    get_string('addpage', 'local_pg'),
                    'get',
                    ['type' => single_button::BUTTON_INFO]
                );
                $hasbuttons = true;
            }

            if (has_capability('local/pg:edit', $this->get_page_context())) {
                $editurl = new moodle_url('/local/pg/edit.php', ['id' => $this->id]);
                echo $OUTPUT->single_button(
                    $editurl,
                    get_string('editpage', 'local_pg'),
                    'get',
                    ['type' => single_button::BUTTON_WARNING]
                );
                $hasbuttons = true;
            }

            if (has_capability('local/pg:delete', $this->get_page_context())) {
                $deleteurl = new moodle_url('/local/pg/delete.php', ['id' => $this->id]);
                echo $OUTPUT->single_button(
                    $deleteurl,
                    get_string('deletepage', 'local_pg'),
                    'get',
                    ['type' => single_button::BUTTON_DANGER]
                );
                $hasbuttons = true;
            }

            if ($hasbuttons) {
                echo html_writer::empty_tag('hr');
            }
        }

        echo $this->get_formatted_content();

        foreach ($this->get_children_pages() as $child) {
            $serve = new self($child, false);

            if ($serve->is_visible()) {
                $url = $serve->get_page_url();
                echo html_writer::empty_tag('hr');
                echo html_writer::link($url, $OUTPUT->heading($serve->get_title(), 4));
            }
        }
        echo $OUTPUT->footer();

        die;
    }
}
