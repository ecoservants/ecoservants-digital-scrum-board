(function() {
    // Simple starter JS for the EcoServants Scrum Board app.
    // This is intentionally lightweight so interns can replace it with a full React app later.

    function log(msg) {
        if (window.console && console.log) {
            console.log('[EcoServants Scrum]', msg);
        }
    }

    function init() {
        var container = document.getElementById('es-scrum-board-app');
        if (!container) {
            return;
        }

        // Clear existing content
        container.innerHTML = '';

        var heading = document.createElement('h2');
        heading.textContent = 'EcoServants Digital Scrum Board – Starter View';

        var info = document.createElement('p');
        info.textContent = 'This is a starter placeholder. Interns will replace this with a full React-based Scrum board UI.';

        var statusBox = document.createElement('pre');
        statusBox.style.background = '#f7f7f7';
        statusBox.style.border = '1px solid #ddd';
        statusBox.style.padding = '10px';
        statusBox.style.borderRadius = '4px';
        statusBox.textContent = 'Checking Scrum API status…';

        container.appendChild(heading);
        container.appendChild(info);
        container.appendChild(statusBox);

        // Call the ping endpoint to show connectivity.
        if (!window.ESScrumConfig || !ESScrumConfig.restUrl) {
            statusBox.textContent = 'ESScrumConfig is missing. REST URL not available.';
            return;
        }

        var url = ESScrumConfig.restUrl.replace(/\/$/, '') + '/ping';

        fetch(url, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': ESScrumConfig.nonce
            },
            credentials: 'same-origin'
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                statusBox.textContent = JSON.stringify(data, null, 2);
                log('Ping response: ' + JSON.stringify(data));
            })
            .catch(function(error) {
                statusBox.textContent = 'Error calling Scrum API: ' + error;
                log(error);
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
