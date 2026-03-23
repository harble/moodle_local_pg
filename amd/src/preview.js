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
 * This module is used to help preview the page and highlight syntax for css and js codes.
 *
 * @module     local_pg/preview
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Modal from 'core/modal';
import Prefetch from 'core/prefetch';
import Ajax from 'core/ajax';
/* eslint-disable camelcase */
import {beautify} from './beautifier';
let js_beautify = beautify.js;
let css_beautify = beautify.css;

import {get_string} from 'core/str';
/* eslint-enable camelcase */
import {
    EditorState,
    EditorView,
    basicSetup,
    Compartment,
    lang,
} from './codemirror';


Prefetch.prefetchStrings('local_pg', [
    'jssyntaxerror',
    'csssyntaxerror',
]);

/**
 * @type {Object}
 */
let draftLangs = {};

let cssEditorView;
let jsEditorView;
/**
 * Open a modal to preview the page.
 */
async function preview() {
    // Create the URL object
    let url = new URL(M.cfg.wwwroot + '/local/pg/preview.php');

    // Add parameters to the URL
    url.searchParams.set("shortname", $('[name="shortname"]').val());
    url.searchParams.set("header", $('[name="header"]').val());
    url.searchParams.set("content", $('[name="content_editor[text]"]').val());
    url.searchParams.set("contentformat", $('[name="content_editor[format]"]').val());
    url.searchParams.set("layout", $('[name="layout"]').val());
    url.searchParams.set("css", $('[name="css"]').val());
    url.searchParams.set("js", $('[name="js"]').val());
    url.searchParams.set("sesskey", M.cfg.sesskey);
    let idInput = $('input[name="id"]');

    if (idInput.length == 1) {
        let id = idInput.val();
        if (id) {
            url.searchParams.set("id", id);
        }
    }

    let lang = $('[name=lang]').val();
    if (lang) {
        url.searchParams.set("lang", lang);
    }

    // Create the modal with the updated URL
    let modal = await Modal.create({
        large: true,
        show: true,
        removeOnClose: true,
        body: `<iframe src="${url.href}" class="embed-responsive-item"></iframe>`,
        title: 'Preview'
    });

    modal.show();
    $('[data-region="body"]').addClass('embed-responsive').addClass('embed-responsive-16by9');
    $('[data-region="modal"]').css({
        'max-width': '100%',
        'max-height': '100%',
        'padding': '0',
        'margin': '0'
    });
}

/**
 * Validate and save js code.
 * @param {CodeMirror} cm
 */
async function jsValidation(cm) {
    let errorPlaceholder = $('#js-text-error');

    var code = cm.state.doc.toString().trim();
    try {
        // Basic syntax check
        // eslint-disable-next-line no-new-func
        Function(code);
        errorPlaceholder.text('');
        errorPlaceholder.hide();
        $('input[type=submit]').removeAttr('disabled');
        return true;
    } catch (e) {
        errorPlaceholder.text(await get_string('jssyntaxerror', 'local_pg', e.message));
        errorPlaceholder.show();
        $('input[type=submit]').attr('disabled', true);
        return false;
    }
}

/**
 * Validate and save css code.
 * @param {CodeMirror} cm
 * @return {Boolean}
 */
async function validateCSS(cm) {
    let errorPlaceholder = $('#css-text-error');

    let cssCode = cm.state.doc.toString().trim();
    let errors = [];

    if (typeof window.CSSLint !== 'undefined') {
        let result = window.CSSLint.verify(cssCode);
        errors = result.messages.map(msg => `Line ${msg.line}: ${msg.message}`);
    } else {
        try {
            let style = document.createElement("style");
            style.textContent = cssCode;
            document.head.appendChild(style);
            if (style.sheet.cssRules.length === 0 && cssCode !== "") {
                errors.push("Invalid CSS detected.");
            }
            document.head.removeChild(style);
        } catch (e) {
            errors.push("Invalid CSS syntax.");
        }
    }

    if (errors.length > 0) {
        errorPlaceholder.text(await get_string('csssyntaxerror', 'local_pg', errors.join("<br>\n")));
        errorPlaceholder.show();
        return true;
    } else {
        errorPlaceholder.text('');
        errorPlaceholder.hide();
        return false;
    }
}
/**
 * @param {HTMLElement} textarea the text area element.
 * @param {Function} validator function (validateCSS or ValidateJS).
 * @returns
 */
function handleEditorUpdate(textarea, validator) {
    return EditorView.updateListener.of(update => {
        if (update.docChanged) {
            let valid = validator(update);
            if (!valid) {
                return;
            }

            const newValue = update.state.doc.toString();

            if (textarea.value !== newValue) {
                textarea.value = newValue;
            }
        }
    });
}
/**
 * Save draft values of langs in memory.
 */
