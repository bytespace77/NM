<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Network Devices to Assets</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .device-card { border-left: 4px solid; cursor: pointer; transition: all 0.2s; }
        .device-card:hover { transform: translateX(5px); }
        .device-card.online { border-color: #28a745; }
        .device-card.offline { border-color: #dc3545; }
        .device-card.linked { background: #e8f5e9; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="container-fluid mt-4">
        <h4>🔗 Link Network Devices to Assets</h4>
        <p class="text-muted">Connect discovered network devices to your asset inventory</p>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">📡 Network Devices</div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <div id="devicesList"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">💼 Available Assets</div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <div id="assetsList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="linkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Link Device to Asset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="selected_device_id">
                    <div class="mb-3">
                        <label>Device: <strong id="device_name"></strong></label>
                    </div>
                    <div class="mb-3">
                        <label>Select Asset</label>
                        <select id="asset_id" class="form-control"></select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="linkDevice()">Link</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let modal;

        async function loadDevices() {
            const res = await fetch('api_get_devices_fast.php');
            const data = await res.json();
            const html = data.devices.map(d => `
                <div class="card device-card ${d.status} ${d.equipment_type_id ? 'linked' : ''} mb-2" onclick="selectDevice(${d.id}, '${d.name}', ${d.equipment_type_id})">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${d.name}</strong>
                                <br><small>${d.ip_address} - ${d.device_type}</small>
                            </div>
                            <div>
                                <span class="badge bg-${d.status === 'online' ? 'success' : 'danger'}">${d.status}</span>
                                ${d.equipment_type_id ? '<span class="badge bg-info ms-1">Linked</span>' : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
            document.getElementById('devicesList').innerHTML = html;
        }

        async function loadAssets() {
            const res = await fetch('api_assets.php?action=list');
            const data = await res.json();
            const assets = data.data.filter(a => !a.device_id);
            const html = assets.map(a => `
                <div class="card mb-2">
                    <div class="card-body p-3">
                        <strong>${a.name}</strong>
                        <br><small>${a.category_name} - ${a.asset_tag}</small>
                    </div>
                </div>
            `).join('');
            document.getElementById('assetsList').innerHTML = html || '<p class="text-muted">No unlinked assets</p>';
        }

        function selectDevice(deviceId, deviceName, linkedAssetId) {
            if (linkedAssetId) {
                alert('This device is already linked to an asset');
                return;
            }
            document.getElementById('selected_device_id').value = deviceId;
            document.getElementById('device_name').textContent = deviceName;
            loadAvailableAssets();
            modal = new bootstrap.Modal(document.getElementById('linkModal'));
            modal.show();
        }

        async function loadAvailableAssets() {
            const res = await fetch('api_assets.php?action=list');
            const data = await res.json();
            const assets = data.data.filter(a => !a.device_id);
            const html = assets.map(a => `<option value="${a.id}">${a.name} (${a.asset_tag})</option>`).join('');
            document.getElementById('asset_id').innerHTML = html;
        }

        async function linkDevice() {
            const deviceId = document.getElementById('selected_device_id').value;
            const assetId = document.getElementById('asset_id').value;

            const res = await fetch('api_assets.php?action=update', {
                method: 'POST',
                body: JSON.stringify({ id: assetId, device_id: deviceId })
            });

            if ((await res.json()).success) {
                modal.hide();
                loadDevices();
                loadAssets();
            }
        }

        loadDevices();
        loadAssets();
    </script>
</body>
</html>
