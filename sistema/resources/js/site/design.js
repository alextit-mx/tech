/* El contenido siempre está visible; la entrada solo acompaña su lectura. */
if (!document.body.classList.contains('builder-page')) {
    const disclosure = document.querySelector('.filter-disclosure');
    const narrow = window.matchMedia('(max-width: 920px)');
    if (disclosure) {
        const fitFilters = () => { disclosure.open = !narrow.matches; };
        fitFilters();
        narrow.addEventListener('change', fitFilters);
    }

    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                observer.unobserve(entry.target);
                entry.target.classList.add(entry.target.matches('h1, h2') ? 'design-enter-title' : entry.target.matches('.p-card, .map') ? 'design-enter-image' : 'design-enter-row');
            }
        }, { threshold: 0.08 });
        document.querySelectorAll('.page-head h1, .page-head-copy, .section > h2, .sheet > li, .steps > li, .tiers > li, .departments > li, .p-card, .guide > li, .symptoms > li, .faq details, .split > div, .contact-list a, address, .pay .core > *, .map').forEach((element) => observer.observe(element));
    }
}
