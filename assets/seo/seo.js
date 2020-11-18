class SeoSnippet {
    constructor(container) {
        this.container = container;
        this.defaultsData = {
            title: this.container.dataset.baseTitle,
            url: this.container.dataset.baseUrl,
            description: this.container.dataset.baseDescription,
        };
        this.targetElements = {
            url: document.querySelector('.seo_snippet span.url'),
            title: document.querySelector('.seo_snippet .title'),
            description: document.querySelector('.seo_snippet .description')
        };

        this.init();
    }

    init() {
        this.targetElements.title.innerHTML = this.defaultsData.title;
        this.targetElements.url.innerHTML = this.defaultsData.url.replace('REPLACE', 'bolt-seo-extension');
        this.targetElements.description.innerHTML = this.defaultsData.description;
    }
}


window.addEventListener("DOMContentLoaded", (event) => {
    const seoSnippet = document.querySelector('.seo_snippet');
    if(seoSnippet) {
        new SeoSnippet(seoSnippet);
    }
});
