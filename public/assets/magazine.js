const one = (selector) => document.querySelector(selector);
const all = (selector) => Array.from(document.querySelectorAll(selector));
const toast = one('[data-toast]');
let toastTimer;
function announce(message) {
  if (!toast) return;
  clearTimeout(toastTimer);
  toast.textContent = message;
  toast.hidden = false;
  toastTimer = setTimeout(() => { toast.hidden = true; }, 4000);
}

function openDialog(dialog) {
  if (!dialog || dialog.open) return;
  dialog.showModal();
  dialog.querySelector('input')?.focus();
}
all('[data-sections-open]').forEach(button => button.addEventListener('click', () => openDialog(one('[data-sections-dialog]'))));
all('[data-search-open]').forEach(button => button.addEventListener('click', () => openDialog(one('[data-search-dialog]'))));
all('[data-dialog-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog')?.close()));
all('dialog').forEach(dialog => {
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  });
});
document.addEventListener('keydown', event => {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    all('dialog[open]').forEach(dialog => dialog.close());
    openDialog(one('[data-search-dialog]'));
  }
});

const savedKey = 'wisdom-news-saved-articles';
function readSaved() {
  try {
    const data = JSON.parse(localStorage.getItem(savedKey) || '[]');
    if (!Array.isArray(data)) return [];
    return data.filter(item => item && typeof item.id === 'string' && typeof item.title === 'string' && typeof item.path === 'string' && item.path.startsWith('/article/') && !item.path.startsWith('//')).slice(0,200);
  } catch { return []; }
}
function persistSaved(items) {
  try { localStorage.setItem(savedKey, JSON.stringify(items)); return true; }
  catch { announce('Your browser could not save this article.'); return false; }
}
const article = one('[data-article-id]');
const saveButtons = all('[data-save-article]');
function syncSaved() {
  const items = readSaved();
  all('[data-saved-count]').forEach(count => { count.textContent = String(items.length); count.hidden = items.length === 0; });
  const saved = article ? items.some(item => item.id === article.dataset.articleId) : false;
  saveButtons.forEach(button => {
    button.setAttribute('aria-pressed', String(saved));
    button.setAttribute('aria-label', saved ? 'Remove this article from saved articles' : 'Save this article');
    const label = button.querySelector('[data-save-label]');
    if (label) label.textContent = saved ? 'Saved' : 'Save article';
  });
}
function renderSaved() {
  const container = one('[data-saved-list]');
  if (!container) return;
  container.replaceChildren();
  const items = readSaved();
  if (items.length === 0) {
    const empty = document.createElement('div'); empty.className = 'saved-empty';
    const heading = document.createElement('h3'); heading.textContent = 'Your next read starts here.';
    const text = document.createElement('p'); text.textContent = 'Save an article while you read, and find it here when you want to come back.';
    empty.append(heading,text); container.append(empty); return;
  }
  for (const item of items) {
    const row = document.createElement('article'); row.className = 'saved-entry';
    const imageLink = document.createElement('a'); imageLink.href = item.path; imageLink.tabIndex = -1; imageLink.setAttribute('aria-hidden','true');
    const image = document.createElement('img'); image.alt = ''; image.loading = 'lazy';
    try { const imageUrl = new URL(item.image,location.origin); if (['http:','https:'].includes(imageUrl.protocol)) image.src = imageUrl.href; } catch { /* A missing image does not hide the story. */ }
    imageLink.append(image);
    const copy = document.createElement('div');
    const category = document.createElement('p'); category.textContent = item.category || 'Wisdom News';
    const title = document.createElement('h3'); if (/[\u0B80-\u0BFF]/.test(item.title)) title.lang = 'ta';
    const link = document.createElement('a'); link.href = item.path; link.textContent = item.title; title.append(link); copy.append(category,title);
    const remove = document.createElement('button'); remove.className = 'icon-button'; remove.type = 'button'; remove.textContent = '×'; remove.style.fontSize = '25px'; remove.setAttribute('aria-label','Remove '+item.title+' from saved articles');
    remove.addEventListener('click', () => { if(persistSaved(readSaved().filter(saved => saved.id !== item.id))) { renderSaved(); syncSaved(); announce('Removed from your reading list.'); container.querySelector('button')?.focus(); } });
    row.append(imageLink,copy,remove); container.append(row);
  }
}
all('[data-saved-open]').forEach(button => button.addEventListener('click', () => { renderSaved(); openDialog(one('[data-saved-dialog]')); }));
saveButtons.forEach(button => button.addEventListener('click', () => {
  if (!article) return;
  const items = readSaved();
  const exists = items.some(item => item.id === article.dataset.articleId);
  const next = exists ? items.filter(item => item.id !== article.dataset.articleId) : [{id:article.dataset.articleId,path:new URL(article.dataset.articleUrl,location.origin).pathname,title:article.dataset.articleTitle,image:article.dataset.articleImage,category:article.dataset.articleCategory},...items].slice(0,200);
  if (persistSaved(next)) { syncSaved(); announce(exists ? 'Removed from your reading list.' : 'Saved to your reading list.'); }
}));
window.addEventListener('storage', event => { if (event.key === savedKey) { syncSaved(); if(one('[data-saved-dialog]')?.open) renderSaved(); } });
syncSaved();

