<?php
$token = htmlspecialchars($_GET['t'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexusPanel | Seguimiento en tiempo real</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
        :root {
            color-scheme: dark;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #070b14;
            color: #e2e8f0;
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(4, 8, 16, 0.96);
        }
        .title {
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        .status {
            font-size: 13px;
            color: #93c5fd;
        }
        #map {
            width: 100%;
            height: calc(100vh - 58px);
            min-height: 360px;
        }
        .error {
            color: #fca5a5;
        }
    </style>
</head>
<body data-theme="<?= function_exists('temaActual') ? htmlspecialchars(temaActual()) : 'dark' ?>">
    <div class="topbar">
        <div class="title">NexusPanel | Viaje compartido</div>
        <div class="status" id="status">Cargando ubicacion...</div>
    </div>
    <div id="map"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script>
    const TOKEN = new URLSearchParams(location.search).get('t') || <?= json_encode($token, JSON_UNESCAPED_UNICODE) ?>;
    const statusEl = document.getElementById('status');

    let map = null;
    let marker = null;
    let destinationMarker = null;
    let routeLine = null;
    let polling = null;

    function setStatus(message, isError = false) {
        statusEl.textContent = message;
        statusEl.classList.toggle('error', Boolean(isError));
    }

    function ensureMap(lat, lng) {
        if (!map) {
            map = L.map('map', { zoomControl: true }).setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19
            }).addTo(map);
            marker = L.marker([lat, lng]).addTo(map);
            return;
        }

        marker.setLatLng([lat, lng]);
        map.panTo([lat, lng], { animate: true, duration: 0.6 });
    }

    function ensureBaseMap(lat, lng, zoom = 13) {
        if (map) return;
        map = L.map('map', { zoomControl: true }).setView([lat, lng], zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19
        }).addTo(map);
    }

    function ensureDestinationMarker(lat, lng) {
        if (!map) return;
        if (!destinationMarker) {
            destinationMarker = L.marker([lat, lng]).addTo(map).bindPopup('Destino del pedido');
        } else {
            destinationMarker.setLatLng([lat, lng]);
        }
    }

    function ensureRouteLine(opLat, opLng, cliLat, cliLng) {
        if (!map) return;
        if (opLat === null || opLng === null || cliLat === null || cliLng === null) {
            if (routeLine) {
                map.removeLayer(routeLine);
                routeLine = null;
            }
            return;
        }
        const points = [[opLat, opLng], [cliLat, cliLng]];
        if (!routeLine) {
            routeLine = L.polyline(points, {
                color: '#22d3ee',
                weight: 4,
                opacity: 0.85,
                dashArray: '8,6'
            }).addTo(map);
        } else {
            routeLine.setLatLngs(points);
        }
    }

    async function pollTrackingPublico() {
        try {
            const response = await fetch(`../api/pedido.php?action=tracking_publico&t=${encodeURIComponent(TOKEN)}`, {
                cache: 'no-store'
            });

            const data = await response.json();
            if (!response.ok || !data.ok) {
                setStatus(data.error || 'Este link no es valido o ya expiro.', true);
                if (polling) {
                    clearInterval(polling);
                    polling = null;
                }
                return;
            }

            if (data.lat === null || data.lng === null) {
                const hasDest = data.cli_lat !== null && data.cli_lng !== null;
                if (hasDest) {
                    ensureBaseMap(Number(data.cli_lat), Number(data.cli_lng), 14);
                    ensureDestinationMarker(Number(data.cli_lat), Number(data.cli_lng));
                } else {
                    ensureBaseMap(19.4326, -99.1332, 11);
                }
                setStatus(`Pedido #${data.pedido_id}: esperando ubicacion del repartidor...`);
                return;
            }

            ensureMap(Number(data.lat), Number(data.lng));
            if (data.cli_lat !== null && data.cli_lng !== null) {
                ensureDestinationMarker(Number(data.cli_lat), Number(data.cli_lng));
            }
            ensureRouteLine(
                data.lat !== null ? Number(data.lat) : null,
                data.lng !== null ? Number(data.lng) : null,
                data.cli_lat !== null ? Number(data.cli_lat) : null,
                data.cli_lng !== null ? Number(data.cli_lng) : null
            );
            const hora = data.ts ? new Date(data.ts).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : 'sin hora';
            setStatus(`Pedido #${data.pedido_id} | ${data.estado} | Actualizado: ${hora}`);
        } catch (_) {
            setStatus('No se pudo actualizar el mapa en este momento.', true);
        }
    }

    (async function initPublicTracking() {
        if (!TOKEN) {
            setStatus('Link de seguimiento incompleto.', true);
            return;
        }

        await pollTrackingPublico();
        polling = setInterval(pollTrackingPublico, 7000);
    })();
    </script>
</body>
</html>

