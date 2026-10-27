"use strict";
const one = (selector) => document.querySelector(selector);
const languageStorageKey = 'traditional-news-language';
const translations = {
    en: {
        'skip.content': 'Skip to content', 'nav.about': 'About', 'nav.contact': 'Contact', 'nav.home': 'Home',
        'nav.main': 'Main navigation', 'menu.open': 'Open menu', 'search.label': 'Search',
        'search.newsroom': 'Search the newsroom', 'search.placeholder': 'Topics, places, people…',
        'breaking.label': 'Breaking news', 'breaking.short': 'Breaking', 'ad.label': 'Advertisement',
        'home.topStories': 'Top stories', 'home.latestNews': 'Latest news', 'home.viewAll': 'View all',
        'home.trending': 'Trending', 'home.more': 'More', 'home.by': 'By',
        'listing.browse': 'Browse coverage', 'listing.noStories': 'No stories yet',
        'listing.fresh': 'Fresh reporting will appear here as it is published.',
        'footer.explore': 'Explore', 'footer.company': 'Company', 'footer.about': 'About us',
        'footer.editorial': 'Editorial policy', 'footer.privacy': 'Privacy', 'footer.terms': 'Terms',
        'footer.newsroom': 'Newsroom', 'footer.rights': 'All rights reserved.', 'language.select': 'Select language',
        'admin.navigation': 'Newsroom navigation', 'admin.toggleNavigation': 'Toggle navigation',
        'admin.overview': 'Overview', 'admin.content': 'Content', 'admin.publishing': 'Publishing',
        'admin.system': 'System', 'admin.articles': 'Articles', 'admin.categories': 'Categories',
        'admin.subcategories': 'Subcategories', 'admin.authors': 'Authors', 'admin.tags': 'Tags',
        'admin.breakingNews': 'Breaking news', 'admin.mediaLibrary': 'Media library',
        'admin.advertisements': 'Advertisements', 'admin.adPositions': 'Ad positions',
        'admin.pages': 'Pages', 'admin.navigationItem': 'Navigation', 'admin.comments': 'Comments',
        'admin.users': 'Users', 'admin.settings': 'Settings', 'admin.activityLogs': 'Activity logs',
        'admin.signOut': 'Sign out', 'admin.newsroom': 'Newsroom', 'admin.dashboard': 'Dashboard',
        'admin.totalArticles': 'Total articles', 'admin.manageStories': 'Manage stories →',
        'admin.published': 'Published', 'admin.draftsInProgress': 'drafts in progress',
        'admin.organizeCoverage': 'Organize coverage →', 'admin.activeNewsroom': 'Active newsroom',
        'admin.adCampaigns': 'advertising campaigns', 'admin.recentlyEdited': 'Recently edited',
        'admin.latestActivity': 'The latest newsroom activity.', 'admin.newArticle': 'New article',
        'admin.story': 'Story', 'admin.author': 'Author', 'admin.status': 'Status', 'admin.updated': 'Updated',
        'status.draft': 'Draft', 'status.published': 'Published', 'status.scheduled': 'Scheduled', 'status.archived': 'Archived'
    },
    ta: {
        'skip.content': 'உள்ளடக்கத்திற்குச் செல்லவும்', 'nav.about': 'எங்களைப் பற்றி', 'nav.contact': 'தொடர்பு', 'nav.home': 'முகப்பு',
        'nav.main': 'முதன்மை வழிசெலுத்தல்', 'menu.open': 'மெனுவைத் திறக்கவும்', 'search.label': 'தேடல்',
        'search.newsroom': 'செய்தியகத்தில் தேடுங்கள்', 'search.placeholder': 'தலைப்புகள், இடங்கள், நபர்கள்…',
        'breaking.label': 'முக்கிய செய்திகள்', 'breaking.short': 'முக்கிய செய்தி', 'ad.label': 'விளம்பரம்',
        'home.topStories': 'முக்கிய செய்திகள்', 'home.latestNews': 'சமீபத்திய செய்திகள்', 'home.viewAll': 'அனைத்தையும் காண்க',
        'home.trending': 'பிரபலமானவை', 'home.more': 'மேலும்', 'home.by': 'எழுதியவர்',
        'listing.browse': 'செய்திகளை ஆராயுங்கள்', 'listing.noStories': 'இதுவரை செய்திகள் இல்லை',
        'listing.fresh': 'புதிய செய்திகள் வெளியிடப்படும்போது இங்கே தோன்றும்.',
        'footer.explore': 'ஆராயுங்கள்', 'footer.company': 'நிறுவனம்', 'footer.about': 'எங்களைப் பற்றி',
        'footer.editorial': 'ஆசிரியர் கொள்கை', 'footer.privacy': 'தனியுரிமை', 'footer.terms': 'விதிமுறைகள்',
        'footer.newsroom': 'செய்தியகம்', 'footer.rights': 'அனைத்து உரிமைகளும் பாதுகாக்கப்பட்டவை.', 'language.select': 'மொழியைத் தேர்ந்தெடுக்கவும்',
        'admin.navigation': 'செய்தியக வழிசெலுத்தல்', 'admin.toggleNavigation': 'வழிசெலுத்தலை மாற்றவும்',
        'admin.overview': 'மேலோட்டம்', 'admin.content': 'உள்ளடக்கம்', 'admin.publishing': 'வெளியீடு',
        'admin.system': 'அமைப்பு', 'admin.articles': 'கட்டுரைகள்', 'admin.categories': 'பிரிவுகள்',
        'admin.subcategories': 'துணைப் பிரிவுகள்', 'admin.authors': 'ஆசிரியர்கள்', 'admin.tags': 'குறிச்சொற்கள்',
        'admin.breakingNews': 'முக்கிய செய்திகள்', 'admin.mediaLibrary': 'ஊடக நூலகம்',
        'admin.advertisements': 'விளம்பரங்கள்', 'admin.adPositions': 'விளம்பர இடங்கள்',
        'admin.pages': 'பக்கங்கள்', 'admin.navigationItem': 'வழிசெலுத்தல்', 'admin.comments': 'கருத்துகள்',
        'admin.users': 'பயனர்கள்', 'admin.settings': 'அமைப்புகள்', 'admin.activityLogs': 'செயல்பாட்டுப் பதிவுகள்',
        'admin.signOut': 'வெளியேறு', 'admin.newsroom': 'செய்தியகம்', 'admin.dashboard': 'கட்டுப்பாட்டுப் பலகை',
        'admin.totalArticles': 'மொத்தக் கட்டுரைகள்', 'admin.manageStories': 'செய்திகளை நிர்வகிக்க →',
        'admin.published': 'வெளியிடப்பட்டவை', 'admin.draftsInProgress': 'வரைவுகள் தயாரிப்பில் உள்ளன',
        'admin.organizeCoverage': 'செய்திப் பிரிவுகளை ஒழுங்குபடுத்து →', 'admin.activeNewsroom': 'செயலில் உள்ள செய்தியகம்',
        'admin.adCampaigns': 'விளம்பர பிரச்சாரங்கள்', 'admin.recentlyEdited': 'சமீபத்தில் திருத்தப்பட்டவை',
        'admin.latestActivity': 'சமீபத்திய செய்தியகச் செயல்பாடு.', 'admin.newArticle': 'புதிய கட்டுரை',
        'admin.story': 'செய்தி', 'admin.author': 'ஆசிரியர்', 'admin.status': 'நிலை', 'admin.updated': 'புதுப்பிக்கப்பட்டது',
        'status.draft': 'வரைவு', 'status.published': 'வெளியிடப்பட்டது', 'status.scheduled': 'திட்டமிடப்பட்டது', 'status.archived': 'காப்பகப்படுத்தப்பட்டது'
    }
};
const applyLanguage = (language) => {
    document.documentElement.lang = language;
    document.documentElement.dataset.language = language;
    document.body.classList.toggle('lang-ta', language === 'ta');
    document.querySelectorAll('[data-i18n]').forEach((element) => {
        const key = element.dataset.i18n ?? '';
        const value = translations[language][key] ?? translations.en[key];
        if (value)
            element.textContent = value;
    });
    document.querySelectorAll('[data-i18n-aria-label]').forEach((element) => {
        const key = element.dataset.i18nAriaLabel ?? '';
        const value = translations[language][key] ?? translations.en[key];
        if (value)
            element.setAttribute('aria-label', value);
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach((element) => {
        const key = element.dataset.i18nPlaceholder ?? '';
        const value = translations[language][key] ?? translations.en[key];
        if (value)
            element.placeholder = value;
    });
    document.querySelectorAll('[data-i18n-data-label]').forEach((element) => {
        const key = element.dataset.i18nDataLabel ?? '';
        const value = translations[language][key] ?? translations.en[key];
        if (value)
            element.dataset.label = value;
    });
    document.querySelectorAll('[data-localized-content]').forEach((element) => {
        const english = element.dataset.en ?? element.textContent ?? '';
        const tamil = element.dataset.ta?.trim() ?? '';
        element.textContent = language === 'ta' && tamil !== '' ? tamil : english;
    });
};
try {
    localStorage.removeItem(languageStorageKey);
}
catch { }
applyLanguage('ta');
const menuButton = one('[data-menu-button]');
const menu = one('[data-menu]');
menuButton?.addEventListener('click', () => {
    const open = menu?.classList.toggle('open') ?? false;
    menuButton.setAttribute('aria-expanded', String(open));
});
const searchButton = one('[data-search-button]');
const searchForm = one('[data-search-form]');
searchButton?.addEventListener('click', () => {
    if (!searchForm)
        return;
    searchForm.hidden = !searchForm.hidden;
    searchButton.setAttribute('aria-expanded', String(!searchForm.hidden));
    if (!searchForm.hidden)
        searchForm.querySelector('input')?.focus();
});
const adminMenuButton = one('[data-admin-menu]');
adminMenuButton?.addEventListener('click', () => {
    const open = one('[data-admin-sidebar]')?.classList.toggle('open') ?? false;
    adminMenuButton.setAttribute('aria-expanded', String(open));
});
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm ?? 'Continue?'))
            event.preventDefault();
    });
});
document.querySelectorAll('[data-editor]').forEach((editor) => {
    const form = editor.closest('form');
    const input = editor.closest('[data-editor-field], label, .resource-form')?.querySelector('[data-editor-input]');
    form?.addEventListener('submit', () => { if (input)
        input.value = editor.innerHTML; });
});
document.querySelectorAll('[data-format]').forEach((button) => {
    button.addEventListener('click', () => {
        const editor = button.closest('[data-editor-field], label, .resource-form')?.querySelector('[data-editor]');
        editor?.focus();
        const format = button.dataset.format;
        if (format === 'a') {
            const href = window.prompt('இணைப்பு (https://…)');
            if (href && /^https?:\/\//i.test(href))
                document.execCommand('createLink', false, href);
        }
        else if (format === 'ul')
            document.execCommand('insertUnorderedList');
        else if (format === 'strong' || format === 'em')
            document.execCommand(format === 'strong' ? 'bold' : 'italic');
        else if (format)
            document.execCommand('formatBlock', false, format);
    });
});
document.querySelectorAll('[data-insert-image]').forEach((button) => {
    button.addEventListener('click', () => {
        const container = button.closest('[data-editor-field], label');
        const editor = container?.querySelector('[data-editor]');
        const picker = container?.querySelector('[data-editor-image]');
        const option = picker?.selectedOptions[0];
        if (!editor || !option?.value) {
            window.alert('முதலில் பட நூலகத்திலிருந்து ஒரு படத்தைத் தேர்ந்தெடுக்கவும்.');
            return;
        }
        editor.focus();
        const src = option.value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        const alt = (option.dataset.alt ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        document.execCommand('insertHTML', false, `<figure><img src="${src}" alt="${alt}" loading="lazy"><figcaption>${alt}</figcaption></figure><p><br></p>`);
    });
});
const featuredSelect = one('[data-featured-image-select]');
const featuredUpload = one('[data-featured-upload]');
const featuredPreview = one('[data-featured-image-preview]');
const previewImage = featuredPreview?.querySelector('img');
const showFeaturedPreview = (src) => {
    if (!featuredPreview || !previewImage)
        return;
    featuredPreview.hidden = src === '';
    if (src)
        previewImage.src = src;
};
featuredSelect?.addEventListener('change', () => {
    showFeaturedPreview(featuredSelect.selectedOptions[0]?.dataset.imageUrl ?? '');
});
featuredUpload?.addEventListener('change', () => {
    const file = featuredUpload.files?.[0];
    if (file)
        showFeaturedPreview(URL.createObjectURL(file));
});
if (featuredSelect?.value)
    showFeaturedPreview(featuredSelect.selectedOptions[0]?.dataset.imageUrl ?? '');
const slugSource = one('[data-slug-source]');
const slugTarget = one('[data-slug-target]');
if (slugTarget?.value) slugTarget.dataset.touched = 'true';
slugSource?.addEventListener('input', () => {
    if (!slugTarget || slugTarget.dataset.touched === 'true')
        return;
    slugTarget.value = slugSource.value.toLowerCase().trim().normalize('NFC').replace(/[^\p{L}\p{M}\p{N}_-]+/gu, '-').replace(/^-|-$/g, '');
});
slugTarget?.addEventListener('input', () => { slugTarget.dataset.touched = 'true'; });
const categorySelect = one('[data-category-select]');
const subcategorySelect = one('[data-subcategory-select]');
const subcategoryHelp = one('[data-subcategory-help]');
const filterSubcategories = () => {
    if (!categorySelect || !subcategorySelect)
        return;
    const categoryId = categorySelect.value;
    let available = 0;
    Array.from(subcategorySelect.options).forEach((option, index) => {
        if (index === 0)
            return;
        const matches = option.dataset.categoryId === categoryId;
        option.hidden = !matches;
        option.disabled = !matches;
        if (matches)
            available += 1;
    });
    const selected = subcategorySelect.selectedOptions[0];
    if (selected?.disabled)
        subcategorySelect.value = '';
    subcategorySelect.disabled = categoryId === '' || available === 0;
    if (subcategoryHelp) {
        subcategoryHelp.textContent = categoryId === ''
            ? 'உட்பிரிவுகளைக் காண முதலில் பிரிவைத் தேர்ந்தெடுக்கவும்.'
            : available === 0 ? 'இந்தப் பிரிவில் உட்பிரிவுகள் இல்லை.' : `${available} உட்பிரிவுகள் உள்ளன.`;
    }
};
categorySelect?.addEventListener('change', filterSubcategories);
filterSubcategories();
const tagFilter = one('[data-tag-filter]');
tagFilter?.addEventListener('input', () => {
    const term = tagFilter.value.trim().toLocaleLowerCase();
    let matches = 0;
    document.querySelectorAll('#article-tags .check').forEach(label => {
        label.hidden = !label.textContent.toLocaleLowerCase().includes(term);
        if (!label.hidden) matches++;
    });
    const count = one('[data-tag-count]');
    if (count) count.textContent = matches + ' குறிச்சொற்கள் உள்ளன. தேர்ந்தெடுத்தவை தக்கவைக்கப்படும்.';
});
document.querySelectorAll('.editor-layout, .resource-form').forEach(form => {
    let dirty = false;
    const markDirty = event => { if (!event.target.closest('[data-body-image-dialog]')) dirty = true; };
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    form.addEventListener('submit', event => { queueMicrotask(() => { if (!event.defaultPrevented) dirty = false; }); });
    window.addEventListener('beforeunload', event => {
        if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
});
one('[data-ad-image]')?.addEventListener('change', event => {
    const input = one('[name=image_url]');
    if (input && event.target.value) { input.value = event.target.value; input.dispatchEvent(new Event('input', {bubbles:true})); }
});
