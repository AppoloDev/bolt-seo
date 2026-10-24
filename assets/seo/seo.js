class SeoSnippet {
    constructor(container) {
        this.container = container;
        this.defaultsData = {
            title: this.container.dataset.baseTitle,
            url: this.container.dataset.baseUrl,
            description: this.container.dataset.baseDescription,
            slug: this.container.dataset.baseSlug,
        };
        // The server resolves the title postfix (separator + postfix/sitename) exactly
        // like the live <title>. Append it to the preview title so the snippet matches
        // what the extension actually renders in the browser.
        this.titlePostfix = this.container.dataset.baseTitlePostfix || '';
        this.targetElements = {
            url: document.querySelector('.seo_snippet span.url'),
            title: document.querySelector('.seo_snippet .title'),
            description: document.querySelector('.seo_snippet .description')
        };

        this.inputs = {
            title: document.querySelector(`[name="${this.container.dataset.fieldTitle}"]`),
            slug: document.querySelector(`[name="${this.container.dataset.fieldSlug}"]`),
            description: document.querySelector(`[name="${this.container.dataset.fieldDescription}"]`),
        }

        this.seoFieldsInputs = {
            title: document.querySelector('#seofields-title'),
            description: document.querySelector('#seofields-description'),
            keywords: document.querySelector('#seofields-keywords'),
            shortlink: document.querySelector('#seofields-shortlink'),
            canonical: document.querySelector('#seofields-canonical'),
            robots: document.querySelector('#seofields-robots'),
            og: document.querySelector('#seofields-og'),
        }

        this.seoField = document.querySelector(`[name="${this.container.dataset.seoField}"]`);
        this.seoData = JSON.parse(this.seoField.value);

        this.init();
        this.initEvents();
    }

    init() {
        const defaultsData = Object.assign({}, this.defaultsData);
        if(this.seoData.title !== '') {
            defaultsData.title = this.seoData.title;
        }
        if(this.seoData.description !== '') {
            defaultsData.description = this.seoData.description;
        }
        this.targetElements.title.textContent = defaultsData.title + this.titlePostfix;
        this.targetElements.url.textContent = defaultsData.url.replace('REPLACE', defaultsData.slug);
        this.targetElements.description.textContent = defaultsData.description;
    }

    initEvents() {
        // Listen on `input` rather than `keyup` so the snippet preview and the
        // persisted JSON stay in sync for every way a value can change — not just
        // physical key presses, but also paste, browser autofill, and programmatic
        // updates (e.g. automated form filling), all of which fire `input` but not
        // `keyup`.
        if (this.inputs.title) {
            this.inputs.title.addEventListener('input', (e) => {
                this.changeTarget('title');
            });
        }

        if (this.inputs.description) {
            this.inputs.description.addEventListener('input', (e) => {
                this.changeTarget('description');
            });
        }

        if (this.inputs.slug) {
            this.inputs.slug.addEventListener('input', (e) => {
                const defaultsData = Object.assign({}, this.defaultsData);
                this.targetElements.url.textContent = defaultsData.url.replace('REPLACE', this.inputs.slug.value);
            });
        }

        Object.keys(this.seoFieldsInputs).forEach((key) => {
            // `robots` is a <select>; the rest are text inputs/areas. `change`
            // covers selects, `input` covers all text edits including paste/autofill.
            const eventName = key === 'robots' ? 'change' : 'input';

            this.seoFieldsInputs[key].addEventListener(eventName, (e) => {
                this.changeTarget(key);
            });
        })
    }

    changeTarget(field) {
        const seoFieldValue = this.seoFieldsInputs[field]
            ? this.stripHtml(this.seoFieldsInputs[field].value)
            : '';

        if (field === 'title' || field === 'description') {
            // The content field is often a rich-text field, so show it as plain
            // text, like the server does. Empty editor markup (`<p><br></p>`)
            // then counts as empty too.
            const contentValue = this.inputs[field] ? this.stripHtml(this.inputs[field].value) : '';

            let value;
            if (
                contentValue.length > 0 &&
                seoFieldValue.length === 0
            ) {
                value = contentValue;
            } else if (seoFieldValue.length > 0) {
                value = seoFieldValue;
            } else {
                value = this.defaultsData[field];
            }

            // The postfix only applies to the title, never the description.
            this.targetElements[field].textContent = field === 'title'
                ? value + this.titlePostfix
                : value;
        }

        this.seoData[field] = seoFieldValue;
        this.persist();
    }

    // Strips HTML from every field on save (not just the edited one), so legacy
    // values stored before this sanitization existed are also cleaned and never
    // re-persisted with HTML.
    persist() {
        const clean = {};
        Object.keys(this.seoData).forEach((key) => {
            clean[key] = typeof this.seoData[key] === 'string'
                ? this.stripHtml(this.seoData[key])
                : this.seoData[key];
        });
        this.seoField.value = JSON.stringify(clean);
    }

    // Removes all HTML tags from a value (and decodes entities) using an inert
    // document, so stored SEO data stays plain text. DOMParser does not execute
    // scripts or load resources, unlike assigning to a detached element's innerHTML.
    stripHtml(value) {
        return new DOMParser().parseFromString(value, 'text/html').body.textContent || '';
    }
}

document.addEventListener("DOMContentLoaded", (event) => {
    const seoSnippet = document.querySelector('.seo_snippet');
    if (seoSnippet) {
        new SeoSnippet(seoSnippet);
    }
});