all('[data-copy-link]').forEach(button => button.addEventListener('click', async () => {
  const link = article?.dataset.articleUrl || location.href;
  try {
    if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(link);
    else {
      const input = document.createElement('textarea'); input.value = link; input.style.position = 'fixed'; input.style.opacity = '0'; document.body.append(input); input.select();
      const copied = document.execCommand('copy'); input.remove(); button.focus(); if (!copied) throw new Error('Clipboard unavailable');
    }
    announce('Article link copied.');
  } catch { announce('Could not copy the link. You can copy it from the address bar.'); }
}));
one('[data-share-article]')?.addEventListener('click', event => {
  const button = event.currentTarget;
  const panel = one('[data-share-panel]');
  if (panel) { panel.hidden = !panel.hidden; button.setAttribute('aria-expanded',String(!panel.hidden)); }
});

const storyGrid = one('[data-story-grid]');
const viewButtons = all('[data-view]');
function applyView(view) {
  if (!storyGrid) return;
  const selected = view === 'list' ? 'list' : 'grid';
  storyGrid.dataset.layout = selected;
  viewButtons.forEach(button => button.setAttribute('aria-pressed',String(button.dataset.view === selected)));
}
try { applyView(localStorage.getItem('wisdom-news-article-view')); } catch { applyView('grid'); }
viewButtons.forEach(button => button.addEventListener('click', () => { applyView(button.dataset.view); try { localStorage.setItem('wisdom-news-article-view',button.dataset.view); } catch { /* View still works without persistence. */ } }));
one('[data-story-sort]')?.addEventListener('change', event => event.currentTarget.form.requestSubmit());

const prose = one('[data-reading-body]');
if (prose) {
  let textSize = 20;
  try { const stored = Number(localStorage.getItem('wisdom-news-reading-size')); if (stored >= 18 && stored <= 26) textSize = stored; } catch { /* Use the default size. */ }
  function applyTextSize() {
    prose.style.setProperty('--reading-size',textSize+'px');
    const label = one('[data-text-size]'); if (label) label.textContent = String(textSize);
    const smaller = one('[data-text-smaller]'); const larger = one('[data-text-larger]');
    if (smaller) smaller.disabled = textSize <= 18; if (larger) larger.disabled = textSize >= 26;
  }
  function changeSize(delta) { textSize = Math.max(18,Math.min(26,textSize+delta)); applyTextSize(); try { localStorage.setItem('wisdom-news-reading-size',String(textSize)); } catch { /* Text sizing remains available. */ } }
  one('[data-text-smaller]')?.addEventListener('click', () => changeSize(-2));
  one('[data-text-larger]')?.addEventListener('click', () => changeSize(2));
  applyTextSize();
  const headings = Array.from(prose.querySelectorAll('h2,h3')).filter(heading => heading.textContent.trim());
  if (headings.length >= 2) {
    const container = one('[data-contents-links]');
    headings.forEach((heading,index) => { if (!heading.id) heading.id = 'reading-section-'+(index+1); const link = document.createElement('a'); link.href = '#'+heading.id; link.textContent = heading.textContent; if (container) container.append(link); });
    const contents = one('[data-article-contents]'); if (contents) contents.hidden = false;
  }
  const progress = one('[data-reading-progress]');
  let pending = false;
  function updateProgress() {
    pending = false;
    const top = prose.getBoundingClientRect().top + window.scrollY;
    const range = Math.max(1,prose.offsetHeight-window.innerHeight*.5);
    const value = Math.max(0,Math.min(1,(window.scrollY+window.innerHeight*.25-top)/range));
    if (progress) progress.style.transform = 'scaleX('+value+')';
  }
  const scheduleProgress = () => { if (!pending) { pending = true; requestAnimationFrame(updateProgress); } };
  window.addEventListener('scroll',scheduleProgress,{passive:true}); window.addEventListener('resize',scheduleProgress);
  prose.querySelectorAll('img').forEach(image => image.addEventListener('load',scheduleProgress));
  document.fonts?.ready.then(scheduleProgress); scheduleProgress();
}
