import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('headerSearch', () => ({
        scrolled: false,
        open: false,
        q: '',
        loading: false,
        timer: null,
        results: { videos: [], categories: [], collections: [] },
        suggestUrl: '/search/suggest',

        get totalCount() {
            return this.results.videos.length + this.results.categories.length + this.results.collections.length;
        },

        openSearch() {
            this.open = true;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        closeSearch() {
            this.open = false;
            this.q = '';
            this.results = { videos: [], categories: [], collections: [] };
            this.loading = false;
            clearTimeout(this.timer);
        },

        onInput() {
            clearTimeout(this.timer);
            if (this.q.trim().length < 1) {
                this.results = { videos: [], categories: [], collections: [] };
                this.loading = false;
                return;
            }
            this.timer = setTimeout(() => this.fetchResults(), 250);
        },

        async fetchResults() {
            this.loading = true;
            try {
                const res = await fetch(this.suggestUrl + '?q=' + encodeURIComponent(this.q));
                const data = await res.json();
                this.results = data;
            } catch (e) {
                this.results = { videos: [], categories: [], collections: [] };
            }
            this.loading = false;
        },

        submitSearch() {
            if (this.q.trim()) {
                window.location.href = '/search?q=' + encodeURIComponent(this.q);
            }
        },
    }));
});

Alpine.start();
