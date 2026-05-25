/**
 * Notion-ähnlicher Editor für die Wissensdatenbank
 * Slash-Befehle (/), Bubble-Menu bei Auswahl, alle Formatierungen wie Notion
 */
import { Editor } from 'https://esm.sh/@tiptap/core@2';
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2';
import Link from 'https://esm.sh/@tiptap/extension-link@2';
import Image from 'https://esm.sh/@tiptap/extension-image@2';
import Placeholder from 'https://esm.sh/@tiptap/extension-placeholder@2';
import Underline from 'https://esm.sh/@tiptap/extension-underline@2';
import HorizontalRule from 'https://esm.sh/@tiptap/extension-horizontal-rule@2';
import { BubbleMenu } from 'https://esm.sh/@tiptap/extension-bubble-menu@2';

const SLASH_COMMANDS_BASE = [
  { id: 'heading1', label: 'Überschrift 1', icon: 'H1', cmd: 'heading', attrs: { level: 1 } },
  { id: 'heading2', label: 'Überschrift 2', icon: 'H2', cmd: 'heading', attrs: { level: 2 } },
  { id: 'heading3', label: 'Überschrift 3', icon: 'H3', cmd: 'heading', attrs: { level: 3 } },
  { id: 'paragraph', label: 'Text', icon: '¶', cmd: 'paragraph' },
  { id: 'page', label: 'Seite', icon: '📄', cmd: 'page' },
  { id: 'bulletList', label: 'Aufzählung', icon: '•', cmd: 'bulletList' },
  { id: 'orderedList', label: 'Nummerierte Liste', icon: '1.', cmd: 'orderedList' },
  { id: 'taskList', label: 'To-do-Liste', icon: '☑', cmd: 'taskList' },
  { id: 'blockquote', label: 'Zitat', icon: '"', cmd: 'blockquote' },
  { id: 'codeBlock', label: 'Code', icon: '</>', cmd: 'codeBlock' },
  { id: 'horizontalRule', label: 'Trennlinie', icon: '—', cmd: 'horizontalRule' },
  { id: 'image', label: 'Bild', icon: '🖼', cmd: 'image' },
];
let SLASH_COMMANDS = SLASH_COMMANDS_BASE;

function runCommand(editor, item, uploadImageCallback, pageCreateCtx) {
  const { from } = editor.state.selection;
  const $pos = editor.state.doc.resolve(from);
  const node = $pos.parent;
  const text = (node.type.name === 'paragraph' || node.type.name === 'doc') ? node.textContent : '';
  const lineStart = $pos.start();
  if (text.startsWith('/') && from > lineStart) {
    editor.chain().focus().deleteRange({ from: lineStart, to: from }).run();
  }

  if (item.cmd === 'page' && pageCreateCtx) {
    editor.chain().focus().setParagraph().run();
    editor._kbPageCreateMode = true;
    return;
  }

  const chain = editor.chain().focus();
  if (item.cmd === 'heading') chain.toggleHeading({ level: item.attrs?.level || 1 });
  else if (item.cmd === 'paragraph') chain.setParagraph();
  else if (item.cmd === 'bulletList') chain.toggleBulletList();
  else if (item.cmd === 'orderedList') chain.toggleOrderedList();
  else if (item.cmd === 'taskList') chain.toggleTaskList();
  else if (item.cmd === 'blockquote') chain.toggleBlockquote();
  else if (item.cmd === 'codeBlock') chain.toggleCodeBlock();
  else if (item.cmd === 'horizontalRule') chain.setHorizontalRule();
  else if (item.cmd === 'image' && typeof uploadImageCallback === 'function') uploadImageCallback();
  else chain.run();
}

function getSlashQuery(editor) {
  const { from } = editor.state.selection;
  const $pos = editor.state.doc.resolve(from);
  const node = $pos.parent;
  const text = typeof node.textContent === 'string' ? node.textContent : '';
  if (!text.startsWith('/')) return null;
  const afterSlash = text.slice(1).trimStart();
  const query = afterSlash.toLowerCase();
  return { query, fullText: text };
}

