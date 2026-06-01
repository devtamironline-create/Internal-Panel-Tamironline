{{-- Alpine controller برای صفحه‌ی فرم entity (brand/device):
     - scroll spy → active section در TOC highlight شود
     - expandAll / collapseAll → emit event به همه‌ی section-cards
     - وضعیت collapsed در localStorage حفظ می‌شود (در خود section-card مدیریت می‌شود) --}}
<script>
window.entityFormCollapsed = (function () {
    try {
        const raw = localStorage.getItem('entity-form-collapsed');
        return raw ? JSON.parse(raw) : {};
    } catch (e) { return {}; }
})();

function entityFormPage() {
    return {
        active: '',
        observer: null,
        init() {
            this.$nextTick(() => {
                const sections = document.querySelectorAll('section[data-section]');
                if (!sections.length) return;
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) this.active = entry.target.dataset.section;
                    });
                }, { rootMargin: '-100px 0px -60% 0px', threshold: 0 });
                sections.forEach((s) => this.observer.observe(s));
            });
        },
        expandAll() {
            window.entityFormCollapsed = {};
            try { localStorage.setItem('entity-form-collapsed', '{}'); } catch (e) {}
            window.dispatchEvent(new CustomEvent('expand-all'));
        },
        collapseAll() {
            window.dispatchEvent(new CustomEvent('collapse-all'));
        },
    };
}
</script>
