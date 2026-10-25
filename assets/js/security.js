// Attach the session CSRF token only to same-origin mutations.
(() => {
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const needsToken = (url, method) => new URL(url, location.href).origin === location.origin &&
        !['GET', 'HEAD', 'OPTIONS'].includes(String(method || 'GET').toUpperCase());
    const originalFetch = window.fetch;
    window.fetch = (input, options = {}) => {
        const url = input instanceof Request ? input.url : input;
        const method = options.method || (input instanceof Request ? input.method : 'GET');
        if (needsToken(url, method)) {
            const headers = new Headers(options.headers || (input instanceof Request ? input.headers : undefined));
            headers.set('X-CSRF-Token', token);
            options = {...options, headers};
        }
        return originalFetch.call(window, input, options);
    };
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;
    const requests = new WeakMap();
    XMLHttpRequest.prototype.open = function(method, url, ...args) {
        requests.set(this, needsToken(url, method));
        return originalOpen.call(this, method, url, ...args);
    };
    XMLHttpRequest.prototype.send = function(body) {
        if (requests.get(this)) this.setRequestHeader('X-CSRF-Token', token);
        return originalSend.call(this, body);
    };
})();
