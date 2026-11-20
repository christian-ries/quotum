(function () {
    'use strict';
    document.addEventListener('click', async function (event) {
        const button = event.target.closest('.quotum-refresh');
        if (!button || button.disabled) return;
        const box = button.closest('.quotum-refreshable');
        if (!box) return;
        const params = new URLSearchParams({
            exclude: box.dataset.quoteId || '0',
            author: box.dataset.author || '1',
            source: box.dataset.source || '1',
            line: box.dataset.line || '0',
            tags: box.dataset.tags || ''
        });
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(window.quotumRefreshUrl + '?' + params.toString(), {cache: 'no-store', credentials: 'same-origin'});
            if (!response.ok) throw new Error('Request failed');
            const data = await response.json();
            box.querySelector('.quotum-refresh-content').innerHTML = data.html;
            box.dataset.quoteId = String(data.id);
        } catch (error) {
            // Keep the existing quotation visible if the request fails.
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });
}());
