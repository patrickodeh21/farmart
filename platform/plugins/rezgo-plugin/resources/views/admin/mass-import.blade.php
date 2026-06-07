@extends('core/base::layouts/master')

@section('content')
<div class="container-xl">
    <div class="page-wrapper">
        <div class="container-xl">
            <div class="page-header d-print-none">
                <div class="row align-items-center">
                    <div class="col">
                        <h2 class="page-title">Mass Import from Rezgo</h2>
                        <div class="text-muted mt-1">Compare Rezgo inventory against your products and import or deactivate in bulk.</div>
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('rezgo.index') }}" class="btn btn-link">Settings</a>
                        <a href="{{ route('rezgo.mass-import') }}" class="btn btn-outline-secondary">
                            <i class="ti ti-refresh me-1"></i> Refresh
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible">
                        {{ session('success') }}<a class="btn-close" data-bs-dismiss="alert"></a>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible">
                        {{ session('error') }}<a class="btn-close" data-bs-dismiss="alert"></a>
                    </div>
                @endif
                @if ($error)
                    <div class="alert alert-danger">
                        <i class="ti ti-alert-circle me-2"></i> {{ $error }}
                    </div>
                @endif

                {{-- PROGRESS BAR (hidden until import starts) --}}
                <div id="import-progress-wrapper" style="display:none;" class="card mb-4 border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <strong id="progress-label">Importing...</strong>
                            <span id="progress-fraction">0 / 0</span>
                        </div>
                        <div class="progress mb-3" style="height:12px;">
                            <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                                 style="width:0%"></div>
                        </div>
                        <div id="progress-log" style="max-height:180px;overflow-y:auto;font-size:12px;background:#f8f9fa;padding:8px;border-radius:4px;"></div>
                    </div>
                </div>

                {{-- SUMMARY (shown after import) --}}
                <div id="import-summary" style="display:none;" class="alert alert-success mb-4"></div>

                {{-- Seasonal/removed tours are auto-deactivated in the background after each admin visit --}}

                {{-- NEW TOURS --}}
                @if (!empty($newTours))
                <div class="card" id="new-tours-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="ti ti-plus me-2"></i>
                            {{ count($newTours) }} New Tour(s) Available in Rezgo
                        </h3>
                        <div class="card-subtitle text-muted mt-1">
                            Select tours to import. Edit the product name if needed.
                        </div>
                    </div>

                    <div class="card-body pb-0">
                        <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="select-all-new">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all-new">Deselect All</button>

                            {{-- PUBLISH OPTION --}}
                            <div class="ms-3 d-flex align-items-center gap-2">
                                <label class="form-check mb-0 d-flex align-items-center gap-2">
                                    <input type="checkbox" class="form-check-input" id="publish-immediately">
                                    <span class="form-check-label fw-semibold">Publish immediately</span>
                                </label>
                                <small class="text-muted">(unchecked = save as Draft for review)</small>
                            </div>

                            <input type="text" id="new-tour-search" class="form-control form-control-sm ms-auto"
                                   placeholder="Search tours..." style="max-width:220px;">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-vcenter" id="new-tours-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;">
                                        <input type="checkbox" class="form-check-input" id="check-all-new">
                                    </th>
                                    <th>Rezgo Tour Name</th>
                                    <th>Product Name <small class="text-muted">(editable)</small></th>
                                    <th>UID</th>
                                    <th>Tags</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($newTours as $tour)
                                <tr class="new-tour-row" data-name="{{ strtolower($tour['title']) }}"
                                    data-uid="{{ $tour['uid'] }}" data-title="{{ $tour['title'] }}">
                                    <td>
                                        <input type="checkbox" class="form-check-input new-check"
                                               value="{{ $tour['uid'] }}">
                                    </td>
                                    <td class="text-muted small">{{ $tour['title'] }}</td>
                                    <td>
                                        <input type="text"
                                               class="form-control form-control-sm tour-name-input"
                                               data-uid="{{ $tour['uid'] }}"
                                               value="{{ $tour['title'] }}"
                                               placeholder="Product name...">
                                    </td>
                                    <td><code class="small">{{ $tour['uid'] }}</code></td>
                                    <td><small class="text-muted">{{ $tour['tags'] }}</small></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                        <div id="new-pagination-info" class="text-muted small mb-2"></div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div id="new-pagination-controls"></div>
                            <button type="button" class="btn btn-primary" id="import-btn" disabled>
                                <i class="ti ti-cloud-download me-1"></i>
                                Import Selected (<span id="selected-count">0</span>)
                            </button>
                        </div>
                    </div>
                </div>

                @elseif (!$error)
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="ti ti-circle-check text-success" style="font-size:3rem;"></i>
                        <h3 class="mt-3">All Rezgo tours are already imported</h3>
                        <p class="text-muted">No new tours found in Rezgo that aren't already in your store.</p>
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // --- REMOVED TOURS: check-all ---
    var checkAllRemoved = document.getElementById('check-all-removed');
    if (checkAllRemoved) {
        checkAllRemoved.addEventListener('change', function () {
            document.querySelectorAll('.removed-check').forEach(function (c) {
                c.checked = checkAllRemoved.checked;
            });
        });
    }

    // --- NEW TOURS: helpers ---
    var checkAllNew    = document.getElementById('check-all-new');
    var selectAllBtn   = document.getElementById('select-all-new');
    var deselectAllBtn = document.getElementById('deselect-all-new');
    var importBtn      = document.getElementById('import-btn');
    var selectedCount  = document.getElementById('selected-count');
    var publishCheckbox = document.getElementById('publish-immediately');

    function updateImportBtn() {
        var checked = document.querySelectorAll('.new-check:checked').length;
        if (selectedCount) selectedCount.textContent = checked;
        if (importBtn) importBtn.disabled = checked === 0;
    }

    if (selectAllBtn) selectAllBtn.addEventListener('click', function () {
        document.querySelectorAll('.new-check').forEach(function (c) { c.checked = true; });
        updateImportBtn();
    });
    if (deselectAllBtn) deselectAllBtn.addEventListener('click', function () {
        document.querySelectorAll('.new-check').forEach(function (c) { c.checked = false; });
        updateImportBtn();
    });
    if (checkAllNew) {
        checkAllNew.addEventListener('change', function () {
            document.querySelectorAll('.new-check').forEach(function (c) { c.checked = checkAllNew.checked; });
            updateImportBtn();
        });
    }
    document.querySelectorAll('.new-check').forEach(function (c) {
        c.addEventListener('change', updateImportBtn);
    });

    // --- PAGINATION ---
    var rowsPerPage = 25;
    var currentPage = 1;
    var visibleRows = [];
    var table    = document.getElementById('new-tours-table');
    var search   = document.getElementById('new-tour-search');
    var info     = document.getElementById('new-pagination-info');
    var controls = document.getElementById('new-pagination-controls');

    function getRows() {
        return table ? Array.from(table.querySelectorAll('tbody .new-tour-row')) : [];
    }
    function filterRows() {
        var q = search ? search.value.toLowerCase() : '';
        getRows().forEach(function (r) { r.style.display = 'none'; });
        visibleRows = getRows().filter(function (r) { return !q || r.dataset.name.includes(q); });
        currentPage = 1;
        renderPage();
    }
    function renderPage() {
        var start = (currentPage - 1) * rowsPerPage;
        var end   = start + rowsPerPage;
        getRows().forEach(function (r) { r.style.display = 'none'; });
        visibleRows.slice(start, end).forEach(function (r) { r.style.display = ''; });
        if (info) {
            info.textContent = visibleRows.length > 0
                ? 'Showing ' + (start + 1) + '–' + Math.min(end, visibleRows.length) + ' of ' + visibleRows.length
                : 'No results';
        }
        renderControls();
    }
    function renderControls() {
        if (!controls) return;
        var pages = Math.ceil(visibleRows.length / rowsPerPage);
        controls.innerHTML = '';
        if (pages <= 1) return;
        for (var i = 1; i <= pages; i++) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = i;
            btn.className = 'btn btn-sm ms-1 ' + (i === currentPage ? 'btn-primary' : 'btn-outline-secondary');
            btn.addEventListener('click', (function (p) { return function () { currentPage = p; renderPage(); }; })(i));
            controls.appendChild(btn);
        }
    }
    if (search) search.addEventListener('input', filterRows);
    visibleRows = getRows();
    renderPage();
    updateImportBtn();

    // --- AJAX IMPORT ONE AT A TIME ---
    var importOneUrl = '{{ route("rezgo.mass-import.import-one") }}';
    var csrfToken    = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function logProgress(msg, type) {
        var log = document.getElementById('progress-log');
        if (!log) return;
        var line = document.createElement('div');
        line.style.color = type === 'error' ? '#c00' : (type === 'skip' ? '#888' : '#155724');
        line.textContent = msg;
        log.appendChild(line);
        log.scrollTop = log.scrollHeight;
    }

    function setProgress(done, total) {
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        var bar = document.getElementById('progress-bar');
        var frac = document.getElementById('progress-fraction');
        var label = document.getElementById('progress-label');
        if (bar)   bar.style.width = pct + '%';
        if (frac)  frac.textContent = done + ' / ' + total;
        if (label) label.textContent = done < total ? 'Importing...' : 'Done!';
    }

    function sleep(ms) { return new Promise(function(r) { setTimeout(r, ms); }); }

    async function callImportOne(uid, title, status) {
        var resp = await fetch(importOneUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ uid: uid, title: title, status: status }),
        });
        return await resp.json();
    }

    async function importSelected() {
        var checked = Array.from(document.querySelectorAll('.new-check:checked'));
        if (checked.length === 0) return;

        var status = (publishCheckbox && publishCheckbox.checked) ? 'published' : 'draft';
        var total  = checked.length;
        var done   = 0;
        var imported = 0;
        var skipped  = 0;
        var failed   = 0;

        // Show progress UI, hide import button
        document.getElementById('import-progress-wrapper').style.display = 'block';
        document.getElementById('import-summary').style.display = 'none';
        if (importBtn) importBtn.disabled = true;

        // Build uid → custom name map from visible inputs
        var nameMap = {};
        document.querySelectorAll('.tour-name-input').forEach(function (input) {
            nameMap[input.dataset.uid] = input.value.trim();
        });

        setProgress(0, total);

        for (var i = 0; i < checked.length; i++) {
            var uid   = checked[i].value;
            var title = nameMap[uid] || uid;

            // 1.5s gap between tours — avoids hammering Rezgo API
            if (i > 0) await sleep(1500);

            try {
                var data = await callImportOne(uid, title, status);

                // JS-level retry: if server reported failure, wait 3s and try once more
                if (!data.success && !data.skipped) {
                    logProgress('↻ Retrying: ' + title, 'skip');
                    await sleep(3000);
                    try {
                        data = await callImportOne(uid, title, status);
                    } catch (retryErr) {
                        data = { success: false, error: retryErr.message };
                    }
                }

                if (data.success && data.skipped) {
                    skipped++;
                    logProgress('⏭ Skipped (already imported): ' + title, 'skip');
                } else if (data.success) {
                    imported++;
                    logProgress('✓ Imported: ' + title + (status === 'published' ? ' [Published]' : ' [Draft]'), 'ok');
                } else {
                    failed++;
                    logProgress('✗ Failed: ' + title + ' — ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                // Network-level error — retry once
                logProgress('↻ Network retry: ' + title, 'skip');
                await sleep(3000);
                try {
                    var data2 = await callImportOne(uid, title, status);
                    if (data2.success && data2.skipped) {
                        skipped++;
                        logProgress('⏭ Skipped (already imported): ' + title, 'skip');
                    } else if (data2.success) {
                        imported++;
                        logProgress('✓ Imported (on retry): ' + title, 'ok');
                    } else {
                        failed++;
                        logProgress('✗ Failed after retry: ' + title + ' — ' + (data2.error || 'Unknown'), 'error');
                    }
                } catch (e2) {
                    failed++;
                    logProgress('✗ Network error: ' + title + ' — ' + e2.message, 'error');
                }
            }

            done++;
            setProgress(done, total);
        }

        // Show summary
        var summaryEl = document.getElementById('import-summary');
        var summaryClass = failed > 0 ? 'alert alert-warning mb-4' : 'alert alert-success mb-4';
        summaryEl.className = summaryClass;
        summaryEl.innerHTML = '<strong>Import complete:</strong> '
            + imported + ' imported, '
            + skipped  + ' skipped, '
            + failed   + ' failed. '
            + (status === 'published' ? 'All published immediately.' : 'All saved as Draft — review and publish when ready.')
            + ' <a href="{{ route("rezgo.mass-import") }}" class="alert-link ms-2">Refresh page</a>';
        summaryEl.style.display = 'block';

        // Update badge cache via a soft reload hint
        if (imported > 0) {
            setTimeout(function () {
                // Optionally reload the page to refresh badge count
            }, 500);
        }
    }

    if (importBtn) {
        importBtn.addEventListener('click', importSelected);
    }
})();
</script>
@endsection
