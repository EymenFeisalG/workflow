(() => {
    if (!document.body.classList.contains('centralen-workflow-shell')) return;
    const dock = document.querySelector('.macDockWrapper');
    let last = '';
    function measure() {
        const viewport = window.visualViewport;
        const gap = Math.max(0, innerHeight - ((viewport?.offsetTop || 0) + (viewport?.height || innerHeight)));
        if (dock) dock.style.bottom = gap + 'px';
        const clearance = dock ? Math.ceil(dock.getBoundingClientRect().height + gap + 10) : 0;
        const value = clearance + 'px';
        if (value === last) return;
        last = value;
        document.body.style.setProperty('--shell-bottom-clearance', value);
        document.dispatchEvent(new CustomEvent('centralen:shell-clearance'));
    }
    if (dock) new ResizeObserver(measure).observe(dock);
    window.addEventListener('resize', measure);
    window.visualViewport?.addEventListener('resize', measure);
    window.visualViewport?.addEventListener('scroll', measure);
    measure();
})();
