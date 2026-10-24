/**
 * Behaviour tests for assets/seo/seo.js, the admin "Google snippet preview".
 *
 * The real script is evaluated in a jsdom page that mirrors the markup of
 * templates/seo.html.twig: the SEO inputs, the hidden JSON field, the snippet
 * container with its data attributes, and the record's own content fields that
 * `fields.title`, `fields.description` and `fields.slug` point to.
 *
 * Tests marked `todo` describe known bugs. They run and are reported, but do
 * not fail the suite until the linked issue is fixed.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const script = readFileSync(new URL('../../assets/seo/seo.js', import.meta.url), 'utf8');

const ISSUE_14 = 'https://github.com/AppoloDev/bolt-seo/issues/14';

const EMPTY_SEO = { title: '', description: '', keywords: '', shortlink: '', canonical: '', robots: '', og: '' };

async function mount({
    seo = {},
    descriptionField = 'fields[body]',
    descriptionValue = '',
    titleValue = '',
} = {}) {
    const dom = new JSDOM(`<!doctype html><body>
        <input name="fields[title]">
        <textarea name="fields[body]"></textarea>
        <input name="fields[slug]">
        <input id="seofields-title">
        <textarea id="seofields-description"></textarea>
        <textarea id="seofields-keywords"></textarea>
        <input id="seofields-shortlink">
        <input id="seofields-canonical">
        <select id="seofields-robots"><option value=""></option><option value="noindex, follow">noindex, follow</option></select>
        <input id="seofields-og">
        <div class="seo_snippet">
            <div class="url"><span class="url"></span></div>
            <div class="title"></div>
            <div class="description"></div>
        </div>
        <input type="hidden" name="fields[seo]">
    </body>`, { runScripts: 'outside-only' });

    const { window } = dom;
    const { document } = window;
    const storedSeo = { ...EMPTY_SEO, ...seo };

    Object.assign(document.querySelector('.seo_snippet').dataset, {
        baseUrl: 'https://example.test/page/REPLACE',
        baseTitle: 'Base title',
        baseSlug: 'base-slug',
        baseDescription: 'Base description',
        baseTitlePostfix: ' | Site',
        fieldTitle: 'fields[title]',
        fieldDescription: descriptionField,
        fieldSlug: 'fields[slug]',
        seoField: 'fields[seo]',
    });
    document.querySelector('[name="fields[seo]"]').value = JSON.stringify(storedSeo);
    for (const key of Object.keys(EMPTY_SEO)) {
        document.querySelector(`#seofields-${key}`).value = storedSeo[key];
    }
    document.querySelector('[name="fields[title]"]').value = titleValue;
    document.querySelector('[name="fields[body]"]').value = descriptionValue;
    document.querySelector('[name="fields[slug]"]').value = 'record-slug';

    // Evaluate the script while the document is still loading, then let jsdom
    // fire DOMContentLoaded itself, exactly like a browser.
    window.eval(script);
    await new Promise((resolve) => document.addEventListener('DOMContentLoaded', resolve));

    return {
        window,
        document,
        preview: {
            title: () => document.querySelector('.seo_snippet .title').textContent,
            description: () => document.querySelector('.seo_snippet .description').textContent,
            url: () => document.querySelector('.seo_snippet span.url').textContent,
        },
        storedSeo: () => JSON.parse(document.querySelector('[name="fields[seo]"]').value),
        // A user typing, pasting or autofilling: the value changes and `input` fires.
        type(selector, value) {
            const element = document.querySelector(selector);
            element.value = value;
            element.dispatchEvent(new window.Event('input', { bubbles: true }));
        },
        // A rich-text editor (Redactor, Article, Bolt's HTML field) copying its
        // content into the hidden <textarea> from script: no event fires.
        editorWrites(value) {
            document.querySelector('[name="fields[body]"]').value = value;
        },
    };
}

// --- initial render ------------------------------------------------------------

test('shows the base title with the postfix, the base description and the URL with the base slug', async () => {
    const page = await mount();

    assert.equal(page.preview.title(), 'Base title | Site');
    assert.equal(page.preview.description(), 'Base description');
    assert.equal(page.preview.url(), 'https://example.test/page/base-slug');
});

test('shows the stored SEO title and description instead of the base values', async () => {
    const page = await mount({ seo: { title: 'SEO title', description: 'SEO description' } });

    assert.equal(page.preview.title(), 'SEO title | Site');
    assert.equal(page.preview.description(), 'SEO description');
});

// --- SEO inputs ------------------------------------------------------------------

test('typing an SEO title shows it with the postfix and persists it', async () => {
    const page = await mount({ titleValue: 'Record title' });

    page.type('#seofields-title', 'SEO title');

    assert.equal(page.preview.title(), 'SEO title | Site');
    assert.equal(page.storedSeo().title, 'SEO title');
});

test('typing an SEO description shows it and persists it', async () => {
    const page = await mount({ descriptionValue: 'Body text' });

    page.type('#seofields-description', 'SEO description');

    assert.equal(page.preview.description(), 'SEO description');
    assert.equal(page.storedSeo().description, 'SEO description');
});

test('strips HTML from SEO input values before showing and persisting them', async () => {
    const page = await mount();

    page.type('#seofields-description', 'My <b>SEO</b> description');

    assert.equal(page.preview.description(), 'My SEO description');
    assert.equal(page.storedSeo().description, 'My SEO description');
});

for (const key of ['keywords', 'shortlink', 'canonical', 'og']) {
    test(`typing in the SEO ${key} input persists it`, async () => {
        const page = await mount();

        page.type(`#seofields-${key}`, `${key} value`);

        assert.equal(page.storedSeo()[key], `${key} value`);
    });
}

test('changing the robots select persists it', async () => {
    const page = await mount();
    const robots = page.document.querySelector('#seofields-robots');

    robots.value = 'noindex, follow';
    robots.dispatchEvent(new page.window.Event('change', { bubbles: true }));

    assert.equal(page.storedSeo().robots, 'noindex, follow');
});

test('strips HTML from previously stored values on the next save', async () => {
    const page = await mount({ seo: { keywords: 'legacy <b>keywords</b>' } });

    page.type('#seofields-title', 'SEO title');

    assert.equal(page.storedSeo().keywords, 'legacy keywords');
});

// --- content fields used as fallbacks ------------------------------------------

test('follows typing in the title field while the SEO title is empty', async () => {
    const page = await mount();

    page.type('[name="fields[title]"]', 'Record title');

    assert.equal(page.preview.title(), 'Record title | Site');
});

test('follows typing in a plain-text description field while the SEO description is empty', async () => {
    const page = await mount();

    page.type('[name="fields[body]"]', 'Typed teaser');

    assert.equal(page.preview.description(), 'Typed teaser');
});

test('the SEO description wins over the description field', async () => {
    const page = await mount({ seo: { description: 'SEO description' } });

    page.type('[name="fields[body]"]', 'Typed teaser');

    assert.equal(page.preview.description(), 'SEO description');
});

test('clearing the SEO description shows the description field instead', async () => {
    const page = await mount({ seo: { description: 'SEO description' }, descriptionValue: 'Teaser text' });

    page.type('#seofields-description', '');

    assert.equal(page.preview.description(), 'Teaser text');
});

test('clearing the SEO description shows the base description when the description field is empty', async () => {
    const page = await mount({ seo: { description: 'SEO description' } });

    page.type('#seofields-description', '');

    assert.equal(page.preview.description(), 'Base description');
});

test('works when no content field is mapped to the description', async () => {
    const page = await mount({ descriptionField: '' });

    page.type('#seofields-description', 'SEO description');
    assert.equal(page.preview.description(), 'SEO description');

    page.type('#seofields-description', '');
    assert.equal(page.preview.description(), 'Base description');
});

test('typing in the slug field updates the preview URL', async () => {
    const page = await mount();

    page.type('[name="fields[slug]"]', 'new-slug');

    assert.equal(page.preview.url(), 'https://example.test/page/new-slug');
});

// --- known bugs ------------------------------------------------------------------

test('follows a rich-text description field, which is written from script without an input event', { todo: ISSUE_14 }, async () => {
    const page = await mount({ descriptionValue: '<p>Original intro</p>' });

    page.editorWrites('<p>Edited intro</p>');

    assert.equal(page.preview.description(), 'Edited intro');
});

test('shows a rich-text description field as plain text, not as HTML markup', { todo: ISSUE_14 }, async () => {
    const page = await mount({ seo: { description: 'SEO description' }, descriptionValue: '<p>Intro with <strong>markup</strong></p>' });

    page.type('#seofields-description', '');

    assert.equal(page.preview.description(), 'Intro with markup');
});