function saveDraftLang() {
    let lang = $('[name=lang]').val();
    let title = $('[name=header]').val();
    let content = {
        text: $('[name="content_editor[text]"]').val(),
        format: $('[name="content_editor[format]"]').val(),
        itemid: $('[name="content_editor[itemid]"]').val()
    };

    draftLangs[lang] = {
        header: title,
        content: content
    };
}
/**
 * Fires when the language changed.
 */
async function changeLang() {
    let lang = $('[name=lang]').val();
    let disable = lang != "";

    $('[name=shortname]').attr('readonly', disable);

    if (draftLangs[lang]) {
        if (draftLangs[lang].header) {
            $('[name=header]').val(draftLangs[lang].header);
        }

        if (draftLangs[lang].content) {
            $('[name="content_editor[text]"]').val(draftLangs[lang].content.text);
            $('[name="content_editor[format]"]').val(draftLangs[lang].content.format);
            $('[name="content_editor[itemid]"]').val(draftLangs[lang].content.itemid);
            $('#id_content_editoreditable').html(draftLangs[lang].content.text);
        }

        return;
    }

    draftLangs[lang] = {
        header: undefined,
        content: {
            text: undefined,
            format: undefined,
            itemid: undefined
        }
    };

    let requests = Ajax.call([{
        methodname: 'local_pg_get_lang_content',
        args: {
            id: $('[name=id]').val(),
            lang: lang
        }
    }]);

    let response = await requests[0];
    if (response.header) {
        $('[name=header]').val(response.header);

        draftLangs[lang].header = response.header;
    }

    if (response.content_editor && response.content_editor.text) {
        draftLangs[lang].content = response.content_editor;
        $('[name="content_editor[text]"]').val(response.content_editor.text);
        $('#id_content_editoreditable').html(response.content_editor.text);

        $('[name="content_editor[format]"]').val(response.content_editor.format);
        $('[name="content_editor[itemid]"]').val(response.content_editor.itemid);
    }
}

export const init = () => {
    // Must be sure that the dom is ready so codemirror is loaded.
    $(function() {
        // Load JavaScript mode
        let jsTextarea = document.querySelector('textarea[name="js"]');
        const jsCompartment = new Compartment();

        const editorStyle = EditorView.theme({
                            '&': {
                                height: '300px',
                                width: '100%',
                                border: '1px solid #8f959e',
                                borderRadius: '0.5rem',
                            },
                        });
        if (jsTextarea) {
            const beautifiedJS = js_beautify(jsTextarea.value, {
                indent_size: 4
            });
            jsTextarea.value = beautifiedJS;

            // Create a container for CodeMirror (next to the textarea)
            const jsContainer = document.createElement('div');
            jsContainer.style.width = '100%';
            jsTextarea.style.display = 'none';
            jsTextarea.parentNode.insertBefore(jsContainer, jsTextarea.nextSibling);

            jsEditorView = new EditorView({
                state: EditorState.create({
                    doc: beautifiedJS,
                    extensions: [
                        basicSetup,
                        editorStyle,
                        lang.javascript(),
                        jsCompartment.of([]),
                        handleEditorUpdate(jsTextarea, jsValidation)
                    ]
                }),
                parent: jsContainer
            });
        }

        // Load CSS mode
        let cssTextarea = document.querySelector('textarea[name="css"]');
        const cssCompartment = new Compartment();

        if (cssTextarea) {
            const beautifiedCSS = css_beautify(cssTextarea.value, {
                indent_size: 2
            });
            cssTextarea.value = beautifiedCSS;

            const cssContainer = document.createElement('div');
            cssContainer.style.width = '100%';
            cssTextarea.style.display = 'none';
            cssTextarea.parentNode.insertBefore(cssContainer, cssTextarea.nextSibling);

            cssEditorView = new EditorView({
                state: EditorState.create({
                    doc: beautifiedCSS,
                    extensions: [
                        basicSetup,
                        lang.css(),
                        editorStyle,
                        cssCompartment.of([]),
                        handleEditorUpdate(cssTextarea, validateCSS)
                    ]
                }),
                parent: cssContainer
            });
        }

        // I need to freeze the Codemirror textarea so that the user can't change the code if selected lang not ''.
        let langInput = $('[name=lang]');
        langInput.on('change', function() {
            let readOnly = $(this).val() !== ''; // Read-only if lang is not empty
            if (jsEditorView) {
                jsEditorView.dispatch({
                    effects: jsCompartment.reconfigure(readOnly ? EditorView.editable.of(false) : [])
                });
            }
            if (cssEditorView) {
                cssEditorView.dispatch({
                    effects: cssCompartment.reconfigure(readOnly ? EditorView.editable.of(false) : [])
                });
            }
            changeLang();
        });

        $('button[name="preview"]').on("click", function() {
            preview();
        });

        $('[name="content_editor[text]"], [name="header"]').on('input, change', saveDraftLang);

        saveDraftLang();
    });
};
