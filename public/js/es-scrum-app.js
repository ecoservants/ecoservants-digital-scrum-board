(function () {
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

        // Recommendations Container
        var recoContainer = document.createElement('div');
        recoContainer.id = 'es-reco-container';
        recoContainer.style.marginTop = '20px';
        recoContainer.style.padding = '15px';
        recoContainer.style.border = '1px solid #243b7e'; // Brand color
        recoContainer.style.borderRadius = '5px';
        recoContainer.style.display = 'none';

        var recoTitle = document.createElement('h3');
        recoTitle.textContent = 'Recommended Tasks For You';
        recoContainer.appendChild(recoTitle);

        var recoList = document.createElement('ul');
        recoContainer.appendChild(recoList);

        container.appendChild(heading);
        container.appendChild(info);
        container.appendChild(statusBox);
        container.appendChild(recoContainer);

        // Call the ping endpoint to show connectivity.
        if (!window.ESScrumConfig || !ESScrumConfig.restUrl) {
            statusBox.textContent = 'ESScrumConfig is missing. REST URL not available.';
            return;
        }

        var urlPing = ESScrumConfig.restUrl.replace(/\/$/, '') + '/ping';

        fetch(urlPing, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': ESScrumConfig.nonce
            },
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                statusBox.textContent = JSON.stringify(data, null, 2);
                log('Ping response: ' + JSON.stringify(data));

                // If ping succeeds, fetch recommendations
                fetchRecommendations();
            })
            .catch(function (error) {
                statusBox.textContent = 'Error calling Scrum API: ' + error;
                log(error);
            });

        function fetchRecommendations() {
            var urlRec = ESScrumConfig.restUrl.replace(/\/$/, '') + '/recommendations';

            fetch(urlRec, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': ESScrumConfig.nonce
                },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (tasks) {
                    if (Array.isArray(tasks) && tasks.length > 0) {
                        recoContainer.style.display = 'block';
                        recoList.innerHTML = '';
                        tasks.forEach(function (task) {
                            var li = document.createElement('li');
                            li.style.marginBottom = '10px';
                            li.innerHTML = '<strong>' + task.title + '</strong> (' + task.priority + ') ';

                            var btn = document.createElement('button');
                            btn.textContent = 'Claim Task';
                            btn.style.marginLeft = '10px';
                            btn.style.cursor = 'pointer';
                            btn.onclick = function () {
                                claimTask(task.id, li);
                            };

                            li.appendChild(btn);
                            recoList.appendChild(li);
                        });
                    } else {
                        recoContainer.innerHTML = '<p>No recommendations found for your program group.</p>';
                        recoContainer.style.display = 'block';
                    }
                })
                .catch(function (err) {
                    console.error('Error fetching recommendations', err);
                });
        }

        function claimTask(taskId, liElement) {
            var urlClaim = ESScrumConfig.restUrl.replace(/\/$/, '') + '/tasks/' + taskId + '/claim';

            // Disable button
            var btn = liElement.querySelector('button');
            btn.disabled = true;
            btn.textContent = 'Claiming...';

            fetch(urlClaim, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': ESScrumConfig.nonce
                },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        liElement.innerHTML = '<em>Task claimed! Moved to In Progress.</em>';
                        setTimeout(function () {
                            liElement.remove();
                        }, 2000);
                    } else {
                        alert('Error: ' + (res.message || 'Unknown error'));
                        btn.disabled = false;
                        btn.textContent = 'Claim Task';
                    }
                })
                .catch(function (err) {
                    console.error(err);
                    alert('Network error');
                    btn.disabled = false;
                    btn.textContent = 'Claim Task';
                });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
