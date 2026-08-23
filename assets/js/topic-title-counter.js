(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        const titleInput = document.getElementById('bbp_topic_title');
        if (!titleInput) return;

        titleInput.setAttribute('maxlength', '160');

        const counter = document.createElement('div');
        counter.style.cssText = 'font-size:12px;color:#6b6b75;margin-top:4px;';
        counter.id = 'sb-title-counter';
        titleInput.parentNode.insertBefore(counter, titleInput.nextSibling);

        function update() {
            const len = titleInput.value.length;
            counter.textContent = len + '/160 caractères';
            counter.style.color = len > 140 ? '#d97706' : len >= 160 ? '#dc2626' : '#6b6b75';
        }
        titleInput.addEventListener('input', update);
        update();
    });
})();
