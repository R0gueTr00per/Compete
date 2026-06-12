<div id="kompetic-wordmark"
     style="position:fixed;bottom:0;left:0;right:0;z-index:0;pointer-events:none;user-select:none;overflow:hidden;padding:0.4rem 1rem;">
    <svg viewBox="0 0 100 22" xmlns="http://www.w3.org/2000/svg"
         width="100%" style="display:block;opacity:0.02;">
        <text x="0" y="18" font-size="18" font-weight="700"
              textLength="100" lengthAdjust="spacingAndGlyphs"
              fill="currentColor">Kompetic</text>
    </svg>
</div>
<script>
(function () {
    var resizeObs = null;

    function position() {
        var el = document.getElementById('kompetic-wordmark');
        if (!el) return;
        var sidebar = document.querySelector('.fi-sidebar');
        var offset = 0;
        if (sidebar) {
            var rect = sidebar.getBoundingClientRect();
            if (rect.right > 0 && rect.left >= 0) offset = rect.width;
        }
        el.style.left = offset + 'px';
    }

    function init() {
        position();
        if (resizeObs) resizeObs.disconnect();
        var sidebar = document.querySelector('.fi-sidebar');
        if (sidebar && window.ResizeObserver) {
            resizeObs = new ResizeObserver(position);
            resizeObs.observe(sidebar);
        }
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('livewire:navigated', init);
    window.addEventListener('resize', position);
})();
</script>
