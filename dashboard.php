<?php
declare(strict_types=1);

require_once __DIR__ . '/includes_header.php';

$user = require_auth([]);
?>

<div class="eq-page-head d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-0">Dashboard</h2>
        <div class="subtitle text-uppercase"><?php echo htmlspecialchars($user['role']); ?> analytics</div>
    </div>
    <div>
        <span class="me-3">Hi, <?php echo htmlspecialchars($user['name']); ?></span>
        <a href="<?php echo htmlspecialchars(url_for('logout.php')); ?>" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
</div>

<div id="dashboard-content">
    <div class="row g-3 mb-3" id="metric-cards"></div>

    <div class="row g-3">
        <div class="col-md-8">
            <div class="card card-dashboard h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3" id="primary-chart-title">Loading...</h5>
                    <canvas id="primaryChart" height="160"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-dashboard mb-3">
                <div class="card-body">
                    <h6 class="card-title mb-2">Highlights</h6>
                    <ul class="list-unstyled small mb-0" id="highlights-list">
                        <li>Loading...</li>
                    </ul>
                </div>
            </div>
            <div class="card card-dashboard">
                <div class="card-body">
                    <h6 class="card-title mb-2">Recent achievements</h6>
                    <ul class="list-unstyled small mb-0" id="achievements-list">
                        <li>Loading...</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-3">
        <div class="col-md-6">
            <div class="card card-dashboard h-100">
                <div class="card-body">
                    <h6 class="card-title mb-2" id="secondary-chart-title">Progress</h6>
                    <canvas id="secondaryChart" height="140"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-dashboard h-100">
                <div class="card-body">
                    <h6 class="card-title mb-2">Community feed</h6>
                    <ul class="list-group list-group-flush small" id="community-feed">
                        <li class="list-group-item">Loading...</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-3" id="dynamic-widgets"></div>
</div>

<script>
    (function () {
        const ctxPrimary = document.getElementById('primaryChart').getContext('2d');
        const ctxSecondary = document.getElementById('secondaryChart').getContext('2d');
        let primaryChart, secondaryChart;
        function esc(text) {
            return String(text == null ? '' : text);
        }
        function renderCharts(data) {
            document.getElementById('primary-chart-title').textContent = data.primaryChartTitle || 'Overview';
            document.getElementById('secondary-chart-title').textContent = data.secondaryChartTitle || 'Progress';

            if (primaryChart) primaryChart.destroy();
            if (secondaryChart) secondaryChart.destroy();

            if (data.primaryChart && data.primaryChart.type) {
                primaryChart = new Chart(ctxPrimary, data.primaryChart);
            }
            if (data.secondaryChart && data.secondaryChart.type) {
                secondaryChart = new Chart(ctxSecondary, data.secondaryChart);
            }

            const highlightsList = document.getElementById('highlights-list');
            highlightsList.innerHTML = '';
            (data.highlights || []).forEach(function (item) {
                const li = document.createElement('li');
                li.textContent = item;
                highlightsList.appendChild(li);
            });

            const achievementsList = document.getElementById('achievements-list');
            achievementsList.innerHTML = '';
            if ((data.recentAchievements || []).length === 0) {
                const li = document.createElement('li');
                li.textContent = 'No achievements yet.';
                achievementsList.appendChild(li);
            } else {
                data.recentAchievements.forEach(function (a) {
                    const li = document.createElement('li');
                    li.textContent = a.title + ' – ' + a.description;
                    achievementsList.appendChild(li);
                });
            }

            const feed = document.getElementById('community-feed');
            feed.innerHTML = '';
            (data.communityFeed || []).forEach(function (post) {
                const li = document.createElement('li');
                li.className = 'list-group-item';
                const strong = document.createElement('strong');
                strong.textContent = esc(post.user) + ':';
                li.appendChild(strong);
                li.appendChild(document.createTextNode(' ' + esc(post.content)));
                feed.appendChild(li);
            });
            if (!data.communityFeed || data.communityFeed.length === 0) {
                const li = document.createElement('li');
                li.className = 'list-group-item';
                li.textContent = 'No posts yet.';
                feed.appendChild(li);
            }

            const metrics = document.getElementById('metric-cards');
            metrics.innerHTML = '';
            (data.metrics || []).forEach(function (m) {
                const col = document.createElement('div');
                col.className = 'col-6 col-lg-3';
                const card = document.createElement('div');
                card.className = 'card card-dashboard h-100';
                const body = document.createElement('div');
                body.className = 'card-body py-3';
                const label = document.createElement('div');
                label.className = 'small text-muted';
                label.textContent = esc(m.label || '');
                const value = document.createElement('div');
                value.className = 'h5 mb-0';
                value.textContent = esc(m.value || 0);
                body.appendChild(label);
                body.appendChild(value);
                card.appendChild(body);
                col.appendChild(card);
                metrics.appendChild(col);
            });

            const widgetsWrap = document.getElementById('dynamic-widgets');
            widgetsWrap.innerHTML = '';
            (data.widgets || []).forEach(function (w) {
                const col = document.createElement('div');
                col.className = 'col-md-6';
                const card = document.createElement('div');
                card.className = 'card card-dashboard h-100';
                const body = document.createElement('div');
                body.className = 'card-body';
                const title = document.createElement('h6');
                title.className = 'card-title mb-2';
                title.textContent = esc(w.title || 'Widget');
                body.appendChild(title);

                if (w.type === 'list') {
                    const ul = document.createElement('ul');
                    ul.className = 'list-group list-group-flush small';
                    (w.items || []).forEach(function (item) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        if (typeof item === 'string') {
                            li.textContent = item;
                        } else {
                            li.textContent = esc(item.primary || item.title || '') +
                                (item.secondary ? ' - ' + esc(item.secondary) : '');
                        }
                        ul.appendChild(li);
                    });
                    if (!w.items || w.items.length === 0) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        li.textContent = esc(w.emptyText || 'No data');
                        ul.appendChild(li);
                    }
                    body.appendChild(ul);
                } else if (w.type === 'text') {
                    const p = document.createElement('p');
                    p.className = 'small text-muted mb-0';
                    p.textContent = esc(w.content || '');
                    body.appendChild(p);
                }

                card.appendChild(body);
                col.appendChild(card);
                widgetsWrap.appendChild(col);
            });
        }

        $.getJSON('<?php echo htmlspecialchars(url_for('api/dashboard_data.php')); ?>')
            .done(function (response) {
                renderCharts(response);
            })
            .fail(function () {
                alert('Failed to load dashboard data.');
            });
    })();
</script>

<?php
require_once __DIR__ . '/includes_footer.php';
