<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes_auth.php';
$user = require_auth([]);

require_once __DIR__ . '/includes_header.php';
?>

<div class="eq-page-head d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h2 class="mb-0">Dashboard</h2>
        <div class="subtitle text-uppercase"><?php echo htmlspecialchars($user['role']); ?> analytics</div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <div id="role-controls"></div>
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
            <div class="card card-dashboard mb-3" id="achievements-panel">
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
            <div class="card card-dashboard h-100" id="community-panel">
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
    <div class="row g-3 mt-3" id="role-sections"></div>
</div>

<script>
    (function () {
        const ctxPrimary = document.getElementById('primaryChart').getContext('2d');
        const ctxSecondary = document.getElementById('secondaryChart').getContext('2d');
        const apiUrl = <?php echo json_encode(url_for('api/dashboard_data.php') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')); ?>;
        let primaryChart, secondaryChart;
        function esc(text) {
            return String(text == null ? '' : text);
        }
        function htmlEscape(text) {
            return String(text == null ? '' : text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
        function scoreChips(scores) {
            if (!scores || !scores.length) {
                return '<div class="small text-muted">No recent tests yet.</div>';
            }
            return '<div class="d-flex flex-wrap gap-2">' + scores.map(function (score) {
                return '<span class="badge rounded-pill text-bg-light border text-dark">' + htmlEscape(score) + '</span>';
            }).join('') + '</div>';
        }
        function renderCharts(data) {
            (data.hideSections || []).forEach(function (id) {
                const el = document.getElementById(id);
                if (el) {
                    el.style.display = 'none';
                }
            });

            const controls = document.getElementById('role-controls');
            controls.innerHTML = '';
            if (data.filters && Array.isArray(data.filters.grades) && data.filters.grades.length > 0) {
                const wrapper = document.createElement('div');
                wrapper.className = 'd-inline-flex align-items-center gap-2';
                const label = document.createElement('span');
                label.className = 'small text-muted';
                label.textContent = 'Filter class';
                const select = document.createElement('select');
                select.className = 'form-select form-select-sm';
                select.style.minWidth = '180px';
                const allOption = document.createElement('option');
                allOption.value = '';
                allOption.textContent = 'All classes';
                select.appendChild(allOption);
                data.filters.grades.forEach(function (grade) {
                    const option = document.createElement('option');
                    option.value = grade;
                    option.textContent = grade;
                    if (data.filters.selectedGrade && data.filters.selectedGrade === grade) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
                select.addEventListener('change', function () {
                    const next = new URL(window.location.href);
                    if (select.value) {
                        next.searchParams.set('grade', select.value);
                    } else {
                        next.searchParams.delete('grade');
                    }
                    window.location.href = next.pathname + (next.search ? next.search : '');
                });
                wrapper.appendChild(label);
                wrapper.appendChild(select);
                controls.appendChild(wrapper);
            }

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
                            if (item.link) {
                                const anchor = document.createElement('a');
                                anchor.href = item.link;
                                anchor.textContent = esc(item.primary || item.title || '');
                                anchor.className = 'text-decoration-none';
                                if (item.link_label) {
                                    anchor.title = item.link_label;
                                }
                                li.appendChild(anchor);
                            } else {
                                li.textContent = esc(item.primary || item.title || '');
                            }
                            if (item.secondary) {
                                li.appendChild(document.createTextNode(' - ' + esc(item.secondary)));
                            }
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
                } else if (w.type === 'table') {
                    const tableWrap = document.createElement('div');
                    tableWrap.className = 'table-responsive';
                    const table = document.createElement('table');
                    table.className = 'table table-sm align-middle mb-0';
                    const thead = document.createElement('thead');
                    const headRow = document.createElement('tr');
                    (w.headers || []).forEach(function (heading) {
                        const th = document.createElement('th');
                        th.scope = 'col';
                        th.textContent = heading;
                        headRow.appendChild(th);
                    });
                    thead.appendChild(headRow);
                    const tbody = document.createElement('tbody');
                    (w.rows || []).forEach(function (row) {
                        const tr = document.createElement('tr');
                        row.forEach(function (cell) {
                            const td = document.createElement('td');
                            td.textContent = cell;
                            tr.appendChild(td);
                        });
                        tbody.appendChild(tr);
                    });
                    if (!(w.rows || []).length) {
                        const tr = document.createElement('tr');
                        const td = document.createElement('td');
                        td.colSpan = (w.headers || []).length || 1;
                        td.className = 'text-muted';
                        td.textContent = esc(w.emptyText || 'No data');
                        tr.appendChild(td);
                        tbody.appendChild(tr);
                    }
                    table.appendChild(thead);
                    table.appendChild(tbody);
                    tableWrap.appendChild(table);
                    body.appendChild(tableWrap);
                } else if (w.type === 'studentCards') {
                    const stack = document.createElement('div');
                    stack.className = 'vstack gap-3';
                    (w.items || []).forEach(function (item) {
                        const card = document.createElement('div');
                        card.className = 'border rounded-3 p-3 bg-light';
                        const top = document.createElement('div');
                        top.className = 'd-flex justify-content-between align-items-start gap-2 mb-2';
                        const left = document.createElement('div');
                        const name = document.createElement('div');
                        name.className = 'fw-semibold';
                        name.textContent = item.primary || item.title || '';
                        const meta = document.createElement('div');
                        meta.className = 'small text-muted';
                        meta.textContent = item.secondary || '';
                        left.appendChild(name);
                        left.appendChild(meta);
                        top.appendChild(left);
                        if (item.link) {
                            const anchor = document.createElement('a');
                            anchor.href = item.link;
                            anchor.className = 'btn btn-sm btn-outline-primary';
                            anchor.textContent = item.link_label || 'Open';
                            top.appendChild(anchor);
                        }
                        card.appendChild(top);
                        if (item.scores && item.scores.length) {
                            const scores = document.createElement('div');
                            scores.innerHTML = scoreChips(item.scores);
                            card.appendChild(scores);
                        }
                        stack.appendChild(card);
                    });
                    if (!(w.items || []).length) {
                        const empty = document.createElement('div');
                        empty.className = 'text-muted small';
                        empty.textContent = esc(w.emptyText || 'No data');
                        stack.appendChild(empty);
                    }
                    body.appendChild(stack);
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

            const sections = document.getElementById('role-sections');
            sections.innerHTML = '';
            (data.roleSections || []).forEach(function (section) {
                const col = document.createElement('div');
                col.className = 'col-md-6';
                const card = document.createElement('div');
                card.className = 'card card-dashboard h-100';
                const body = document.createElement('div');
                body.className = 'card-body';
                const title = document.createElement('h6');
                title.className = 'card-title mb-3';
                title.textContent = esc(section.title || 'Section');
                body.appendChild(title);

                (section.paragraphs || []).forEach(function (para) {
                    const p = document.createElement('p');
                    p.className = 'small text-muted';
                    p.textContent = esc(para);
                    body.appendChild(p);
                });

                if (section.items && section.items.length) {
                    const ul = document.createElement('ul');
                    ul.className = 'list-unstyled small mb-0';
                    section.items.forEach(function (item) {
                        const li = document.createElement('li');
                        li.className = 'mb-2';
                        li.textContent = esc(item);
                        ul.appendChild(li);
                    });
                    body.appendChild(ul);
                }

                card.appendChild(body);
                col.appendChild(card);
                sections.appendChild(col);
            });
        }

        $.getJSON(apiUrl)
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
