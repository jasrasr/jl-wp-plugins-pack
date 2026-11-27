(function () {
    'use strict';
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.jl-yourls-create');
        if (!button) return;
        var box = button.closest('#jl_yourls_link');
        var result = box.querySelector('.jl-yourls-result');
        var data = new FormData();
        data.append('action', 'jl_yourls_create');
        data.append('post_id', button.dataset.postId);
        data.append('nonce', button.dataset.nonce);
        data.append('keyword', box.querySelector('.jl-yourls-keyword').value);
        button.disabled = true;
        result.textContent = 'Creating short link…';
        fetch(window.jlYourlsAjaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (response) {
                if (!response.success) throw new Error(response.data && response.data.message || 'Could not create the short link.');
                result.textContent = 'Short link created: ' + response.data.url;
                window.location.reload();
            })
            .catch(function (error) { result.textContent = error.message; button.disabled = false; });
    });
}());