function filterCommands(items, query) {
  if (!query) return items;
  const q = query.toLowerCase();
  return items.filter(c =>
    c.label.toLowerCase().includes(q) || c.id.toLowerCase().includes(q)
  );
}

function renderSlashMenu(items, selectedIndex) {
  let html = '<div class="kb-slash-menu py-1 min-w-[220px] max-h-[320px] overflow-y-auto">';
  items.forEach((item, i) => {
    const sel = i === selectedIndex ? ' bg-gray-100 dark:bg-gray-600' : '';
    html += `<button type="button" class="kb-slash-item w-full text-left px-3 py-2 text-sm flex items-center gap-3 hover:bg-gray-100 dark:hover:bg-gray-600${sel}" data-cmd="${item.id}">`;
    html += `<span class="w-8 text-center font-medium text-gray-500 dark:text-gray-400">${item.icon}</span>`;
    html += `<span class="text-gray-900 dark:text-gray-100">${item.label}</span>`;
    html += '</button>';
  });
  html += '</div>';
  return html;
}

export async function createNotionEditor(options) {
  const {
    element,
    initialContent,
    uploadUrl,
    basePath,
    onImageSelect,
    pagesApiUrl,
    currentPageId,
  } = options;

  const pageCreateCtx = (pagesApiUrl && typeof pagesApiUrl === 'string') ? { pagesApiUrl, currentPageId: currentPageId || null } : null;

  let TaskListExt = null;
  let TaskItemExt = null;
  try {
    TaskListExt = (await import('https://esm.sh/@tiptap/extension-task-list@2')).default;
    TaskItemExt = (await import('https://esm.sh/@tiptap/extension-task-item@2')).default;
  } catch (_) {}
  let slashCommands = TaskListExt && TaskItemExt
    ? SLASH_COMMANDS_BASE
    : SLASH_COMMANDS_BASE.filter(c => c.cmd !== 'taskList');
  if (!pageCreateCtx) {
    slashCommands = slashCommands.filter(c => c.cmd !== 'page');
  }

  const extensions = [
    StarterKit.configure({
      codeBlock: { HTMLAttributes: { class: 'rounded bg-gray-100 dark:bg-gray-700 p-3 font-mono text-sm' } },
      blockquote: { HTMLAttributes: { class: 'border-l-4 border-primary-500 pl-4 italic text-gray-700 dark:text-gray-300' } },
    }),
    Underline,
    Link.configure({ openOnClick: false, HTMLAttributes: { target: '_blank', rel: 'noopener' } }),
    Image.configure({ inline: false, allowBase64: false }),
    HorizontalRule,
    Placeholder.configure({ placeholder: 'Schreibe etwas oder tippe / für Befehle…' }),
    BubbleMenu.configure({
        element: document.getElementById('kb-bubble-menu') || (() => {
          const el = document.createElement('div');
          el.id = 'kb-bubble-menu';
          el.className = 'kb-bubble-menu hidden flex items-center gap-0.5 p-1 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 shadow-lg z-[100]';
          document.body.appendChild(el);
          return el;
        })(),
        pluginKey: 'bubbleMenu',
        tippyOptions: { duration: 150, maxWidth: 'none' },
      }),
  ];
  if (TaskListExt) extensions.splice(extensions.length - 1, 0, TaskListExt.configure({ HTMLAttributes: { class: 'list-none pl-0 space-y-1' } }));
  if (TaskItemExt) extensions.splice(extensions.length - 1, 0, TaskItemExt.configure({ nested: true, HTMLAttributes: { class: 'flex items-start gap-2' }, taskItemCheckedStyles: 'line-through opacity-60' }));

  const editor = new Editor({
    element,
    extensions,
    content: initialContent || '<p></p>',
    editorProps: {
      attributes: { class: 'ProseMirror kb-editor-body min-h-[320px] focus:outline-none' },
      handleKeyDown(view, event) {
        if (event.key === 'Escape' && editor._kbPageCreateMode) {
          editor._kbPageCreateMode = false;
          return false;
        }
        if (event.key === 'Enter' && editor._kbPageCreateMode && pageCreateCtx) {
          event.preventDefault();
          const { from } = editor.state.selection;
          const $pos = editor.state.doc.resolve(from);
          const node = $pos.parent;
          const title = (node.textContent || '').trim();
          editor._kbPageCreateMode = false;
          if (!title) {
            editor.chain().focus().splitBlock().run();
            return true;
          }
          (async () => {
            try {
              const r = await fetch(pageCreateCtx.pagesApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title, parent_id: pageCreateCtx.currentPageId }),
                credentials: 'same-origin',
              });
              const d = await r.json();
              if (d.success && d.id) {
                const href = (basePath.replace(/\/$/, '') || '') + '/knowledge/edit.php?id=' + encodeURIComponent(d.id);
                const start = $pos.start();
                const end = $pos.end();
                const esc = (s) => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
                editor.chain().focus().deleteRange({ from: start, to: end }).insertContent('<a href="' + esc(href) + '">' + esc(title) + '</a>').run();
                if (typeof window.showToast === 'function') window.showToast('Seite erstellt: ' + title, 'success');
                document.dispatchEvent(new CustomEvent('kb-pages-updated'));
                document.dispatchEvent(new CustomEvent('kb-editor-request-save'));
              } else {
                if (typeof window.showToast === 'function') window.showToast(d.error || 'Fehler beim Erstellen', 'error');
                editor.chain().focus().splitBlock().run();
              }
            } catch (e) {
              if (typeof window.showToast === 'function') window.showToast('Fehler beim Erstellen der Seite', 'error');
              editor.chain().focus().splitBlock().run();
            }
          })();
          return true;
        }
        const slashEl = document.getElementById('kb-slash-menu');
        if (slashEl && !slashEl.classList.contains('hidden')) {
          if (event.key === 'ArrowDown') {
            event.preventDefault();
            const items = slashEl.querySelectorAll('.kb-slash-item');
            const current = slashEl.querySelector('.kb-slash-item.bg-gray-100, .kb-slash-item.bg-gray-600');
            let idx = current ? Array.from(items).indexOf(current) + 1 : 0;
            if (idx >= items.length) idx = 0;
            items.forEach((el, i) => {
              el.classList.toggle('bg-gray-100', false);
              el.classList.toggle('bg-gray-600', false);
              if (i === idx) el.classList.add(document.documentElement.classList.contains('dark') ? 'bg-gray-600' : 'bg-gray-100');
            });
            items[idx]?.scrollIntoView({ block: 'nearest' });
            return true;
          }
          if (event.key === 'ArrowUp') {
            event.preventDefault();
            const items = slashEl.querySelectorAll('.kb-slash-item');
            const current = slashEl.querySelector('.kb-slash-item.bg-gray-100, .kb-slash-item.bg-gray-600');
            let idx = current ? Array.from(items).indexOf(current) - 1 : items.length - 1;
            if (idx < 0) idx = items.length - 1;
            items.forEach((el, i) => {
              el.classList.toggle('bg-gray-100', false);
              el.classList.toggle('bg-gray-600', false);
              if (i === idx) el.classList.add(document.documentElement.classList.contains('dark') ? 'bg-gray-600' : 'bg-gray-100');
            });
            items[idx]?.scrollIntoView({ block: 'nearest' });
            return true;
          }
          if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            const selected = slashEl.querySelector('.kb-slash-item.bg-gray-100, .kb-slash-item.bg-gray-600') || slashEl.querySelector('.kb-slash-item');
            if (selected && window.__kbSlashSelect) window.__kbSlashSelect(selected.dataset.cmd);
            return true;
          }
          if (event.key === 'Escape') {
            slashEl.classList.add('hidden');
            return true;
          }
        }
        return false;
      },
    },
  });

  // Bubble-Menu-Inhalt (wird von BubbleMenu-Extension nicht automatisch gefüllt – wir füllen es manuell)
  const bubbleEl = document.getElementById('kb-bubble-menu');
  if (bubbleEl) {
    bubbleEl.innerHTML = `
      <button type="button" class="kb-bubble-btn px-2 py-1.5 rounded text-sm font-bold hover:bg-gray-100 dark:hover:bg-gray-700" data-cmd="bold" title="Fett">B</button>
      <button type="button" class="kb-bubble-btn px-2 py-1.5 rounded text-sm italic hover:bg-gray-100 dark:hover:bg-gray-700" data-cmd="italic" title="Kursiv">I</button>
      <button type="button" class="kb-bubble-btn px-2 py-1.5 rounded text-sm underline hover:bg-gray-100 dark:hover:bg-gray-700" data-cmd="underline" title="Unterstrichen">U</button>
      <button type="button" class="kb-bubble-btn px-2 py-1.5 rounded text-sm line-through hover:bg-gray-100 dark:hover:bg-gray-700" data-cmd="strike" title="Durchgestrichen">S</button>
      <span class="w-px h-5 bg-gray-200 dark:bg-gray-600 mx-0.5"></span>
      <button type="button" class="kb-bubble-btn px-2 py-1.5 rounded text-sm font-mono hover:bg-gray-100 dark:hover:bg-gray-700" data-cmd="code" title="Code">Code</button>
      <button type="button" class="kb-bubble-btn px-2 py-1.5 rounded text-sm hover:bg-gray-100 dark:hover:bg-gray-700" data-cmd="link" title="Link">🔗</button>
    `;
    bubbleEl.querySelectorAll('.kb-bubble-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const cmd = btn.dataset.cmd;
        if (cmd === 'link') {
          const url = window.prompt('URL:', 'https://');
          if (url) editor.chain().focus().setLink({ href: url }).run();
        } else {
          const m = { bold: 'toggleBold', italic: 'toggleItalic', underline: 'toggleUnderline', strike: 'toggleStrike', code: 'toggleCode' };
          if (m[cmd]) editor.chain().focus()[m[cmd]]().run();
        }
      });
    });
  }

  // Slash-Menü-Container sicher anlegen
  let menuEl = document.getElementById('kb-slash-menu');
  if (!menuEl) {
    menuEl = document.createElement('div');
    menuEl.id = 'kb-slash-menu';
    menuEl.className = 'hidden fixed rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-xl z-[9999]';
    document.body.appendChild(menuEl);
  }

  function showSlashMenu(coords, query) {
    const items = filterCommands(slashCommands, query);
    if (items.length === 0) return;
    const el = document.getElementById('kb-slash-menu');
    if (!el) return;
    el.innerHTML = renderSlashMenu(items, 0);
    el.classList.remove('hidden');
    el.style.position = 'fixed';
    el.style.left = (coords.left || 0) + 'px';
    el.style.top = (coords.bottom !== undefined ? coords.bottom + 4 : coords.top + 24) + 'px';
    el.style.zIndex = '9999';

    el.querySelectorAll('.kb-slash-item').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const item = slashCommands.find(c => c.id === btn.dataset.cmd);
        if (item) {
          runCommand(editor, item, onImageSelect, pageCreateCtx);
          el.classList.add('hidden');
        }
      });
    });

    window.__kbSlashSelect = (cmdId) => {
      const item = slashCommands.find(c => c.id === cmdId);
      if (item) {
        runCommand(editor, item, onImageSelect, pageCreateCtx);
        document.getElementById('kb-slash-menu').classList.add('hidden');
      }
      window.__kbSlashSelect = null;
    };
  }

  function hideSlashMenu() {
    const menuEl = document.getElementById('kb-slash-menu');
    if (menuEl) menuEl.classList.add('hidden');
    window.__kbSlashSelect = null;
  }

  // Slash-Menü: nach Dokument-Update prüfen („update“ = nach dem Einfügen, damit „/“ im Doc steht)
  editor.on('update', () => {
    if (editor._kbPageCreateMode) return;
    const slashData = getSlashQuery(editor);
    if (slashData) {
      const coords = editor.view.coordsAtPos(editor.state.selection.from);
      showSlashMenu(coords, slashData.query);
    } else {
      hideSlashMenu();
    }
  });

  editor.on('blur', () => { setTimeout(hideSlashMenu, 150); });

  // Echten Editor-Root markieren und Styles injizieren (damit H1, Listen, HR etc. garantiert sichtbar sind)
  const editorRoot = editor.view.dom;
  editorRoot.classList.add('kb-editor-inner');
  if (!document.getElementById('kb-editor-inner-styles')) {
    const style = document.createElement('style');
    style.id = 'kb-editor-inner-styles';
    style.textContent = `
.kb-editor-inner h1 { font-size: 1.875rem !important; font-weight: 700 !important; line-height: 1.2 !important; margin: 0.75em 0 0.35em !important; display: block !important; }
.kb-editor-inner h1:first-child { margin-top: 0 !important; }
.kb-editor-inner h2 { font-size: 1.5rem !important; font-weight: 600 !important; margin: 0.6em 0 0.3em !important; display: block !important; }
.kb-editor-inner h3 { font-size: 1.25rem !important; font-weight: 600 !important; margin: 0.5em 0 0.25em !important; display: block !important; }
.kb-editor-inner ul { list-style: none !important; padding-left: 0 !important; margin: 0.5em 0 !important; display: block !important; }
.kb-editor-inner ul:not([data-type="taskList"]) li { display: block !important; margin: 0.15em 0 !important; padding-left: 1.25rem !important; position: relative !important; }
.kb-editor-inner ul:not([data-type="taskList"]) li::before { content: "•" !important; position: absolute !important; left: 0 !important; font-weight: 700 !important; }
.kb-editor-inner ul[data-type="taskList"] li { display: flex !important; align-items: flex-start !important; gap: 0.5rem !important; padding-left: 0 !important; }
.kb-editor-inner ul[data-type="taskList"] li[data-checked="true"] { opacity: 0.7 !important; text-decoration: line-through !important; }
.kb-editor-inner ul[data-type="taskList"] li::before { content: none !important; }
.kb-editor-inner ol { list-style: none !important; counter-reset: kb-ol !important; padding-left: 0 !important; margin: 0.5em 0 !important; display: block !important; }
.kb-editor-inner ol li { display: block !important; margin: 0.15em 0 !important; padding-left: 2rem !important; position: relative !important; counter-increment: kb-ol !important; }
.kb-editor-inner ol li::before { content: counter(kb-ol) "." !important; position: absolute !important; left: 0 !important; font-weight: 600 !important; }
.kb-editor-inner blockquote { border-left: 4px solid #3b82f6 !important; padding-left: 1rem !important; margin: 0.75em 0 !important; font-style: italic !important; display: block !important; }
.dark .kb-editor-inner blockquote { border-left-color: #60a5fa !important; }
.kb-editor-inner pre { background: #f1f5f9 !important; padding: 1rem !important; border-radius: 0.375rem !important; font-size: 0.875rem !important; line-height: 1.5 !important; overflow-x: auto !important; margin: 0.75em 0 !important; display: block !important; white-space: pre-wrap !important; }
.dark .kb-editor-inner pre { background: #334155 !important; }
.kb-editor-inner hr { border: none !important; border-top: 2px solid #cbd5e1 !important; margin: 1.25em 0 !important; display: block !important; height: 0 !important; }
.dark .kb-editor-inner hr { border-top-color: #475569 !important; }
.kb-editor-inner :not(pre) > code { background: #e2e8f0 !important; padding: 0.15em 0.4em !important; border-radius: 0.25rem !important; font-size: 0.875em !important; font-family: ui-monospace, monospace !important; }
.dark .kb-editor-inner :not(pre) > code { background: #475569 !important; }
`;
    document.head.appendChild(style);
  }

  return editor;
}
