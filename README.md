Bolt SEO extension
==================

The Bolt SEO extension is an extension to help you improve the search engine
indexing of your Bolt website in a number of ways. It does this by:

  - Allowing you to specify the SEO title and meta-description for your pages.
  - Adding meta tags to your HTML to facilitate indexing of your pages using meta
    tags and OG tags.
  - Override the canonical, if you really want to.
  - Set the `<meta name="robots">`-tag.

Setup
-----

To use this extension, you should add a field to your contenttypes, and add the
tags to the header of your HMTL templates.

In your contenttypes, you should add a single `seo` field. The extenion will
use this to store the data for the different fields that show in the backend
when editing a record. Simply add it to your fields like this;

```yaml
pages:
    name: Pages
    singular_name: Page
    fields:
        [..]
        seo:
            type: seo
            group: "SEO settings"
```

You can assign the fields their own tab, using the `group: 'SEO settings'`, to
keep them organised in the backend.

After you've done this, it will look like this in the Bolt backend:

![](screenshots/screenshot.png)

To add the SEO title and Meta tags to your HTML, edit your templates (the
'master' or 'header') to have the following:

```HTML
    <title>{{ seo.title() }}</title>
    {{ seo.metatags() }}
```

When you've done this, all pages that make use of these templates will
automatically have the correct `<title>` tag and the meta- and OG-tags.

### Configure the 'meta tags' output

By default, the output of the meta-tags is defined in the file
`vendor/appolodev/bolt-seo/templates/_metatags.twig`. If you'd like to
configure this output, you shouldn't edit this file directly. If you do,
changes will be overwritten on subsequent updates of this extension. Instead,
in `/config/extensions/appolo-boltseo.yaml` uncomment the following lines:

```yaml
templates:
    meta: _metatags.twig
```

Next, copy the file `_metatags.twig` to your theme folder, and the extension
will pick it up from there.

**Note:** This is a new extension, so the functionality is still pretty bare
bones. What's there works well, but there is probably a lot of functionality to
add, to improve search engine indexing. If you'd like to contribute, or have a
good idea, feel free to open an issue on the tracker at the
[SEO Extension repository][gh] on Github.

[gh]: https://github.com/AppoloDev/bolt-seo/issues

### Contributors

* [Bob den Otter](https://github.com/bobdenotter): Thanks to him to allow me to use his original extension [Bolt SEO](https://github.com/bobdenotter/seo)
